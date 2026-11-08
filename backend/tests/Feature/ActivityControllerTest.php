<?php

namespace Tests\Feature;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Models\Comment;
use App\Models\Friendship;
use App\Models\LogEntry;
use App\Models\MediaItem;
use App\Models\Reaction;
use App\Models\User;
use App\Models\UserMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActivityControllerTest extends TestCase
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

    /**
     * created_at isn't fillable (see LogEntry's #[Fillable] attribute), so a
     * caller-supplied value is applied via forceFill after creation instead
     * of being silently dropped by mass assignment.
     */
    private function logEntry(User $user, array $overrides = []): LogEntry
    {
        $userMedia = UserMedia::create([
            'user_id' => $user->id,
            'media_item_id' => $this->mediaItem()->id,
            'status' => 'completed',
        ]);

        $createdAt = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);

        $entry = LogEntry::create(array_merge([
            'user_media_id' => $userMedia->id,
            'logged_date' => now()->toDateString(),
        ], $overrides));

        if ($createdAt !== null) {
            $entry->forceFill(['created_at' => $createdAt])->save();
        }

        return $entry;
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

    public function test_feed_requires_authentication(): void
    {
        $this->getJson('/api/activity/feed')->assertUnauthorized();
    }

    public function test_feed_shows_friends_entries_including_unrated_ones(): void
    {
        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $this->befriend($viewer, $friend);

        $this->logEntry($friend, ['rating' => null, 'review' => null]);
        $this->logEntry($friend, ['rating' => 4.5, 'review' => 'Great!']);

        $response = $this->actingAs($viewer)->getJson('/api/activity/feed')->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_feed_excludes_a_non_friends_entries(): void
    {
        $viewer = User::factory()->create();
        $stranger = User::factory()->create();
        $this->logEntry($stranger);

        $response = $this->actingAs($viewer)->getJson('/api/activity/feed')->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    public function test_feed_falls_back_to_the_viewers_own_entries_with_no_friends(): void
    {
        $viewer = User::factory()->create();
        $this->logEntry($viewer, ['rating' => null]);

        $response = $this->actingAs($viewer)->getJson('/api/activity/feed')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($viewer->id, $response->json('data.0.user.id'));
    }

    public function test_feed_does_not_show_a_non_friends_entries_just_because_the_viewer_is_tagged_on_one(): void
    {
        $viewer = User::factory()->create();
        $stranger = User::factory()->create();
        $entry = $this->logEntry($stranger);
        $entry->participants()->attach($viewer->id);

        $response = $this->actingAs($viewer)->getJson('/api/activity/feed')->assertOk();

        // No friendship with $stranger, so their entry (even tagging the
        // viewer) doesn't surface, and the viewer has no entries of their
        // own either: true empty feed, not a fallback trigger since the
        // viewer genuinely has zero friends and zero entries.
        $this->assertCount(0, $response->json('data'));
    }

    /**
     * See docs/adr/0006-comment-reaction-summaries-embedded-in-existing-endpoints.md.
     */
    public function test_feed_includes_comment_count_and_the_viewers_own_reaction_summary(): void
    {
        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $this->befriend($viewer, $friend);

        $entry = $this->logEntry($friend, ['review' => 'Great episode']);
        Comment::create(['log_entry_id' => $entry->id, 'user_id' => $viewer->id, 'body' => 'Agreed']);
        Reaction::create(['log_entry_id' => $entry->id, 'user_id' => $viewer->id, 'emoji' => 'fire']);
        Reaction::create(['log_entry_id' => $entry->id, 'user_id' => $friend->id, 'emoji' => 'fire']);

        $response = $this->actingAs($viewer)->getJson('/api/activity/feed')->assertOk();

        $response->assertJsonPath('data.0.comment_count', 1)
            ->assertJsonPath('data.0.reaction_summary.count', 2)
            ->assertJsonPath('data.0.reaction_summary.viewer_reactions', ['fire']);
    }

    public function test_new_from_friends_requires_authentication(): void
    {
        $this->getJson('/api/activity/new-from-friends')->assertUnauthorized();
    }

    public function test_new_from_friends_returns_one_entry_per_friend_sorted_by_recency(): void
    {
        $viewer = User::factory()->create();
        $friendA = User::factory()->create();
        $friendB = User::factory()->create();
        $this->befriend($viewer, $friendA);
        $this->befriend($viewer, $friendB);

        $this->logEntry($friendA, ['created_at' => now()->subDays(10)]);
        $recentA = $this->logEntry($friendA, ['created_at' => now()->subDay()]);
        $recentB = $this->logEntry($friendB, ['created_at' => now()]);

        $response = $this->actingAs($viewer)->getJson('/api/activity/new-from-friends')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertCount(2, $ids);
        $this->assertSame([$recentB->id, $recentA->id], $ids->values()->all());
    }

    public function test_new_from_friends_shows_a_friends_only_entry_even_if_months_old(): void
    {
        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $this->befriend($viewer, $friend);

        $old = $this->logEntry($friend, ['created_at' => now()->subMonths(6)]);

        $response = $this->actingAs($viewer)->getJson('/api/activity/new-from-friends')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($old->id, $response->json('data.0.id'));
    }

    public function test_new_from_friends_ignores_entries_where_the_friend_is_only_a_tagged_participant(): void
    {
        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $someoneElse = User::factory()->create();
        $this->befriend($viewer, $friend);

        // $friend is tagged on someone else's entry, but never logged
        // anything themselves.
        $entry = $this->logEntry($someoneElse);
        $entry->participants()->attach($friend->id);

        $response = $this->actingAs($viewer)->getJson('/api/activity/new-from-friends')->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    public function test_new_from_friends_is_empty_with_no_friends(): void
    {
        $viewer = User::factory()->create();
        $this->logEntry($viewer);

        $response = $this->actingAs($viewer)->getJson('/api/activity/new-from-friends')->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    /**
     * Guards against the classic MAX(created_at)-plus-self-join bug: that
     * approach matches on the timestamp value, not a unique row, so two
     * entries sharing the exact same created_at would both come back
     * instead of exactly one. The window-function query breaks the tie
     * with `id DESC` instead.
     */
    public function test_new_from_friends_returns_exactly_one_row_when_two_entries_share_the_same_timestamp(): void
    {
        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $this->befriend($viewer, $friend);

        $sameInstant = now();
        $first = $this->logEntry($friend, ['created_at' => $sameInstant]);
        $second = $this->logEntry($friend, ['created_at' => $sameInstant]);

        $response = $this->actingAs($viewer)->getJson('/api/activity/new-from-friends')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame(max($first->id, $second->id), $response->json('data.0.id'));
    }

    /**
     * Pins the query-count property that makes this a single-query
     * endpoint rather than an N+1: resolving for 10 friends shouldn't
     * issue more queries than resolving for 5.
     */
    public function test_new_from_friends_query_count_does_not_scale_with_friend_count(): void
    {
        $fewFriendsViewer = User::factory()->create();
        foreach (User::factory()->count(5)->create() as $friend) {
            $this->befriend($fewFriendsViewer, $friend);
            $this->logEntry($friend);
        }

        DB::enableQueryLog();
        $this->actingAs($fewFriendsViewer)->getJson('/api/activity/new-from-friends')->assertOk();
        $queryCountForFive = count(DB::getQueryLog());
        DB::flushQueryLog();
        DB::disableQueryLog();

        $manyFriendsViewer = User::factory()->create();
        foreach (User::factory()->count(10)->create() as $friend) {
            $this->befriend($manyFriendsViewer, $friend);
            $this->logEntry($friend);
        }

        DB::enableQueryLog();
        $this->actingAs($manyFriendsViewer)->getJson('/api/activity/new-from-friends')->assertOk();
        $queryCountForTen = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($queryCountForFive, $queryCountForTen);
    }

    /**
     * See docs/adr/0006-comment-reaction-summaries-embedded-in-existing-endpoints.md.
     */
    public function test_new_from_friends_includes_comment_count_and_reaction_summary(): void
    {
        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $this->befriend($viewer, $friend);

        $entry = $this->logEntry($friend, ['review' => 'Great episode']);
        Comment::create(['log_entry_id' => $entry->id, 'user_id' => $friend->id, 'body' => 'Self-reply']);

        $response = $this->actingAs($viewer)->getJson('/api/activity/new-from-friends')->assertOk();

        $response->assertJsonPath('data.0.comment_count', 1)
            ->assertJsonPath('data.0.reaction_summary.count', 0)
            ->assertJsonPath('data.0.reaction_summary.viewer_reactions', []);
    }
}
