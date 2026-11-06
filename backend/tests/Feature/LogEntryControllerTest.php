<?php

namespace Tests\Feature;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Models\Episode;
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

    private function tvShow(): MediaItem
    {
        return MediaItem::create([
            'type' => MediaType::TvShow,
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

    public function test_episode_level_logging_stores_the_episode_id(): void
    {
        $user = User::factory()->create();
        $show = $this->tvShow();
        $episode = $show->episodes()->create(['season_number' => 1, 'episode_number' => 1, 'title' => 'Pilot']);

        $response = $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $show->id,
            'episode_id' => $episode->id,
            'logged_date' => '2026-01-01',
        ]);

        $response->assertCreated()->assertJsonPath('data.episode_id', $episode->id);
        $this->assertDatabaseHas('log_entries', ['episode_id' => $episode->id, 'season_number' => null]);
    }

    public function test_season_level_logging_stores_the_season_number(): void
    {
        $user = User::factory()->create();
        $show = $this->tvShow();
        $show->episodes()->create(['season_number' => 1, 'episode_number' => 1, 'title' => 'Pilot']);

        $response = $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $show->id,
            'season_number' => 1,
            'logged_date' => '2026-01-01',
        ]);

        $response->assertCreated()->assertJsonPath('data.season_number', 1);
        $this->assertDatabaseHas('log_entries', ['episode_id' => null, 'season_number' => 1]);
    }

    public function test_episode_id_and_season_number_are_mutually_exclusive(): void
    {
        $user = User::factory()->create();
        $show = $this->tvShow();
        $episode = $show->episodes()->create(['season_number' => 1, 'episode_number' => 1, 'title' => 'Pilot']);

        $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $show->id,
            'episode_id' => $episode->id,
            'season_number' => 1,
            'logged_date' => '2026-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('season_number');
    }

    public function test_episode_id_must_belong_to_the_show_being_logged(): void
    {
        $user = User::factory()->create();
        $show = $this->tvShow();
        $otherShow = $this->tvShow();
        $episode = $otherShow->episodes()->create(['season_number' => 1, 'episode_number' => 1, 'title' => 'Pilot']);

        $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $show->id,
            'episode_id' => $episode->id,
            'logged_date' => '2026-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('episode_id');
    }

    public function test_season_number_must_already_be_synced_for_the_show_being_logged(): void
    {
        $user = User::factory()->create();
        $show = $this->tvShow();

        $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $show->id,
            'season_number' => 1,
            'logged_date' => '2026-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('season_number');
    }

    public function test_episode_and_season_granularity_are_rejected_for_a_non_tv_show_media_item(): void
    {
        $user = User::factory()->create();
        $movie = $this->mediaItem();

        $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $movie->id,
            'season_number' => 1,
            'logged_date' => '2026-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('season_number');
    }

    public function test_rewatch_detection_is_scoped_to_the_same_episode(): void
    {
        $user = User::factory()->create();
        $show = $this->tvShow();
        $episodeOne = $show->episodes()->create(['season_number' => 1, 'episode_number' => 1, 'title' => 'Pilot']);
        $episodeTwo = $show->episodes()->create(['season_number' => 1, 'episode_number' => 2, 'title' => 'Episode 2']);

        $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $show->id,
            'episode_id' => $episodeOne->id,
            'logged_date' => '2026-01-01',
        ])->assertCreated()->assertJsonPath('data.is_rewatch', false);

        // A different episode of the same show is a different target, not a rewatch.
        $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $show->id,
            'episode_id' => $episodeTwo->id,
            'logged_date' => '2026-01-02',
        ])->assertCreated()->assertJsonPath('data.is_rewatch', false);

        // Logging episode one again is a rewatch of that same target.
        $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $show->id,
            'episode_id' => $episodeOne->id,
            'logged_date' => '2026-02-01',
        ])->assertCreated()->assertJsonPath('data.is_rewatch', true);
    }

    public function test_a_whole_show_log_entry_is_not_a_rewatch_relative_to_a_season_level_entry(): void
    {
        $user = User::factory()->create();
        $show = $this->tvShow();
        $show->episodes()->create(['season_number' => 1, 'episode_number' => 1, 'title' => 'Pilot']);

        $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $show->id,
            'season_number' => 1,
            'logged_date' => '2026-01-01',
        ])->assertCreated()->assertJsonPath('data.is_rewatch', false);

        // Finishing the whole show is a different Logging Target from season 1,
        // even though it necessarily re-covers that ground.
        $this->actingAs($user)->postJson('/api/log-entries', [
            'media_item_id' => $show->id,
            'logged_date' => '2026-02-01',
        ])->assertCreated()->assertJsonPath('data.is_rewatch', false);
    }
}
