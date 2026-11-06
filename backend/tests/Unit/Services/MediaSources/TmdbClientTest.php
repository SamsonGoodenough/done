<?php

namespace Tests\Unit\Services\MediaSources;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Services\MediaSources\Exceptions\MediaNotFoundException;
use App\Services\MediaSources\Exceptions\MediaSourceUnavailableException;
use App\Services\MediaSources\Providers\TmdbClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TmdbClientTest extends TestCase
{
    private TmdbClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Deterministic test config, independent of whatever real key is in .env.
        config([
            'services.tmdb.key' => 'fake-tmdb-key',
            'services.tmdb.base_url' => 'https://api.themoviedb.org/3',
            'services.tmdb.image_base_url' => 'https://image.tmdb.org/t/p/w500',
        ]);

        $this->client = new TmdbClient;
    }

    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/tmdb/{$name}.json")), true);
    }

    public function test_movie_search_maps_results_and_normalizes_empty_fields(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/search/movie*' => Http::response($this->fixture('movie-search'), 200),
        ]);

        $results = $this->client->search('matrix', MediaType::Movie);

        $this->assertCount(2, $results);

        $this->assertSame(MediaType::Movie, $results[0]->type);
        $this->assertSame(MediaSource::Tmdb, $results[0]->source);
        $this->assertSame('603', $results[0]->externalId);
        $this->assertSame('The Matrix', $results[0]->title);
        $this->assertSame('1999-03-30', $results[0]->releaseDate);
        $this->assertSame('https://image.tmdb.org/t/p/w500/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg', $results[0]->posterUrl);
        $this->assertNotNull($results[0]->overview);

        // second result exercises the "" release_date / null poster_path normalization
        $this->assertNull($results[1]->releaseDate);
        $this->assertNull($results[1]->posterUrl);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://api.themoviedb.org/3/search/movie')
                && $request['api_key'] === 'fake-tmdb-key'
                && $request['query'] === 'matrix';
        });
    }

    public function test_tv_search_maps_name_and_first_air_date(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/search/tv*' => Http::response($this->fixture('tv-search'), 200),
        ]);

        $results = $this->client->search('thrones', MediaType::TvShow);

        $this->assertCount(1, $results);
        $this->assertSame(MediaType::TvShow, $results[0]->type);
        $this->assertSame('1399', $results[0]->externalId);
        $this->assertSame('Game of Thrones', $results[0]->title);
        $this->assertSame('2011-04-17', $results[0]->releaseDate);
    }

    public function test_movie_details_maps_runtime_and_genres_into_metadata(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/movie/603*' => Http::response($this->fixture('movie-details'), 200),
        ]);

        $details = $this->client->details('603', MediaType::Movie);

        $this->assertSame('The Matrix', $details->title);
        $this->assertSame('1999-03-30', $details->releaseDate);
        $this->assertSame(MediaSource::Tmdb, $details->source);
        $this->assertSame([
            'runtime' => 136,
            'genres' => ['Action', 'Science Fiction'],
        ], $details->metadata);
    }

    public function test_tv_details_maps_seasons_episodes_status_and_genres_into_metadata(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/tv/1399*' => Http::response($this->fixture('tv-details'), 200),
        ]);

        $details = $this->client->details('1399', MediaType::TvShow);

        $this->assertSame('Game of Thrones', $details->title);
        $this->assertSame([
            'seasons' => 8,
            'episodes' => 73,
            'status' => 'Ended',
            'genres' => ['Drama', 'Sci-Fi & Fantasy'],
        ], $details->metadata);
    }

    public function test_show_summary_maps_status_and_season_numbers_including_specials(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/tv/1399*' => Http::response($this->fixture('tv-details'), 200),
        ]);

        $summary = $this->client->showSummary('1399');

        $this->assertSame('Ended', $summary->status);
        $this->assertSame([0, 1, 2], $summary->seasonNumbers);
    }

    public function test_show_summary_404_throws_not_found(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/tv/999999*' => Http::response($this->fixture('not-found'), 404),
        ]);

        $this->expectException(MediaNotFoundException::class);

        $this->client->showSummary('999999');
    }

    public function test_episodes_for_season_maps_episode_numbers_titles_and_air_dates(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/tv/1399/season/1*' => Http::response($this->fixture('tv-season-1'), 200),
        ]);

        $episodes = $this->client->episodesForSeason('1399', 1);

        $this->assertCount(2, $episodes);
        $this->assertSame(1, $episodes[0]->seasonNumber);
        $this->assertSame(1, $episodes[0]->episodeNumber);
        $this->assertSame('Winter Is Coming', $episodes[0]->title);
        $this->assertSame('2011-04-17', $episodes[0]->airDate);
    }

    public function test_episodes_for_season_normalizes_empty_title_and_air_date_to_null(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/tv/1399/season/2*' => Http::response($this->fixture('tv-season-2'), 200),
        ]);

        $episodes = $this->client->episodesForSeason('1399', 2);

        $this->assertNull($episodes[1]->title);
        $this->assertNull($episodes[1]->airDate);
    }

    public function test_details_404_throws_not_found_without_retrying(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/movie/999999*' => Http::response($this->fixture('not-found'), 404),
        ]);

        $this->expectException(MediaNotFoundException::class);

        try {
            $this->client->details('999999', MediaType::Movie);
        } finally {
            // a 404 is a clean "not found," not a transient failure — never retried
            Http::assertSentCount(1);
        }
    }

    public function test_search_failure_retries_once_then_throws_unavailable(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/search/movie*' => Http::response('Internal Server Error', 500),
        ]);

        $this->expectException(MediaSourceUnavailableException::class);

        try {
            $this->client->search('matrix', MediaType::Movie);
        } finally {
            // the one retry means two total attempts
            Http::assertSentCount(2);
        }
    }

    public function test_connection_failure_throws_unavailable(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/search/movie*' => Http::failedConnection(),
        ]);

        $this->expectException(MediaSourceUnavailableException::class);

        $this->client->search('matrix', MediaType::Movie);
    }

    public function test_malformed_response_body_throws_unavailable(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/movie/603*' => Http::response('not json at all', 200),
        ]);

        $this->expectException(MediaSourceUnavailableException::class);

        $this->client->details('603', MediaType::Movie);
    }
}
