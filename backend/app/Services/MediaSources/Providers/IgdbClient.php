<?php

namespace App\Services\MediaSources\Providers;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Services\MediaSources\Exceptions\MediaNotFoundException;
use App\Services\MediaSources\Exceptions\MediaSourceUnavailableException;
use App\Services\MediaSources\MediaDetails;
use App\Services\MediaSources\MediaSourceClient;
use App\Services\MediaSources\SearchResult;
use Closure;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * IGDB authenticates entirely through a Twitch Developer app's OAuth2
 * client-credentials flow — there is no IGDB-specific API key. See
 * docs/adr/0002-games-metadata-vendor-igdb-over-rawg.md and
 * docs/research/games-api-alternatives-to-rawg.md.
 */
class IgdbClient implements MediaSourceClient
{
    private const TOKEN_CACHE_KEY = 'igdb.twitch_access_token';

    public function search(string $query, MediaType $type): array
    {
        $body = sprintf(
            'search "%s"; fields name,cover.url,first_release_date; limit 20;',
            $this->escapeApicalypseString($query)
        );

        return array_map(
            fn (array $game) => new SearchResult(
                type: MediaType::Game,
                source: MediaSource::GamesApi,
                externalId: (string) $game['id'],
                title: $game['name'],
                releaseDate: $this->timestampToDate($game['first_release_date'] ?? null),
                posterUrl: $this->normalizeCoverUrl($game['cover']['url'] ?? null),
                overview: null,
            ),
            $this->games($body)
        );
    }

    public function details(string $externalId, MediaType $type): MediaDetails
    {
        $body = sprintf(
            'fields name,cover.url,first_release_date,summary,platforms.name,involved_companies.company.name,involved_companies.developer; where id = %d;',
            (int) $externalId
        );

        $game = $this->games($body)[0]
            ?? throw MediaNotFoundException::forExternalId(MediaSource::GamesApi, $externalId);

        return new MediaDetails(
            type: MediaType::Game,
            source: MediaSource::GamesApi,
            externalId: (string) $game['id'],
            title: $game['name'],
            releaseDate: $this->timestampToDate($game['first_release_date'] ?? null),
            posterUrl: $this->normalizeCoverUrl($game['cover']['url'] ?? null),
            overview: $game['summary'] ?? null,
            metadata: [
                'platforms' => array_map(fn (array $platform) => $platform['name'], $game['platforms'] ?? []),
                'developer' => $this->developerName($game['involved_companies'] ?? []),
            ],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function games(string $apicalypseBody): array
    {
        $response = $this->queryGames($apicalypseBody);

        if ($response->status() === 401) {
            // Cached token was stale (expired/revoked server-side before our
            // TTL thought it would); force a fresh one and try exactly once more.
            Cache::forget(self::TOKEN_CACHE_KEY);
            $response = $this->queryGames($apicalypseBody);
        }

        if ($response->failed()) {
            throw MediaSourceUnavailableException::for(MediaSource::GamesApi);
        }

        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw MediaSourceUnavailableException::for(MediaSource::GamesApi);
        }

        return $decoded;
    }

    private function queryGames(string $apicalypseBody): Response
    {
        return $this->withOneRetry(fn () => Http::withHeaders([
            'Client-ID' => config('services.twitch.client_id'),
            'Authorization' => 'Bearer '.$this->accessToken(),
        ])->withBody($apicalypseBody, 'text/plain')
            ->post(rtrim(config('services.igdb.base_url'), '/').'/games'));
    }

    private function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        return is_string($cached) ? $cached : $this->refreshAccessToken();
    }

    private function refreshAccessToken(): string
    {
        $response = $this->withOneRetry(fn () => Http::asForm()->post(config('services.twitch.oauth_url'), [
            'client_id' => config('services.twitch.client_id'),
            'client_secret' => config('services.twitch.client_secret'),
            'grant_type' => 'client_credentials',
        ]));

        if ($response->failed()) {
            throw MediaSourceUnavailableException::for(MediaSource::GamesApi);
        }

        $token = $response->json('access_token');
        $expiresIn = $response->json('expires_in');

        if (! is_string($token) || $token === '' || ! is_int($expiresIn)) {
            throw MediaSourceUnavailableException::for(MediaSource::GamesApi);
        }

        // Refresh a little before Twitch's own expiry, never for less than a minute.
        Cache::put(self::TOKEN_CACHE_KEY, $token, max($expiresIn - 60, 60));

        return $token;
    }

    /**
     * One retry with a short backoff, covering both a thrown connection
     * failure and a failed (5xx) response. A 4xx response (e.g. the 401 the
     * caller handles itself for a stale token) is returned as-is and
     * doesn't consume the retry.
     */
    private function withOneRetry(Closure $attempt): Response
    {
        try {
            $response = $attempt();
        } catch (Throwable $e) {
            usleep(200_000);

            try {
                return $attempt();
            } catch (Throwable $e2) {
                throw MediaSourceUnavailableException::for(MediaSource::GamesApi, $e2);
            }
        }

        if ($response->serverError()) {
            usleep(200_000);

            try {
                $response = $attempt();
            } catch (Throwable $e) {
                throw MediaSourceUnavailableException::for(MediaSource::GamesApi, $e);
            }
        }

        return $response;
    }

    /**
     * @param  array<int, array<string, mixed>>  $involvedCompanies
     */
    private function developerName(array $involvedCompanies): ?string
    {
        foreach ($involvedCompanies as $involved) {
            if (($involved['developer'] ?? false) === true) {
                return $involved['company']['name'] ?? null;
            }
        }

        return null;
    }

    private function timestampToDate(?int $timestamp): ?string
    {
        return $timestamp === null ? null : gmdate('Y-m-d', $timestamp);
    }

    /**
     * IGDB's cover.url comes back protocol-relative and sized for a
     * thumbnail by default; upsize it to an actual cover image. Pattern
     * corroborated against several independent IGDB client implementations
     * (api-docs.igdb.com's own images page couldn't be fetched directly in
     * this session to confirm firsthand — flagged for a human reviewer).
     */
    private function normalizeCoverUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        }

        return str_replace('/t_thumb/', '/t_cover_big/', $url);
    }

    private function escapeApicalypseString(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}
