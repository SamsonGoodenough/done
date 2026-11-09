<?php

namespace Tests\Feature;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Events\CommentPosted;
use App\Events\ReactionAdded;
use App\Listeners\CreateCommentNotification;
use App\Listeners\CreateReactionNotification;
use App\Models\Comment;
use App\Models\Friendship;
use App\Models\LogEntry;
use App\Models\MediaItem;
use App\Models\Notification;
use App\Models\Reaction;
use App\Models\User;
use App\Models\UserMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
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

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
    }

    public function test_unread_count_requires_authentication(): void
    {
        $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
    }

    public function test_mark_read_requires_authentication(): void
    {
        $user = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'tagged',
            'data' => ['log_entry_id' => 1, 'from_user_id' => $user->id],
        ]);

        $this->patchJson("/api/notifications/{$notification->id}/read")->assertUnauthorized();
    }

    public function test_a_stranger_cannot_mark_someone_elses_notification_read(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $owner->id,
            'type' => 'tagged',
            'data' => ['log_entry_id' => 1, 'from_user_id' => $stranger->id],
        ]);

        $this->actingAs($stranger)->patchJson("/api/notifications/{$notification->id}/read")->assertForbidden();
    }

    public function test_tagging_notifies_the_tagged_user_but_not_the_tagger(): void
    {
        $tagger = User::factory()->create();
        $friend = User::factory()->create();
        $mediaItem = $this->mediaItem();

        $response = $this->actingAs($tagger)->postJson('/api/log-entries', [
            'media_item_id' => $mediaItem->id,
            'logged_date' => '2026-01-01',
            'tagged_user_ids' => [$friend->id, $tagger->id],
        ])->assertCreated();

        $logEntryId = $response->json('data.id');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $friend->id,
            'type' => 'tagged',
        ]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $tagger->id]);

        $notification = Notification::where('user_id', $friend->id)->firstOrFail();
        $this->assertSame($logEntryId, $notification->data['log_entry_id']);
        $this->assertSame($tagger->id, $notification->data['from_user_id']);
    }

    public function test_sending_a_friend_request_notifies_the_addressee(): void
    {
        $requester = User::factory()->create();
        $addressee = User::factory()->create();

        $this->actingAs($requester)->postJson('/api/friendships', ['addressee_id' => $addressee->id])->assertCreated();

        $notification = Notification::where('user_id', $addressee->id)->firstOrFail();
        $this->assertSame('friend_request', $notification->type->value);
        $this->assertSame($requester->id, $notification->data['from_user_id']);
    }

    public function test_accepting_a_friend_request_notifies_the_original_requester(): void
    {
        $requester = User::factory()->create();
        $addressee = User::factory()->create();
        $friendship = Friendship::create([
            'requester_id' => $requester->id,
            'addressee_id' => $addressee->id,
            'status' => 'pending',
        ]);

        $this->actingAs($addressee)->postJson("/api/friendships/{$friendship->id}/accept")->assertOk();

        $notification = Notification::where('user_id', $requester->id)->where('type', 'friend_accepted')->firstOrFail();
        $this->assertSame($addressee->id, $notification->data['from_user_id']);
    }

    /**
     * A crossing request (B requests A while A's request to B is still
     * pending) auto-accepts the existing row rather than creating a second
     * one — see FriendshipManager. The original requester should get a
     * friend_accepted notification, not a second friend_request.
     */
    public function test_a_crossing_friend_request_notifies_the_original_requester_as_accepted(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->postJson('/api/friendships', ['addressee_id' => $b->id])->assertCreated();
        $this->actingAs($b)->postJson('/api/friendships', ['addressee_id' => $a->id])->assertOk();

        // a's first request produced one friend_request notification (for b);
        // the crossing second call should produce exactly one more
        // (friend_accepted, for a), not a second friend_request for b.
        $this->assertDatabaseHas('notifications', ['user_id' => $a->id, 'type' => 'friend_accepted']);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseMissing('notifications', ['user_id' => $b->id, 'type' => 'friend_accepted']);
    }

    public function test_a_top_level_comment_notifies_the_review_owner_only(): void
    {
        $owner = User::factory()->create();
        $commenter = User::factory()->create();
        $entry = $this->logEntry($owner);

        $this->actingAs($commenter)->postJson("/api/log-entries/{$entry->id}/comments", [
            'body' => 'Nice!',
        ])->assertCreated();

        $notification = Notification::where('user_id', $owner->id)->firstOrFail();
        $this->assertSame('comment_on_review', $notification->type->value);
        $this->assertSame($commenter->id, $notification->data['from_user_id']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $commenter->id]);
    }

    public function test_commenting_on_your_own_review_does_not_self_notify(): void
    {
        $owner = User::factory()->create();
        $entry = $this->logEntry($owner);

        $this->actingAs($owner)->postJson("/api/log-entries/{$entry->id}/comments", [
            'body' => 'Noting my own thoughts here.',
        ])->assertCreated();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_a_reply_notifies_only_the_parent_comments_author_not_the_review_owner_again(): void
    {
        $owner = User::factory()->create();
        $firstCommenter = User::factory()->create();
        $replier = User::factory()->create();
        $entry = $this->logEntry($owner);
        $parent = Comment::create(['log_entry_id' => $entry->id, 'user_id' => $firstCommenter->id, 'body' => 'Root comment']);

        $this->actingAs($replier)->postJson("/api/log-entries/{$entry->id}/comments", [
            'body' => 'A reply',
            'parent_comment_id' => $parent->id,
        ])->assertCreated();

        $this->assertDatabaseHas('notifications', ['user_id' => $firstCommenter->id, 'type' => 'comment_reply']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $owner->id]);
    }

    public function test_replying_to_your_own_comment_does_not_self_notify(): void
    {
        $owner = User::factory()->create();
        $entry = $this->logEntry($owner);
        $parent = Comment::create(['log_entry_id' => $entry->id, 'user_id' => $owner->id, 'body' => 'Root comment']);

        $this->actingAs($owner)->postJson("/api/log-entries/{$entry->id}/comments", [
            'body' => 'Following up on my own comment',
            'parent_comment_id' => $parent->id,
        ])->assertCreated();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_a_reply_to_a_soft_deleted_comment_still_notifies_its_author(): void
    {
        $owner = User::factory()->create();
        $originalAuthor = User::factory()->create();
        $replier = User::factory()->create();
        $entry = $this->logEntry($owner);
        $parent = Comment::create(['log_entry_id' => $entry->id, 'user_id' => $originalAuthor->id, 'body' => 'Will be deleted']);
        $parent->delete();

        $this->actingAs($replier)->postJson("/api/log-entries/{$entry->id}/comments", [
            'body' => 'Replying anyway',
            'parent_comment_id' => $parent->id,
        ])->assertCreated();

        $this->assertDatabaseHas('notifications', ['user_id' => $originalAuthor->id, 'type' => 'comment_reply']);
    }

    /**
     * A queued listener can run well after the HTTP request that created the
     * Comment: the owner may have soft-deleted their Shelf Entry (removed
     * the item) in between, which doesn't touch the Log Entry/Comment at
     * all. The listener still has to resolve the owner via a withTrashed()
     * lookup rather than crash on a null userMedia.
     */
    public function test_a_comment_notification_still_resolves_the_owner_after_the_shelf_entry_is_removed(): void
    {
        $owner = User::factory()->create();
        $commenter = User::factory()->create();
        $entry = $this->logEntry($owner);
        $comment = Comment::create(['log_entry_id' => $entry->id, 'user_id' => $commenter->id, 'body' => 'Nice!']);
        $entry->userMedia->delete();

        (new CreateCommentNotification)->handle(new CommentPosted($comment->fresh()));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type' => 'comment_on_review',
        ]);
    }

    public function test_a_reaction_notifies_the_review_owner(): void
    {
        $owner = User::factory()->create();
        $reactor = User::factory()->create();
        $entry = $this->logEntry($owner);

        $this->actingAs($reactor)->postJson("/api/log-entries/{$entry->id}/reactions", ['emoji' => 'heart'])->assertCreated();

        $notification = Notification::where('user_id', $owner->id)->firstOrFail();
        $this->assertSame('reaction', $notification->type->value);
        $this->assertSame($reactor->id, $notification->data['from_user_id']);
        $this->assertSame('heart', $notification->data['emoji']);
    }

    public function test_reacting_to_your_own_review_does_not_self_notify(): void
    {
        $owner = User::factory()->create();
        $entry = $this->logEntry($owner);

        $this->actingAs($owner)->postJson("/api/log-entries/{$entry->id}/reactions", ['emoji' => 'heart'])->assertCreated();

        $this->assertDatabaseCount('notifications', 0);
    }

    /** Same race as comments — see the comment-notification test above. */
    public function test_a_reaction_notification_still_resolves_the_owner_after_the_shelf_entry_is_removed(): void
    {
        $owner = User::factory()->create();
        $reactor = User::factory()->create();
        $entry = $this->logEntry($owner);
        $reaction = Reaction::create(['log_entry_id' => $entry->id, 'user_id' => $reactor->id, 'emoji' => 'heart']);
        $entry->userMedia->delete();

        (new CreateReactionNotification)->handle(new ReactionAdded($reaction->fresh()));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type' => 'reaction',
        ]);
    }

    public function test_repeating_the_same_reaction_does_not_create_a_second_notification(): void
    {
        $owner = User::factory()->create();
        $reactor = User::factory()->create();
        $entry = $this->logEntry($owner);

        $this->actingAs($reactor)->postJson("/api/log-entries/{$entry->id}/reactions", ['emoji' => 'heart'])->assertCreated();
        $this->actingAs($reactor)->postJson("/api/log-entries/{$entry->id}/reactions", ['emoji' => 'heart'])->assertCreated();

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_index_returns_the_callers_notifications_most_recent_first_with_actor_embedded(): void
    {
        $recipient = User::factory()->create();
        $actor = User::factory()->create();
        $older = Notification::create(['user_id' => $recipient->id, 'type' => 'tagged', 'data' => ['log_entry_id' => 1, 'from_user_id' => $actor->id]]);
        $older->forceFill(['created_at' => now()->subHour()])->save();
        $newer = Notification::create(['user_id' => $recipient->id, 'type' => 'reaction', 'data' => ['log_entry_id' => 1, 'from_user_id' => $actor->id, 'emoji' => 'fire']]);

        $response = $this->actingAs($recipient)->getJson('/api/notifications')->assertOk();

        $this->assertSame([$newer->id, $older->id], collect($response->json('data'))->pluck('id')->all());
        $response->assertJsonPath('data.0.actor.id', $actor->id);
        $this->assertArrayNotHasKey('relationship_status', $response->json('data.0.actor'));
    }

    public function test_index_does_not_return_another_users_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Notification::create(['user_id' => $other->id, 'type' => 'tagged', 'data' => ['log_entry_id' => 1, 'from_user_id' => $other->id]]);

        $response = $this->actingAs($user)->getJson('/api/notifications')->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    public function test_unread_count_only_counts_unread_notifications_for_the_caller(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Notification::create(['user_id' => $user->id, 'type' => 'tagged', 'data' => ['log_entry_id' => 1, 'from_user_id' => $other->id]]);
        Notification::create(['user_id' => $user->id, 'type' => 'tagged', 'data' => ['log_entry_id' => 1, 'from_user_id' => $other->id], 'read_at' => now()]);
        Notification::create(['user_id' => $other->id, 'type' => 'tagged', 'data' => ['log_entry_id' => 1, 'from_user_id' => $user->id]]);

        $this->actingAs($user)->getJson('/api/notifications/unread-count')->assertOk()->assertJson(['count' => 1]);
    }

    public function test_mark_read_sets_read_at_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $notification = Notification::create(['user_id' => $user->id, 'type' => 'tagged', 'data' => ['log_entry_id' => 1, 'from_user_id' => $user->id]]);

        $this->actingAs($user)->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id);
        $firstReadAt = $notification->fresh()->read_at;
        $this->assertNotNull($firstReadAt);

        $this->actingAs($user)->patchJson("/api/notifications/{$notification->id}/read")->assertOk();
        $this->assertTrue($firstReadAt->eq($notification->fresh()->read_at));
    }

    public function test_mark_all_read_only_touches_the_callers_own_unread_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = Notification::create(['user_id' => $user->id, 'type' => 'tagged', 'data' => ['log_entry_id' => 1, 'from_user_id' => $other->id]]);
        $someoneElses = Notification::create(['user_id' => $other->id, 'type' => 'tagged', 'data' => ['log_entry_id' => 1, 'from_user_id' => $user->id]]);

        $this->actingAs($user)->patchJson('/api/notifications/read-all')->assertNoContent();

        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertNull($someoneElses->fresh()->read_at);
    }
}
