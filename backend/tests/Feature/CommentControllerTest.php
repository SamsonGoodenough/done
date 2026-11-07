<?php

namespace Tests\Feature;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\Friendship;
use App\Models\LogEntry;
use App\Models\MediaItem;
use App\Models\User;
use App\Models\UserMedia;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

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

        $this->getJson("/api/log-entries/{$entry->id}/comments")->assertUnauthorized();
    }

    public function test_store_requires_authentication(): void
    {
        $entry = $this->logEntry(User::factory()->create());

        $this->postJson("/api/log-entries/{$entry->id}/comments", ['body' => 'Nice!'])->assertUnauthorized();
    }

    public function test_destroy_requires_authentication(): void
    {
        $author = User::factory()->create();
        $entry = $this->logEntry($author);
        $comment = Comment::create(['log_entry_id' => $entry->id, 'user_id' => $author->id, 'body' => 'Hi']);

        $this->deleteJson("/api/comments/{$comment->id}")->assertUnauthorized();
    }

    public function test_any_member_can_comment_on_a_public_profiles_review(): void
    {
        $author = User::factory()->create();
        $commenter = User::factory()->create();
        $entry = $this->logEntry($author);

        $response = $this->actingAs($commenter)->postJson("/api/log-entries/{$entry->id}/comments", [
            'body' => 'Totally agree!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.body', 'Totally agree!')
            ->assertJsonPath('data.parent_comment_id', null)
            ->assertJsonPath('data.user.id', $commenter->id);
        $this->assertDatabaseHas('comments', ['log_entry_id' => $entry->id, 'user_id' => $commenter->id]);
    }

    public function test_a_reply_records_its_parent_comment_id(): void
    {
        $author = User::factory()->create();
        $entry = $this->logEntry($author);
        $parent = Comment::create(['log_entry_id' => $entry->id, 'user_id' => $author->id, 'body' => 'Root comment']);

        $response = $this->actingAs($author)->postJson("/api/log-entries/{$entry->id}/comments", [
            'body' => 'A reply',
            'parent_comment_id' => $parent->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.parent_comment_id', $parent->id);
    }

    public function test_parent_comment_id_must_belong_to_the_same_log_entry(): void
    {
        $author = User::factory()->create();
        $entry = $this->logEntry($author);
        $otherEntry = $this->logEntry($author);
        $parentOnOtherEntry = Comment::create(['log_entry_id' => $otherEntry->id, 'user_id' => $author->id, 'body' => 'Elsewhere']);

        $this->actingAs($author)->postJson("/api/log-entries/{$entry->id}/comments", [
            'body' => 'A reply',
            'parent_comment_id' => $parentOnOtherEntry->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('parent_comment_id');
    }

    public function test_replying_to_a_soft_deleted_comment_is_still_allowed(): void
    {
        $author = User::factory()->create();
        $entry = $this->logEntry($author);
        $parent = Comment::create(['log_entry_id' => $entry->id, 'user_id' => $author->id, 'body' => 'Root comment']);
        $parent->delete();

        $this->actingAs($author)->postJson("/api/log-entries/{$entry->id}/comments", [
            'body' => 'Still a valid reply',
            'parent_comment_id' => $parent->id,
        ])->assertCreated();
    }

    public function test_commenting_is_rejected_when_the_log_entry_has_no_review(): void
    {
        $author = User::factory()->create();
        $entry = $this->logEntry($author, ['review' => null]);

        $this->actingAs($author)->postJson("/api/log-entries/{$entry->id}/comments", [
            'body' => 'Nice!',
        ])->assertUnprocessable()->assertJsonValidationErrors('log_entry_id');
    }

    public function test_commenting_on_a_private_profiles_review_is_forbidden_for_a_stranger(): void
    {
        $author = User::factory()->create(['profile_visibility' => 'private']);
        $stranger = User::factory()->create();
        $entry = $this->logEntry($author);

        $this->actingAs($stranger)->postJson("/api/log-entries/{$entry->id}/comments", [
            'body' => 'Nice!',
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

        $this->actingAs($stranger)->postJson("/api/log-entries/{$entry->id}/comments", [
            'body' => 'Nice!',
        ])->assertForbidden();
    }

    public function test_commenting_on_a_private_profiles_review_is_allowed_for_an_accepted_friend(): void
    {
        $author = User::factory()->create(['profile_visibility' => 'private']);
        $friend = User::factory()->create();
        $this->befriend($author, $friend);
        $entry = $this->logEntry($author);

        $this->actingAs($friend)->postJson("/api/log-entries/{$entry->id}/comments", [
            'body' => 'Nice!',
        ])->assertCreated();
    }

    public function test_index_returns_a_flat_chronologically_ordered_list(): void
    {
        $author = User::factory()->create();
        $entry = $this->logEntry($author);
        $first = Comment::create(['log_entry_id' => $entry->id, 'user_id' => $author->id, 'body' => 'First']);
        $reply = Comment::create([
            'log_entry_id' => $entry->id,
            'user_id' => $author->id,
            'parent_comment_id' => $first->id,
            'body' => 'A reply',
        ]);

        $response = $this->actingAs($author)->getJson("/api/log-entries/{$entry->id}/comments")->assertOk();

        $this->assertSame([$first->id, $reply->id], collect($response->json('data'))->pluck('id')->all());
        $response->assertJsonPath('data.1.parent_comment_id', $first->id);
    }

    /**
     * Guards against a tie on second-precision created_at values producing
     * non-deterministic order — same reasoning as
     * ActivityController::newFromFriends's id tiebreak.
     */
    public function test_index_breaks_a_created_at_tie_with_id(): void
    {
        $author = User::factory()->create();
        $entry = $this->logEntry($author);
        $sameInstant = now();
        $first = Comment::create(['log_entry_id' => $entry->id, 'user_id' => $author->id, 'body' => 'First']);
        $first->forceFill(['created_at' => $sameInstant])->save();
        $second = Comment::create(['log_entry_id' => $entry->id, 'user_id' => $author->id, 'body' => 'Second']);
        $second->forceFill(['created_at' => $sameInstant])->save();

        $response = $this->actingAs($author)->getJson("/api/log-entries/{$entry->id}/comments")->assertOk();

        $this->assertSame([$first->id, $second->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_index_includes_soft_deleted_comments_as_deleted_placeholders_with_replies_intact(): void
    {
        $author = User::factory()->create();
        $entry = $this->logEntry($author);
        $parent = Comment::create(['log_entry_id' => $entry->id, 'user_id' => $author->id, 'body' => 'Will be deleted']);
        $reply = Comment::create([
            'log_entry_id' => $entry->id,
            'user_id' => $author->id,
            'parent_comment_id' => $parent->id,
            'body' => 'A reply',
        ]);
        $parent->delete();

        $response = $this->actingAs($author)->getJson("/api/log-entries/{$entry->id}/comments")->assertOk();

        $this->assertCount(2, $response->json('data'));
        $response->assertJsonPath('data.0.body', null)
            ->assertJsonPath('data.0.is_deleted', true)
            ->assertJsonPath('data.1.id', $reply->id)
            ->assertJsonPath('data.1.body', 'A reply');
    }

    public function test_comment_author_can_delete_their_own_comment(): void
    {
        $author = User::factory()->create();
        $entry = $this->logEntry($author);
        $comment = Comment::create(['log_entry_id' => $entry->id, 'user_id' => $author->id, 'body' => 'Oops']);

        $this->actingAs($author)->deleteJson("/api/comments/{$comment->id}")->assertNoContent();

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_the_reviews_own_author_can_delete_someone_elses_comment_on_it(): void
    {
        $reviewAuthor = User::factory()->create();
        $commenter = User::factory()->create();
        $entry = $this->logEntry($reviewAuthor);
        $comment = Comment::create(['log_entry_id' => $entry->id, 'user_id' => $commenter->id, 'body' => 'Rude comment']);

        $this->actingAs($reviewAuthor)->deleteJson("/api/comments/{$comment->id}")->assertNoContent();

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_an_admin_can_delete_any_comment_instance_wide(): void
    {
        $reviewAuthor = User::factory()->create();
        $commenter = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::Admin->value);
        $entry = $this->logEntry($reviewAuthor);
        $comment = Comment::create(['log_entry_id' => $entry->id, 'user_id' => $commenter->id, 'body' => 'Rude comment']);

        $this->actingAs($admin)->deleteJson("/api/comments/{$comment->id}")->assertNoContent();

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_a_stranger_cannot_delete_someone_elses_comment(): void
    {
        $reviewAuthor = User::factory()->create();
        $commenter = User::factory()->create();
        $stranger = User::factory()->create();
        $entry = $this->logEntry($reviewAuthor);
        $comment = Comment::create(['log_entry_id' => $entry->id, 'user_id' => $commenter->id, 'body' => 'Comment']);

        $this->actingAs($stranger)->deleteJson("/api/comments/{$comment->id}")->assertForbidden();
    }

    public function test_deleting_a_comment_with_replies_preserves_the_replies(): void
    {
        $author = User::factory()->create();
        $entry = $this->logEntry($author);
        $parent = Comment::create(['log_entry_id' => $entry->id, 'user_id' => $author->id, 'body' => 'Parent']);
        $reply = Comment::create([
            'log_entry_id' => $entry->id,
            'user_id' => $author->id,
            'parent_comment_id' => $parent->id,
            'body' => 'Reply',
        ]);

        $this->actingAs($author)->deleteJson("/api/comments/{$parent->id}")->assertNoContent();

        $this->assertSoftDeleted('comments', ['id' => $parent->id]);
        $this->assertDatabaseHas('comments', ['id' => $reply->id, 'deleted_at' => null]);
    }
}
