<?php

namespace Tests\Feature;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Models\MediaItem;
use App\Models\User;
use App\Models\UserMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserMediaControllerTest extends TestCase
{
    use RefreshDatabase;

    private function mediaItem(MediaType $type = MediaType::Movie): MediaItem
    {
        return MediaItem::create([
            'type' => $type,
            'source' => MediaSource::Tmdb,
            'external_id' => (string) fake()->unique()->randomNumber(6),
            'title' => fake()->sentence(3),
        ]);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/user-media')->assertUnauthorized();
    }

    public function test_store_adds_an_item_to_the_shelf(): void
    {
        $user = User::factory()->create();
        $mediaItem = $this->mediaItem();

        $response = $this->actingAs($user)->postJson('/api/user-media', [
            'media_item_id' => $mediaItem->id,
            'status' => 'planned',
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'planned');
        $this->assertDatabaseHas('user_media', [
            'user_id' => $user->id,
            'media_item_id' => $mediaItem->id,
            'status' => 'planned',
        ]);
    }

    public function test_store_rejects_done_status(): void
    {
        $user = User::factory()->create();
        $mediaItem = $this->mediaItem();

        $this->actingAs($user)->postJson('/api/user-media', [
            'media_item_id' => $mediaItem->id,
            'status' => 'done',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_removing_then_re_adding_restores_the_soft_deleted_row_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        $mediaItem = $this->mediaItem();

        $first = $this->actingAs($user)->postJson('/api/user-media', [
            'media_item_id' => $mediaItem->id,
            'status' => 'planned',
        ])->json('data.id');

        $this->actingAs($user)->deleteJson("/api/user-media/{$first}")->assertNoContent();
        $this->assertSoftDeleted('user_media', ['id' => $first]);

        $second = $this->actingAs($user)->postJson('/api/user-media', [
            'media_item_id' => $mediaItem->id,
            'status' => 'in_progress',
        ])->assertCreated()->json('data.id');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('user_media', 1);
        $this->assertDatabaseHas('user_media', ['id' => $first, 'status' => 'in_progress', 'deleted_at' => null]);
    }

    public function test_reposting_to_an_already_active_shelf_entry_leaves_it_unchanged(): void
    {
        $user = User::factory()->create();
        $mediaItem = $this->mediaItem();

        $userMedia = UserMedia::create([
            'user_id' => $user->id,
            'media_item_id' => $mediaItem->id,
            'status' => 'done',
            'is_favourite' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/user-media', [
            'media_item_id' => $mediaItem->id,
            'status' => 'planned',
        ]);

        $response->assertOk()->assertJsonPath('data.id', $userMedia->id);
        $this->assertDatabaseHas('user_media', [
            'id' => $userMedia->id,
            'status' => 'done',
            'is_favourite' => true,
        ]);
    }

    public function test_update_changes_status_without_ordering_constraints(): void
    {
        $user = User::factory()->create();
        $userMedia = UserMedia::create([
            'user_id' => $user->id,
            'media_item_id' => $this->mediaItem()->id,
            'status' => 'planned',
        ]);

        $this->actingAs($user)->patchJson("/api/user-media/{$userMedia->id}", [
            'status' => 'dropped',
        ])->assertOk()->assertJsonPath('data.status', 'dropped');
    }

    public function test_update_rejects_done_status_when_no_log_entries_exist(): void
    {
        $user = User::factory()->create();
        $userMedia = UserMedia::create([
            'user_id' => $user->id,
            'media_item_id' => $this->mediaItem()->id,
            'status' => 'planned',
        ]);

        $this->actingAs($user)->patchJson("/api/user-media/{$userMedia->id}", [
            'status' => 'done',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_update_allows_done_status_once_a_log_entry_exists(): void
    {
        $user = User::factory()->create();
        $userMedia = UserMedia::create([
            'user_id' => $user->id,
            'media_item_id' => $this->mediaItem()->id,
            'status' => 'planned',
        ]);
        $userMedia->logEntries()->create(['logged_date' => '2026-01-01', 'rating' => 4.0]);

        $this->actingAs($user)->patchJson("/api/user-media/{$userMedia->id}", [
            'status' => 'done',
        ])->assertOk()->assertJsonPath('data.status', 'done');

        $this->assertDatabaseHas('user_media', ['id' => $userMedia->id, 'status' => 'done']);
    }

    public function test_update_toggles_favourite_independently_of_status(): void
    {
        $user = User::factory()->create();
        $userMedia = UserMedia::create([
            'user_id' => $user->id,
            'media_item_id' => $this->mediaItem()->id,
            'status' => 'planned',
        ]);

        $this->actingAs($user)->patchJson("/api/user-media/{$userMedia->id}", [
            'is_favourite' => true,
        ])->assertOk()->assertJsonPath('data.is_favourite', true)->assertJsonPath('data.status', 'planned');
    }

    public function test_a_user_cannot_update_another_users_shelf_entry(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $userMedia = UserMedia::create([
            'user_id' => $owner->id,
            'media_item_id' => $this->mediaItem()->id,
            'status' => 'planned',
        ]);

        $this->actingAs($intruder)
            ->patchJson("/api/user-media/{$userMedia->id}", ['status' => 'dropped'])
            ->assertForbidden();
    }

    public function test_a_user_cannot_delete_another_users_shelf_entry(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $userMedia = UserMedia::create([
            'user_id' => $owner->id,
            'media_item_id' => $this->mediaItem()->id,
            'status' => 'planned',
        ]);

        $this->actingAs($intruder)->deleteJson("/api/user-media/{$userMedia->id}")->assertForbidden();
    }

    public function test_index_only_returns_the_callers_own_shelf(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        UserMedia::create(['user_id' => $user->id, 'media_item_id' => $this->mediaItem()->id, 'status' => 'planned']);
        UserMedia::create(['user_id' => $other->id, 'media_item_id' => $this->mediaItem()->id, 'status' => 'planned']);

        $response = $this->actingAs($user)->getJson('/api/user-media')->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_filters_by_status_type_and_favourite(): void
    {
        $user = User::factory()->create();

        UserMedia::create([
            'user_id' => $user->id,
            'media_item_id' => $this->mediaItem(MediaType::Movie)->id,
            'status' => 'planned',
            'is_favourite' => true,
        ]);
        UserMedia::create([
            'user_id' => $user->id,
            'media_item_id' => $this->mediaItem(MediaType::Game)->id,
            'status' => 'dropped',
            'is_favourite' => false,
        ]);

        $byStatus = $this->actingAs($user)->getJson('/api/user-media?status=planned')->assertOk();
        $this->assertCount(1, $byStatus->json('data'));

        $byType = $this->actingAs($user)->getJson('/api/user-media?type=game')->assertOk();
        $this->assertCount(1, $byType->json('data'));

        $byFavourite = $this->actingAs($user)->getJson('/api/user-media?favourite=1')->assertOk();
        $this->assertCount(1, $byFavourite->json('data'));
    }

    public function test_index_includes_the_latest_rating_from_log_entries(): void
    {
        $user = User::factory()->create();
        $userMedia = UserMedia::create([
            'user_id' => $user->id,
            'media_item_id' => $this->mediaItem()->id,
            'status' => 'done',
        ]);

        // Older rated entry, newer rated entry, and an unrated one newer than
        // both: the stamp shows the newest entry that actually has a rating.
        $userMedia->logEntries()->create(['logged_date' => '2026-01-01', 'rating' => 3.0]);
        $userMedia->logEntries()->create(['logged_date' => '2026-02-01', 'rating' => 4.5]);
        $userMedia->logEntries()->create(['logged_date' => '2026-03-01', 'rating' => null]);

        $unrated = UserMedia::create([
            'user_id' => $user->id,
            'media_item_id' => $this->mediaItem()->id,
            'status' => 'planned',
        ]);

        $response = $this->actingAs($user)->getJson('/api/user-media')->assertOk();

        $byId = collect($response->json('data'))->keyBy('id');
        $this->assertSame(4.5, $byId[$userMedia->id]['latest_rating']);
        $this->assertNull($byId[$unrated->id]['latest_rating']);
    }
}
