<?php

namespace Tests\Feature;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Models\Friendship;
use App\Models\LogEntry;
use App\Models\MediaItem;
use App\Models\Reaction;
use App\Models\User;
use App\Models\UserMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReactionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function mediaItem(): MediaItem
    {
        return MediaItem::create([
            'type' => MediaType::Movie,
            'source' => MediaSource::Tmdb,
            'external_id' => (string) fake()->unique()->randomNumber(6),
            'title' => fake()->sentence(3),
        ]);
    }

    private function logEntry(User $user, array $overrides = []): LogEntry
    {
        $userMedia = UserMedia::create([
            'user_id' => $user->id,
            'media_item_id' => $this->mediaItem()->id,
            'status' => 'completed',
        ]);

        return LogEntry::create(array_merge([
            'user_media_id' => $userMedia->id,
            'logged_date' => now()->toDateString(),
            'review' => 'A genuinely great watch.',
        ], $overrides));
    }

    private function befriend(User $a, User $b): void
    {
        Friendship::create([
            'requester_id' => $a->id,
            'addressee_id' => $b->id,
            'status' => 'accepted',
            'responded_at' => now(),
        ]);
    }

    public function test_index_requires_authentication(): void
    {
        $entry = $this->logEntry(User::factory()->create());

        $this->getJson("/api/log-entries/{$entry->id}/reactions")->assertUnauthorized();
    }

    public function test_store_requires_authentication(): void
    {
        $entry = $this->logEntry(User::factory()->create());

        $this->postJson("/api/log-entries/{$entry->id}/reactions", ['emoji' => 'heart'])->assertUnauthorized();
    }

    public function test_destroy_requires_authentication(): void
    {
        $entry = $this->logEntry(User::factory()->create());

        $this->deleteJson("/api/log-entries/{$entry->id}/reactions/heart")->assertUnauthorized();
    }

    public function test_reacting_creates_a_reaction(): void
    {
        $author = User::factory()->create();
        $reactor = User::factory()->create();
        $entry = $this->logEntry($author);

        $response = $this->actingAs($reactor)->postJson("/api/log-entries/{$entry->id}/reactions", ['emoji' => 'heart']);

        $response->assertCreated();
        $this->assertDatabaseHas('reactions', ['log_entry_id' => $entry->id, 'user_id' => $reactor->id, 'emoji' => 'heart']);
    }

    public function test_reacting_with_the_same_emoji_twice_is_idempotent(): void
    {
        $author = User::factory()->create();
        $reactor = User::factory()->create();
        $entry = $this->logEntry($author);

        $this->actingAs($reactor)->postJson("/api/log-entries/{$entry->id}/reactions", ['emoji' => 'heart'])->assertCreated();
        $this->actingAs($reactor)->postJson("/api/log-entries/{$entry->id}/reactions", ['emoji' => 'heart'])->assertCreated();

        $this->assertDatabaseCount('reactions', 1);
    }

    public function test_a_user_can_leave_several_distinct_reactions_on_the_same_review(): void
    {
        $author = User::factory()->create();
        $reactor = User::factory()->create();
        $entry = $this->logEntry($author);

        $this->actingAs($reactor)->postJson("/api/log-entries/{$entry->id}/reactions", ['emoji' => 'heart'])->assertCreated();
        $this->actingAs($reactor)->postJson("/api/log-entries/{$entry->id}/reactions", ['emoji' => 'fire'])->assertCreated();

        $this->assertDatabaseCount('reactions', 2);
    }

    public function test_emoji_must_be_one_of_the_fixed_set(): void
    {
        $author = User::factory()->create();
        $entry = $this->logEntry($author);

        $this->actingAs($author)->postJson("/api/log-entries/{$entry->id}/reactions", [
            'emoji' => 'skull',
        ])->assertUnprocessable()->assertJsonValidationErrors('emoji');
    }

    public function test_reacting_is_rejected_when_the_log_entry_has_no_review(): void
    {
        $author = User::factory()->create();
        $entry = $this->logEntry($author, ['review' => null]);

        $this->actingAs($author)->postJson("/api/log-entries/{$entry->id}/reactions", [
            'emoji' => 'heart',
        ])->assertUnprocessable()->assertJsonValidationErrors('log_entry_id');
    }

    public function test_reacting_to_a_private_profiles_review_is_forbidden_for_a_stranger(): void
    {
        $author = User::factory()->create(['profile_visibility' => 'private']);
        $stranger = User::factory()->create();
        $entry = $this->logEntry($author);

        $this->actingAs($stranger)->postJson("/api/log-entries/{$entry->id}/reactions", [
            'emoji' => 'heart',
        ])->assertForbidden();
    }

    /**
     * Authorization must be checked before validation: a stranger forbidden
     * from viewing this entry should get 403, never a 422 that would leak
     * whether the entry exists or has a review to someone not authorized to
     * know either.
     */
    public function test_a_forbidden_stranger_gets_403_even_when_the_log_entry_also_has_no_review(): void
    {
        $author = User::factory()->create(['profile_visibility' => 'private']);
        $stranger = User::factory()->create();
        $entry = $this->logEntry($author, ['review' => null]);

        $this->actingAs($stranger)->postJson("/api/log-entries/{$entry->id}/reactions", [
            'emoji' => 'heart',
        ])->assertForbidden();
    }

    public function test_reacting_to_a_private_profiles_review_is_allowed_for_an_accepted_friend(): void
    {
        $author = User::factory()->create(['profile_visibility' => 'private']);
        $friend = User::factory()->create();
        $this->befriend($author, $friend);
        $entry = $this->logEntry($author);

        $this->actingAs($friend)->postJson("/api/log-entries/{$entry->id}/reactions", [
            'emoji' => 'heart',
        ])->assertCreated();
    }

    public function test_index_returns_counts_per_emoji_reactor_lists_and_the_viewers_own_reactions(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $otherReactor = User::factory()->create();
        $entry = $this->logEntry($author);

        Reaction::create(['log_entry_id' => $entry->id, 'user_id' => $viewer->id, 'emoji' => 'heart']);
        Reaction::create(['log_entry_id' => $entry->id, 'user_id' => $otherReactor->id, 'emoji' => 'heart']);
        Reaction::create(['log_entry_id' => $entry->id, 'user_id' => $viewer->id, 'emoji' => 'fire']);

        $response = $this->actingAs($viewer)->getJson("/api/log-entries/{$entry->id}/reactions")->assertOk();

        $byEmoji = collect($response->json('data.reactions'))->keyBy('emoji');
        $this->assertSame(2, $byEmoji['heart']['count']);
        $this->assertCount(2, $byEmoji['heart']['users']);
        $this->assertSame(1, $byEmoji['fire']['count']);
        $this->assertEqualsCanonicalizing(['heart', 'fire'], $response->json('data.viewer_reactions'));
    }

    public function test_unreacting_removes_the_viewers_own_reaction(): void
    {
        $author = User::factory()->create();
        $reactor = User::factory()->create();
        $entry = $this->logEntry($author);
        Reaction::create(['log_entry_id' => $entry->id, 'user_id' => $reactor->id, 'emoji' => 'heart']);

        $this->actingAs($reactor)->deleteJson("/api/log-entries/{$entry->id}/reactions/heart")->assertNoContent();

        $this->assertDatabaseCount('reactions', 0);
    }

    public function test_unreacting_does_not_remove_another_users_reaction(): void
    {
        $author = User::factory()->create();
        $reactorA = User::factory()->create();
        $reactorB = User::factory()->create();
        $entry = $this->logEntry($author);
        Reaction::create(['log_entry_id' => $entry->id, 'user_id' => $reactorA->id, 'emoji' => 'heart']);
        Reaction::create(['log_entry_id' => $entry->id, 'user_id' => $reactorB->id, 'emoji' => 'heart']);

        $this->actingAs($reactorA)->deleteJson("/api/log-entries/{$entry->id}/reactions/heart")->assertNoContent();

        $this->assertDatabaseCount('reactions', 1);
        $this->assertDatabaseHas('reactions', ['user_id' => $reactorB->id, 'emoji' => 'heart']);
    }

    public function test_unreacting_when_nothing_was_there_is_still_a_no_op_success(): void
    {
        $author = User::factory()->create();
        $entry = $this->logEntry($author);

        $this->actingAs($author)->deleteJson("/api/log-entries/{$entry->id}/reactions/heart")->assertNoContent();
    }
}
