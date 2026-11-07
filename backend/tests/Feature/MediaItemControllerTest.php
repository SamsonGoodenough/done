<?php

namespace Tests\Feature;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\LogEntry;
use App\Models\MediaItem;
use App\Models\Reaction;
use App\Models\User;
use App\Models\UserMedia;
use App\Services\MediaSources\EpisodeData;
use App\Services\MediaSources\Exceptions\MediaNotFoundException;
use App\Services\MediaSources\Exceptions\MediaSourceUnavailableException;
use App\Services\MediaSources\MediaDetails;
use App\Services\MediaSources\MediaSourceClient;
use App\Services\MediaSources\Providers\TmdbClient;
use App\Services\MediaSources\SearchResult;
use App\Services\MediaSources\ShowSummary;
use App\Services\MediaSources\SupportsEpisodeSync;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaItemControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    private function manager(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Manager->value);

        return $user;
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Member->value);

        return $user;
    }

    /** Binds a fake client in place of the real TmdbClient for Movie/TvShow dispatch. */
    private function fakeMovieClient(MediaSourceClient $client): void
    {
        $this->app->instance(TmdbClient::class, $client);
    }

    public function test_search_requires_authentication(): void
    {
        $this->getJson('/api/media-items/search?type=movie&query=matrix')
            ->assertUnauthorized();
    }

    public function test_search_flags_results_already_in_the_catalog(): void
    {
        $existing = MediaItem::create([
            'type' => MediaType::Movie,
            'source' => MediaSource::Tmdb,
            'external_id' => '603',
            'title' => 'The Matrix',
        ]);

        $this->fakeMovieClient(new class implements MediaSourceClient
        {
            public function search(string $query, MediaType $type): array
            {
                return [
                    new SearchResult(MediaType::Movie, MediaSource::Tmdb, '603', 'The Matrix', '1999-03-31', null, null),
                    new SearchResult(MediaType::Movie, MediaSource::Tmdb, '604', 'The Matrix Reloaded', '2003-05-15', null, null),
                ];
            }

            public function details(string $externalId, MediaType $type): MediaDetails
            {
                throw new \RuntimeException('not used in this test');
            }
        });

        $response = $this->actingAs($this->member())
            ->getJson('/api/media-items/search?type=movie&query=matrix')
            ->assertOk();

        $response->assertJsonPath('data.0.external_id', '603');
        $response->assertJsonPath('data.0.media_item_id', $existing->id);
        $response->assertJsonPath('data.1.external_id', '604');
        $response->assertJsonPath('data.1.media_item_id', null);
    }

    public function test_store_from_source_catalogs_a_new_item(): void
    {
        $this->fakeMovieClient(new class implements MediaSourceClient
        {
            public function search(string $query, MediaType $type): array
            {
                return [];
            }

            public function details(string $externalId, MediaType $type): MediaDetails
            {
                return new MediaDetails(
                    MediaType::Movie,
                    MediaSource::Tmdb,
                    $externalId,
                    'The Matrix',
                    '1999-03-31',
                    'https://image.tmdb.org/t/p/w500/poster.jpg',
                    'A hacker discovers reality is a simulation.',
                    ['runtime' => 136, 'genres' => ['Action', 'Science Fiction']],
                );
            }
        });

        $response = $this->actingAs($this->member())->postJson('/api/media-items', [
            'type' => 'movie',
            'external_id' => '603',
        ]);

        $response->assertCreated()->assertJsonPath('data.title', 'The Matrix');
        $this->assertDatabaseHas('media_items', ['source' => 'tmdb', 'external_id' => '603']);
    }

    public function test_store_from_source_returns_an_already_cataloged_item_without_duplicating(): void
    {
        $existing = MediaItem::create([
            'type' => MediaType::Movie,
            'source' => MediaSource::Tmdb,
            'external_id' => '603',
            'title' => 'The Matrix',
        ]);

        $this->fakeMovieClient(new class implements MediaSourceClient
        {
            public function search(string $query, MediaType $type): array
            {
                return [];
            }

            public function details(string $externalId, MediaType $type): MediaDetails
            {
                return new MediaDetails(MediaType::Movie, MediaSource::Tmdb, $externalId, 'The Matrix', null, null, null, []);
            }
        });

        $response = $this->actingAs($this->member())->postJson('/api/media-items', [
            'type' => 'movie',
            'external_id' => '603',
        ]);

        $response->assertOk()->assertJsonPath('data.id', $existing->id);
        $this->assertDatabaseCount('media_items', 1);
    }

    public function test_store_from_source_does_not_collide_across_types_sharing_the_same_external_id(): void
    {
        // TMDB assigns movie IDs and TV IDs from independent numbering
        // spaces, so the same numeric id can legitimately belong to an
        // unrelated movie and TV show under the single 'tmdb' source.
        $movie = MediaItem::create([
            'type' => MediaType::Movie,
            'source' => MediaSource::Tmdb,
            'external_id' => '603',
            'title' => 'A Movie',
        ]);

        $this->fakeMovieClient(new class implements MediaSourceClient
        {
            public function search(string $query, MediaType $type): array
            {
                return [];
            }

            public function details(string $externalId, MediaType $type): MediaDetails
            {
                return new MediaDetails(MediaType::TvShow, MediaSource::Tmdb, $externalId, 'An Unrelated TV Show', null, null, null, []);
            }
        });

        $response = $this->actingAs($this->member())->postJson('/api/media-items', [
            'type' => 'tv_show',
            'external_id' => '603',
        ]);

        $response->assertCreated()->assertJsonPath('data.title', 'An Unrelated TV Show');
        $this->assertNotEquals($movie->id, $response->json('data.id'));
        $this->assertDatabaseCount('media_items', 2);
    }

    public function test_store_from_source_maps_not_found_to_404(): void
    {
        $this->fakeMovieClient(new class implements MediaSourceClient
        {
            public function search(string $query, MediaType $type): array
            {
                return [];
            }

            public function details(string $externalId, MediaType $type): MediaDetails
            {
                throw MediaNotFoundException::forExternalId(MediaSource::Tmdb, $externalId);
            }
        });

        $this->actingAs($this->member())
            ->postJson('/api/media-items', ['type' => 'movie', 'external_id' => 'does-not-exist'])
            ->assertNotFound();
    }

    public function test_store_from_source_maps_unavailable_to_503(): void
    {
        $this->fakeMovieClient(new class implements MediaSourceClient
        {
            public function search(string $query, MediaType $type): array
            {
                return [];
            }

            public function details(string $externalId, MediaType $type): MediaDetails
            {
                throw MediaSourceUnavailableException::for(MediaSource::Tmdb);
            }
        });

        $this->actingAs($this->member())
            ->postJson('/api/media-items', ['type' => 'movie', 'external_id' => '603'])
            ->assertStatus(503);
    }

    public function test_member_cannot_manually_add_a_media_item(): void
    {
        $this->actingAs($this->member())->postJson('/api/media-items/manual', [
            'type' => 'book',
            'title' => 'An Obscure Zine',
        ])->assertForbidden();
    }

    public function test_manager_can_manually_add_a_media_item_with_minimal_fields(): void
    {
        $response = $this->actingAs($this->manager())->postJson('/api/media-items/manual', [
            'type' => 'book',
            'title' => 'An Obscure Zine',
        ]);

        $response->assertCreated()->assertJsonPath('data.title', 'An Obscure Zine');
        $this->assertDatabaseHas('media_items', [
            'title' => 'An Obscure Zine',
            'source' => 'manual',
            'external_id' => null,
        ]);
    }

    public function test_admin_can_manually_add_a_media_item(): void
    {
        $this->actingAs($this->admin())->postJson('/api/media-items/manual', [
            'type' => 'game',
            'title' => 'An Obscure Prototype',
        ])->assertCreated();
    }

    public function test_manual_entry_requires_a_title(): void
    {
        $this->actingAs($this->manager())
            ->postJson('/api/media-items/manual', ['type' => 'book'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_show_returns_the_item(): void
    {
        $mediaItem = MediaItem::create([
            'type' => MediaType::Movie,
            'source' => MediaSource::Tmdb,
            'external_id' => '603',
            'title' => 'The Matrix',
        ]);

        $this->actingAs($this->member())
            ->getJson("/api/media-items/{$mediaItem->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'The Matrix');
    }

    public function test_show_404s_for_a_soft_deleted_item(): void
    {
        $mediaItem = MediaItem::create([
            'type' => MediaType::Movie,
            'source' => MediaSource::Tmdb,
            'external_id' => '603',
            'title' => 'The Matrix',
        ]);
        $mediaItem->delete();

        $this->actingAs($this->member())
            ->getJson("/api/media-items/{$mediaItem->id}")
            ->assertNotFound();
    }

    public function test_log_entries_only_returns_the_callers_own_diary_for_that_item(): void
    {
        $user = $this->member();
        $other = $this->member();
        $mediaItem = MediaItem::create([
            'type' => MediaType::Movie,
            'source' => MediaSource::Tmdb,
            'external_id' => '603',
            'title' => 'The Matrix',
        ]);

        $mine = UserMedia::create(['user_id' => $user->id, 'media_item_id' => $mediaItem->id, 'status' => 'completed']);
        LogEntry::create(['user_media_id' => $mine->id, 'logged_date' => '2026-01-01']);

        $theirs = UserMedia::create(['user_id' => $other->id, 'media_item_id' => $mediaItem->id, 'status' => 'completed']);
        LogEntry::create(['user_media_id' => $theirs->id, 'logged_date' => '2026-01-01']);

        $response = $this->actingAs($user)
            ->getJson("/api/media-items/{$mediaItem->id}/log-entries")
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    /**
     * See docs/adr/0006-comment-reaction-summaries-embedded-in-existing-endpoints.md:
     * this endpoint's LogEntryResource carries a comment_count (including
     * soft-deleted "[deleted]" comments) and a condensed reaction summary,
     * to avoid the feed/diary UI needing a side call per entry.
     */
    public function test_log_entries_includes_comment_count_and_the_viewers_own_reactions(): void
    {
        $user = $this->member();
        $other = $this->member();
        $mediaItem = MediaItem::create([
            'type' => MediaType::Movie,
            'source' => MediaSource::Tmdb,
            'external_id' => '603',
            'title' => 'The Matrix',
        ]);

        $userMedia = UserMedia::create(['user_id' => $user->id, 'media_item_id' => $mediaItem->id, 'status' => 'completed']);
        $entry = LogEntry::create(['user_media_id' => $userMedia->id, 'logged_date' => '2026-01-01', 'review' => 'Loved it']);

        $deletedComment = Comment::create(['log_entry_id' => $entry->id, 'user_id' => $other->id, 'body' => 'Nice!']);
        $deletedComment->delete();
        Comment::create(['log_entry_id' => $entry->id, 'user_id' => $other->id, 'body' => 'Still here']);
        Reaction::create(['log_entry_id' => $entry->id, 'user_id' => $user->id, 'emoji' => 'heart']);
        Reaction::create(['log_entry_id' => $entry->id, 'user_id' => $other->id, 'emoji' => 'heart']);

        $response = $this->actingAs($user)
            ->getJson("/api/media-items/{$mediaItem->id}/log-entries")
            ->assertOk();

        $response->assertJsonPath('data.0.comment_count', 2)
            ->assertJsonPath('data.0.reaction_summary.count', 2)
            ->assertJsonPath('data.0.reaction_summary.viewer_reactions', ['heart']);
    }

    public function test_episodes_requires_authentication(): void
    {
        $show = MediaItem::create([
            'type' => MediaType::TvShow,
            'source' => MediaSource::Tmdb,
            'external_id' => '1399',
            'title' => 'Game of Thrones',
        ]);

        $this->getJson("/api/media-items/{$show->id}/episodes")->assertUnauthorized();
    }

    public function test_episodes_rejects_a_non_tv_show_media_item(): void
    {
        $movie = MediaItem::create([
            'type' => MediaType::Movie,
            'source' => MediaSource::Tmdb,
            'external_id' => '603',
            'title' => 'The Matrix',
        ]);

        $this->actingAs($this->member())
            ->getJson("/api/media-items/{$movie->id}/episodes")
            ->assertStatus(422);
    }

    public function test_episodes_syncs_and_returns_the_shows_episodes(): void
    {
        $show = MediaItem::create([
            'type' => MediaType::TvShow,
            'source' => MediaSource::Tmdb,
            'external_id' => '1399',
            'title' => 'Game of Thrones',
            'metadata' => ['status' => 'Returning Series'],
        ]);

        $this->fakeMovieClient(new class implements MediaSourceClient, SupportsEpisodeSync
        {
            public function search(string $query, MediaType $type): array
            {
                return [];
            }

            public function details(string $externalId, MediaType $type): MediaDetails
            {
                throw new \RuntimeException('not used in this test');
            }

            public function showSummary(string $externalId): ShowSummary
            {
                return new ShowSummary('Ended', [1]);
            }

            public function episodesForSeason(string $externalId, int $seasonNumber): array
            {
                return [new EpisodeData(1, 1, 'Winter Is Coming', '2011-04-17')];
            }
        });

        $response = $this->actingAs($this->member())
            ->getJson("/api/media-items/{$show->id}/episodes")
            ->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Winter Is Coming');
        $response->assertJsonPath('data.0.season_number', 1);
        $this->assertDatabaseHas('episodes', ['media_item_id' => $show->id, 'title' => 'Winter Is Coming']);
    }
}
