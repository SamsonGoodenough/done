<?php

namespace Tests\Feature;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Models\MediaItem;
use App\Models\User;
use App\Models\UserMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogEntryControllerTest extends TestCase
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

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/log-entries', [])->assertUnauthorized();
    }

    public function test_logging_with_no_prior_shelf_entry_creates_one_as_completed(): void
    {
        $user = User::factory()->create();
        $mediaItem = $this->mediaItem();

        $response = $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $mediaItem->id,
            'logged_date' => '2026-01-01',
            'rating' => 4.5,
        ]);

        $response->assertCreated()->assertJsonPath('data.is_rewatch', false);
        $this->assertDatabaseHas('user_media', [
            'user_id' => $user->id,
            'media_item_id' => $mediaItem->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('log_entries', ['rating' => '4.5']);
    }

    public function test_logging_against_an_existing_shelf_entry_never_changes_its_status(): void
    {
        $user = User::factory()->create();
        $mediaItem = $this->mediaItem();
        $userMedia = UserMedia::create([
            'user_id' => $user->id,
            'media_item_id' => $mediaItem->id,
            'status' => 'dropped',
        ]);

        $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $mediaItem->id,
            'logged_date' => '2026-01-01',
        ])->assertCreated();

        $this->assertDatabaseHas('user_media', ['id' => $userMedia->id, 'status' => 'dropped']);
    }

    public function test_logging_restores_a_previously_removed_shelf_entry_as_completed(): void
    {
        $user = User::factory()->create();
        $mediaItem = $this->mediaItem();
        $userMedia = UserMedia::create([
            'user_id' => $user->id,
            'media_item_id' => $mediaItem->id,
            'status' => 'dropped',
        ]);
        $userMedia->delete();

        $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $mediaItem->id,
            'logged_date' => '2026-01-01',
        ])->assertCreated();

        $this->assertDatabaseCount('user_media', 1);
        $this->assertDatabaseHas('user_media', ['id' => $userMedia->id, 'status' => 'completed', 'deleted_at' => null]);
    }

    public function test_second_log_entry_for_the_same_item_is_flagged_as_a_rewatch(): void
    {
        $user = User::factory()->create();
        $mediaItem = $this->mediaItem();

        $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $mediaItem->id,
            'logged_date' => '2026-01-01',
        ])->assertCreated()->assertJsonPath('data.is_rewatch', false);

        $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $mediaItem->id,
            'logged_date' => '2026-02-01',
        ])->assertCreated()->assertJsonPath('data.is_rewatch', true);
    }

    public function test_is_rewatch_can_be_overridden_manually(): void
    {
        $user = User::factory()->create();
        $mediaItem = $this->mediaItem();

        $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $mediaItem->id,
            'logged_date' => '2026-01-01',
            'is_rewatch' => true,
        ])->assertCreated()->assertJsonPath('data.is_rewatch', true);
    }

    public function test_is_favourite_writes_through_to_the_shelf_entry(): void
    {
        $user = User::factory()->create();
        $mediaItem = $this->mediaItem();

        $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $mediaItem->id,
            'logged_date' => '2026-01-01',
            'is_favourite' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('user_media', [
            'user_id' => $user->id,
            'media_item_id' => $mediaItem->id,
            'is_favourite' => true,
        ]);
    }

    public function test_tagged_user_ids_attach_participants_including_the_author(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $mediaItem = $this->mediaItem();

        $response = $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $mediaItem->id,
            'logged_date' => '2026-01-01',
            'tagged_user_ids' => [$friend->id, $user->id],
        ]);

        $response->assertCreated();
        $logEntryId = $response->json('data.id');

        $this->assertDatabaseHas('log_entry_participants', ['log_entry_id' => $logEntryId, 'user_id' => $friend->id]);
        $this->assertDatabaseHas('log_entry_participants', ['log_entry_id' => $logEntryId, 'user_id' => $user->id]);
    }

    public function test_rating_must_be_in_half_star_steps(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $this->mediaItem()->id,
            'logged_date' => '2026-01-01',
            'rating' => 3.3,
        ])->assertUnprocessable()->assertJsonValidationErrors('rating');
    }

    public function test_logged_date_cannot_be_in_the_future(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $this->mediaItem()->id,
            'logged_date' => now()->addDay()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('logged_date');
    }

    public function test_media_item_must_exist_and_not_be_soft_deleted(): void
    {
        $user = User::factory()->create();
        $mediaItem = $this->mediaItem();
        $mediaItem->delete();

        $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $mediaItem->id,
            'logged_date' => '2026-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('media_item_id');
    }
}
