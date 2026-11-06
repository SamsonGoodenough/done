<?php

namespace Tests\Unit\Services\Episodes;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Models\MediaItem;
use App\Services\Episodes\EpisodeSyncer;
use App\Services\MediaSources\EpisodeData;
use App\Services\MediaSources\Exceptions\MediaSourceUnavailableException;
use App\Services\MediaSources\MediaDetails;
use App\Services\MediaSources\MediaSourceClient;
use App\Services\MediaSources\Providers\TmdbClient;
use App\Services\MediaSources\ShowSummary;
use App\Services\MediaSources\SupportsEpisodeSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class EpisodeSyncerTest extends TestCase
{
    use RefreshDatabase;

    private function show(array $metadata = []): MediaItem
    {
        return MediaItem::create([
            'type' => MediaType::TvShow,
            'source' => MediaSource::Tmdb,
            'external_id' => '1399',
            'title' => 'Game of Thrones',
            'metadata' => $metadata,
        ]);
    }

    /** Binds a fake client in place of the real TmdbClient. */
    private function fakeClient(object $client): void
    {
        $this->app->instance(TmdbClient::class, $client);
    }

    private function syncer(): EpisodeSyncer
    {
        return $this->app->make(EpisodeSyncer::class);
    }

    public function test_first_sync_persists_every_season_returned_by_the_source(): void
    {
        $show = $this->show(['status' => 'Returning Series']);

        $this->fakeClient(new class implements MediaSourceClient, SupportsEpisodeSync
        {
            public function search(string $query, MediaType $type): array
            {
                return [];
            }

            public function details(string $externalId, MediaType $type): MediaDetails
            {
                throw new RuntimeException('not used in this test');
            }

            public function showSummary(string $externalId): ShowSummary
            {
                return new ShowSummary('Returning Series', [1, 2]);
            }

            public function episodesForSeason(string $externalId, int $seasonNumber): array
            {
                return [
                    new EpisodeData($seasonNumber, 1, "S{$seasonNumber}E1", '2011-01-01'),
                    new EpisodeData($seasonNumber, 2, "S{$seasonNumber}E2", '2011-01-08'),
                ];
            }
        });

        $episodes = $this->syncer()->episodesFor($show);

        $this->assertCount(4, $episodes);
        $this->assertDatabaseHas('episodes', ['media_item_id' => $show->id, 'season_number' => 1, 'episode_number' => 1, 'title' => 'S1E1']);
        $this->assertDatabaseHas('episodes', ['media_item_id' => $show->id, 'season_number' => 2, 'episode_number' => 2, 'title' => 'S2E2']);
    }

    public function test_a_season_that_fails_to_fetch_does_not_discard_seasons_already_synced_this_call(): void
    {
        $show = $this->show(['status' => 'Returning Series']);

        $this->fakeClient(new class implements MediaSourceClient, SupportsEpisodeSync
        {
            public function search(string $query, MediaType $type): array
            {
                return [];
            }

            public function details(string $externalId, MediaType $type): MediaDetails
            {
                throw new RuntimeException('not used in this test');
            }

            public function showSummary(string $externalId): ShowSummary
            {
                return new ShowSummary('Returning Series', [1, 2]);
            }

            public function episodesForSeason(string $externalId, int $seasonNumber): array
            {
                if ($seasonNumber === 2) {
                    throw MediaSourceUnavailableException::for(MediaSource::Tmdb);
                }

                return [new EpisodeData($seasonNumber, 1, "S{$seasonNumber}E1", '2011-01-01')];
            }
        });

        $this->expectException(MediaSourceUnavailableException::class);

        try {
            $this->syncer()->episodesFor($show);
        } finally {
            $this->assertDatabaseHas('episodes', ['media_item_id' => $show->id, 'season_number' => 1]);
            $this->assertDatabaseMissing('episodes', ['media_item_id' => $show->id, 'season_number' => 2]);
        }
    }

    public function test_already_synced_and_terminal_status_never_calls_the_source_again(): void
    {
        $show = $this->show(['status' => 'Ended']);
        $show->episodes()->create(['season_number' => 1, 'episode_number' => 1, 'title' => 'Pilot']);

        $this->fakeClient(new class implements MediaSourceClient, SupportsEpisodeSync
        {
            public function search(string $query, MediaType $type): array
            {
                return [];
            }

            public function details(string $externalId, MediaType $type): MediaDetails
            {
                throw new RuntimeException('not used in this test');
            }

            public function showSummary(string $externalId): ShowSummary
            {
                throw new RuntimeException('should never be called for an already-synced, ended show');
            }

            public function episodesForSeason(string $externalId, int $seasonNumber): array
            {
                throw new RuntimeException('should never be called for an already-synced, ended show');
            }
        });

        $episodes = $this->syncer()->episodesFor($show);

        $this->assertCount(1, $episodes);
    }

    public function test_a_still_airing_show_fetches_only_the_newly_appeared_season(): void
    {
        $show = $this->show(['status' => 'Returning Series']);
        $show->episodes()->create(['season_number' => 1, 'episode_number' => 1, 'title' => 'Pilot']);

        $client = new class implements MediaSourceClient, SupportsEpisodeSync
        {
            public array $calledSeasons = [];

            public function search(string $query, MediaType $type): array
            {
                return [];
            }

            public function details(string $externalId, MediaType $type): MediaDetails
            {
                throw new RuntimeException('not used in this test');
            }

            public function showSummary(string $externalId): ShowSummary
            {
                return new ShowSummary('Ended', [1, 2]);
            }

            public function episodesForSeason(string $externalId, int $seasonNumber): array
            {
                $this->calledSeasons[] = $seasonNumber;

                return [new EpisodeData($seasonNumber, 1, "S{$seasonNumber}E1", '2012-01-01')];
            }
        };
        $this->fakeClient($client);

        $episodes = $this->syncer()->episodesFor($show);

        $this->assertSame([2], $client->calledSeasons);
        $this->assertCount(2, $episodes);
        $this->assertSame('Ended', $show->fresh()->metadata['status']);
    }

    public function test_a_manually_cataloged_show_returns_whatever_is_stored_locally_without_calling_the_source(): void
    {
        $show = MediaItem::create([
            'type' => MediaType::TvShow,
            'source' => MediaSource::Manual,
            'external_id' => null,
            'title' => 'An Obscure Show',
        ]);

        $this->fakeClient(new class implements MediaSourceClient, SupportsEpisodeSync
        {
            public function search(string $query, MediaType $type): array
            {
                return [];
            }

            public function details(string $externalId, MediaType $type): MediaDetails
            {
                throw new RuntimeException('not used in this test');
            }

            public function showSummary(string $externalId): ShowSummary
            {
                throw new RuntimeException('should never be called for a manually-cataloged show');
            }

            public function episodesForSeason(string $externalId, int $seasonNumber): array
            {
                throw new RuntimeException('should never be called for a manually-cataloged show');
            }
        });

        $episodes = $this->syncer()->episodesFor($show);

        $this->assertCount(0, $episodes);
    }

    public function test_throws_for_a_non_tv_show_media_item(): void
    {
        $movie = MediaItem::create([
            'type' => MediaType::Movie,
            'source' => MediaSource::Tmdb,
            'external_id' => '603',
            'title' => 'The Matrix',
        ]);

        $this->expectException(RuntimeException::class);

        $this->syncer()->episodesFor($movie);
    }
}
