<?php

namespace Tests\Unit\Services\MediaSources;

use App\Enums\MediaType;
use App\Services\MediaSources\Exceptions\MediaNotFoundException;
use App\Services\MediaSources\Exceptions\MediaSourceUnavailableException;
use App\Services\MediaSources\Providers\IgdbClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IgdbClientTest extends TestCase
{
    /**
     * @return array<mixed>
     */
    private function fixture(string $name): array
    {
        return json_decode(
            file_get_contents(base_path("tests/Fixtures/igdb/{$name}.json")),
            associative: true,
        );
    }

    public function test_search_maps_results_and_normalizes_cover_urls(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response($this->fixture('token')),
            'api.igdb.com/*' => Http::response($this->fixture('search-results')),
        ]);

        $results = (new IgdbClient)->search('witcher 3', MediaType::Game);

        $this->assertCount(2, $results);

        $first = $results[0];
        $this->assertSame('1942', $first->externalId);
        $this->assertSame('The Witcher 3: Wild Hunt', $first->title);
        $this->assertSame('2015-05-19', $first->releaseDate);
        $this->assertSame(
            'https://images.igdb.com/igdb/image/upload/t_cover_big/co1wyy.jpg',
            $first->posterUrl
        );
        $this->assertNull($first->overview);

        // Second fixture entry has no "cover" key at all.
        $second = $results[1];
        $this->assertSame('2016-06-08', $second->releaseDate);
        $this->assertNull($second->posterUrl);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.igdb.com/v4/games'
            && $request->hasHeader('Client-ID')
            && $request->hasHeader('Authorization', 'Bearer fake-twitch-access-token'));
    }

    public function test_details_extracts_platforms_and_the_developer_company(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response($this->fixture('token')),
            'api.igdb.com/*' => Http::response($this->fixture('game-details')),
        ]);

        $details = (new IgdbClient)->details('1942', MediaType::Game);

        $this->assertSame('The Witcher 3: Wild Hunt', $details->title);
        $this->assertSame('2015-05-19', $details->releaseDate);
        $this->assertStringContainsString('Child of Prophecy', $details->overview);
        $this->assertSame(
            ['PC (Microsoft Windows)', 'PlayStation 4', 'Xbox One'],
            $details->metadata['platforms']
        );
        // CD Projekt Red is flagged developer: true; Bandai Namco is only the publisher.
        $this->assertSame('CD Projekt Red', $details->metadata['developer']);
    }

    public function test_details_throws_not_found_when_igdb_returns_an_empty_array(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response($this->fixture('token')),
            'api.igdb.com/*' => Http::response($this->fixture('not-found')),
        ]);

        $this->expectException(MediaNotFoundException::class);

        (new IgdbClient)->details('999999999', MediaType::Game);
    }

    public function test_search_throws_unavailable_after_the_retry_is_exhausted(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response($this->fixture('token')),
            'api.igdb.com/*' => Http::sequence()
                ->push($this->fixture('server-error'), 500)
                ->push($this->fixture('server-error'), 500),
        ]);

        $this->expectException(MediaSourceUnavailableException::class);

        (new IgdbClient)->search('anything', MediaType::Game);
    }

    public function test_token_is_cached_and_not_refetched_within_its_ttl(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response($this->fixture('token')),
            'api.igdb.com/*' => Http::response($this->fixture('search-results')),
        ]);

        $client = new IgdbClient;
        $client->search('first call', MediaType::Game);
        $client->search('second call', MediaType::Game);

        Http::assertSentCount(3); // 1 token fetch + 2 games queries
        Http::assertSent(fn ($request) => str_contains($request->url(), 'oauth2/token'));
    }

    public function test_a_401_triggers_exactly_one_token_refresh_and_retry(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::sequence()
                ->push($this->fixture('token'))
                ->push($this->fixture('token')),
            'api.igdb.com/*' => Http::sequence()
                ->push(['message' => 'Unauthorized'], 401)
                ->push($this->fixture('search-results'), 200),
        ]);

        $results = (new IgdbClient)->search('witcher 3', MediaType::Game);

        $this->assertCount(2, $results);
        // 2 token fetches (initial + post-401 refresh) + 2 games queries (initial 401 + retry).
        Http::assertSentCount(4);
    }
}
