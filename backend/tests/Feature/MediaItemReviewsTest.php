<?php

namespace Tests\Feature;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Models\Friendship;
use App\Models\LogEntry;
use App\Models\MediaItem;
use App\Models\User;
use App\Models\UserMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaItemReviewsTest extends TestCase
{
    use RefreshDatabase;

    private function movie(): MediaItem
    {
        return MediaItem::create([
            'type' => MediaType::Movie,
            'source' => MediaSource::Tmdb,
            'external_id' => (string) fake()->unique()->randomNumber(6),
            'title' => 'Heat',
        ]);
    }

    private function log(User $user, MediaItem $item, array $attributes = []): LogEntry
    {
        $userMedia = UserMedia::firstOrCreate(
            ['user_id' => $user->id, 'media_item_id' => $item->id],
            ['status' => 'done'],
        );

        return LogEntry::create(array_merge([
            'user_media_id' => $userMedia->id,
            'logged_date' => now()->toDateString(),
        ], $attributes));
    }

    private function privateUser(): User
    {
        return User::factory()->create(['profile_visibility' => 'private']);
    }

    public function test_reviews_requires_authentication(): void
    {
        $this->getJson('/api/media-items/'.$this->movie()->id.'/reviews')->assertUnauthorized();
    }

    public function test_it_lists_reviewed_entries_from_public_members_with_the_average(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $item = $this->movie();

        $this->log($viewer, $item, ['rating' => 4.0, 'review' => 'Great']);
        $this->log($other, $item, ['rating' => 5.0, 'review' => 'Perfect']);
        $this->log($other, $item, ['rating' => 3.0]);

        $response = $this->actingAs($viewer)->getJson("/api/media-items/{$item->id}/reviews")->assertOk();

        $this->assertCount(2, $response->json('data'));
        $response->assertJsonPath('meta.rating_count', 2);
        // $other's newest rated entry (3.0) replaces the earlier 5.0: (4 + 3) / 2
        $response->assertJsonPath('meta.average_rating', 3.5);
        $this->assertSame('Great', collect($response->json('data'))->firstWhere('review', 'Great')['review']);
    }

    public function test_average_is_null_when_nobody_rated(): void
    {
        $viewer = User::factory()->create();
        $item = $this->movie();

        $this->log($viewer, $item, ['review' => 'Just thoughts']);

        $this->actingAs($viewer)->getJson("/api/media-items/{$item->id}/reviews")
            ->assertOk()
            ->assertJsonPath('meta.average_rating', null)
            ->assertJsonPath('meta.rating_count', 0);
    }

    public function test_entries_without_a_review_are_not_listed(): void
    {
        $viewer = User::factory()->create();
        $item = $this->movie();

        $this->log($viewer, $item, ['rating' => 4.0, 'review' => null]);
        $this->log($viewer, $item, ['rating' => 4.0, 'review' => '']);

        $this->actingAs($viewer)->getJson("/api/media-items/{$item->id}/reviews")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_friends_only_member_is_hidden_unless_a_friend(): void
    {
        $viewer = User::factory()->create();
        $stranger = $this->privateUser();
        $friend = $this->privateUser();
        Friendship::create([
            'requester_id' => $viewer->id,
            'addressee_id' => $friend->id,
            'status' => 'accepted',
            'responded_at' => now(),
        ]);
        $item = $this->movie();

        $this->log($stranger, $item, ['rating' => 1.0, 'review' => 'Hidden']);
        $this->log($friend, $item, ['rating' => 5.0, 'review' => 'Visible']);

        $response = $this->actingAs($viewer)->getJson("/api/media-items/{$item->id}/reviews")->assertOk();

        $this->assertSame(['Visible'], collect($response->json('data'))->pluck('review')->all());
        $response->assertJsonPath('meta.average_rating', 5);
        $response->assertJsonPath('meta.rating_count', 1);
    }

    public function test_episode_and_season_ratings_do_not_count_toward_the_show_average(): void
    {
        $viewer = User::factory()->create();
        $item = MediaItem::create([
            'type' => MediaType::TvShow,
            'source' => MediaSource::Tmdb,
            'external_id' => '999',
            'title' => 'Severance',
        ]);

        $this->log($viewer, $item, ['rating' => 5.0, 'season_number' => 1, 'review' => 'Season thoughts']);
        $this->log($viewer, $item, ['rating' => 2.0]);

        $this->actingAs($viewer)->getJson("/api/media-items/{$item->id}/reviews")
            ->assertOk()
            ->assertJsonPath('meta.average_rating', 2)
            ->assertJsonCount(1, 'data');
    }

    public function test_show_returns_a_public_members_entry_with_author_and_media_item(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $item = $this->movie();
        $entry = $this->log($author, $item, ['rating' => 4.5, 'review' => 'Loved it']);

        $this->actingAs($viewer)->getJson("/api/log-entries/{$entry->id}")
            ->assertOk()
            ->assertJsonPath('data.review', 'Loved it')
            ->assertJsonPath('data.user.id', $author->id)
            ->assertJsonPath('data.media_item.id', $item->id);
    }

    public function test_show_is_forbidden_for_a_friends_only_authors_entry_to_a_stranger(): void
    {
        $viewer = User::factory()->create();
        $author = $this->privateUser();
        $entry = $this->log($author, $this->movie(), ['review' => 'Private']);

        $this->actingAs($viewer)->getJson("/api/log-entries/{$entry->id}")->assertForbidden();
    }

    public function test_show_requires_authentication(): void
    {
        $entry = $this->log(User::factory()->create(), $this->movie());

        $this->getJson("/api/log-entries/{$entry->id}")->assertUnauthorized();
    }

    public function test_show_is_not_found_once_the_authors_shelf_entry_is_removed(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $entry = $this->log($author, $this->movie(), ['review' => 'Gone']);

        $entry->userMedia->delete();

        $this->actingAs($viewer)->getJson("/api/log-entries/{$entry->id}")->assertNotFound();
        $this->actingAs($viewer)->getJson("/api/log-entries/{$entry->id}/comments")->assertNotFound();
        $this->actingAs($viewer)->getJson("/api/log-entries/{$entry->id}/reactions")->assertNotFound();
    }

    public function test_entries_of_a_removed_shelf_item_do_not_count_toward_reviews_or_average(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $item = $this->movie();

        $this->log($viewer, $item, ['rating' => 4.0, 'review' => 'Kept']);
        $removed = $this->log($other, $item, ['rating' => 1.0, 'review' => 'Removed']);
        $removed->userMedia->delete();

        $response = $this->actingAs($viewer)->getJson("/api/media-items/{$item->id}/reviews")->assertOk();

        $this->assertSame(['Kept'], collect($response->json('data'))->pluck('review')->all());
        $response->assertJsonPath('meta.average_rating', 4);
        $response->assertJsonPath('meta.rating_count', 1);
    }
}
