<?php

namespace App\Services\MediaSources\Providers;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Services\MediaSources\Exceptions\MediaNotFoundException;
use App\Services\MediaSources\Exceptions\MediaSourceUnavailableException;
use App\Services\MediaSources\MediaDetails;
use App\Services\MediaSources\MediaSourceClient;
use App\Services\MediaSources\SearchResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

/**
 * Movies + TV, via TMDB's v3 API (api_key query param auth). See
 * docs/planning/07-external-integrations.md and
 * docs/adr/0001-media-source-integration-architecture.md.
 */
class TmdbClient implements MediaSourceClient
{
    public function search(string $query, MediaType $type): array
    {
        $response = $this->getOrFail("{$this->baseUrl()}/search/{$this->endpointSegment($type)}", [
            'api_key' => $this->apiKey(),
            'query' => $query,
        ]);

        $data = $this->decodeOrFail($response);

        return array_map(
            fn (array $result) => $this->mapSearchResult($result, $type),
            $data['results'] ?? []
        );
    }

    public function details(string $externalId, MediaType $type): MediaDetails
    {
        $response = $this->getOrFail(
            "{$this->baseUrl()}/{$this->endpointSegment($type)}/{$externalId}",
            ['api_key' => $this->apiKey()]
        );

        if ($response->status() === 404) {
            throw MediaNotFoundException::forExternalId(MediaSource::Tmdb, $externalId);
        }

        $data = $this->decodeOrFail($response);

        return $this->mapDetails($data, $type, $externalId);
    }

    /**
     * One retry on a connection error or 5xx (a genuine blip); a 4xx (like a
     * 404) is never retried since it isn't transient — see details() above,
     * which needs to see a real 404 to distinguish not-found from unavailable.
     * A connection error that survives the retry surfaces as a thrown
     * ConnectionException, which we translate here; a 5xx that survives the
     * retry comes back as an ordinary failed Response (throw: false), left
     * for decodeOrFail() to translate.
     */
    private function getOrFail(string $url, array $query): Response
    {
        try {
            // retry()'s first argument is total attempts, not retry count, so
            // 2 here means "one retry" (confirmed against the vendored
            // Illuminate\Support\helpers.php retry() implementation).
            return Http::retry(2, 200, function (Throwable $exception) {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                return $exception instanceof RequestException && $exception->response->serverError();
            }, throw: false)->get($url, $query);
        } catch (ConnectionException $e) {
            throw MediaSourceUnavailableException::for(MediaSource::Tmdb, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeOrFail(Response $response): array
    {
        if ($response->failed()) {
            throw MediaSourceUnavailableException::for(MediaSource::Tmdb);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw MediaSourceUnavailableException::for(MediaSource::Tmdb);
        }

        return $data;
    }

    private function mapSearchResult(array $result, MediaType $type): SearchResult
    {
        $isMovie = $type === MediaType::Movie;
        $posterPath = $result['poster_path'] ?? null;

        return new SearchResult(
            type: $type,
            source: MediaSource::Tmdb,
            externalId: (string) $result['id'],
            title: $isMovie ? ($result['title'] ?? '') : ($result['name'] ?? ''),
            releaseDate: $this->normalizeDate($isMovie ? ($result['release_date'] ?? null) : ($result['first_air_date'] ?? null)),
            posterUrl: $posterPath !== null ? $this->imageBaseUrl().$posterPath : null,
            overview: $result['overview'] ?? null,
        );
    }

    private function mapDetails(array $data, MediaType $type, string $externalId): MediaDetails
    {
        $isMovie = $type === MediaType::Movie;
        $posterPath = $data['poster_path'] ?? null;
        $genres = array_map(static fn (array $genre) => $genre['name'], $data['genres'] ?? []);

        $metadata = $isMovie
            ? ['runtime' => $data['runtime'] ?? null, 'genres' => $genres]
            : [
                'seasons' => $data['number_of_seasons'] ?? null,
                'episodes' => $data['number_of_episodes'] ?? null,
                'status' => $data['status'] ?? null,
                'genres' => $genres,
            ];

        return new MediaDetails(
            type: $type,
            source: MediaSource::Tmdb,
            externalId: isset($data['id']) ? (string) $data['id'] : $externalId,
            title: $isMovie ? ($data['title'] ?? '') : ($data['name'] ?? ''),
            releaseDate: $this->normalizeDate($isMovie ? ($data['release_date'] ?? null) : ($data['first_air_date'] ?? null)),
            posterUrl: $posterPath !== null ? $this->imageBaseUrl().$posterPath : null,
            overview: $data['overview'] ?? null,
            metadata: $metadata,
        );
    }

    private function normalizeDate(?string $date): ?string
    {
        return $date === null || $date === '' ? null : $date;
    }

    private function endpointSegment(MediaType $type): string
    {
        return match ($type) {
            MediaType::Movie => 'movie',
            MediaType::TvShow => 'tv',
            default => throw new InvalidArgumentException("TmdbClient does not support media type [{$type->value}]."),
        };
    }

    private function baseUrl(): string
    {
        return rtrim(config('services.tmdb.base_url'), '/');
    }

    private function imageBaseUrl(): string
    {
        return rtrim(config('services.tmdb.image_base_url'), '/');
    }

    private function apiKey(): string
    {
        return config('services.tmdb.key');
    }
}
