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
use Throwable;

/**
 * Books, via Open Library — the one source with no API key at all. Catalogs
 * at edition granularity (not "work"), since this project's metadata shape
 * needs isbn/page_count, which only exist per-edition. See
 * docs/planning/04-data-model.md and docs/planning/07-external-integrations.md.
 */
class OpenLibraryClient implements MediaSourceClient
{
    public function search(string $query, MediaType $type): array
    {
        $response = $this->getOrFail("{$this->baseUrl()}/search.json", [
            'q' => $query,
            // Open Library stopped returning a default field set in Jan 2025
            // (https://blog.openlibrary.org/2025/01/16/api-search-json-performance-tuning/)
            // — every field this client needs must be requested explicitly.
            'fields' => 'key,title,first_publish_year,edition_key,cover_edition_key',
            'limit' => 20,
        ]);

        $data = $this->decodeOrFail($response);

        $results = [];

        foreach ($data['docs'] ?? [] as $doc) {
            $editionKey = $doc['cover_edition_key'] ?? $doc['edition_key'][0] ?? null;

            // No edition to catalog at all — a pure "work" stub with nothing
            // underneath it. Skip it rather than inventing an external_id.
            if ($editionKey === null) {
                continue;
            }

            $results[] = new SearchResult(
                type: MediaType::Book,
                source: MediaSource::BooksApi,
                externalId: (string) $editionKey,
                title: $doc['title'] ?? '',
                releaseDate: $this->yearToDate($doc['first_publish_year'] ?? null),
                posterUrl: $this->coverUrl($editionKey, 'M'),
                overview: null,
            );
        }

        return $results;
    }

    public function details(string $externalId, MediaType $type): MediaDetails
    {
        $response = $this->getOrFail("{$this->baseUrl()}/api/books", [
            'bibkeys' => "OLID:{$externalId}",
            'jscmd' => 'data',
            'format' => 'json',
        ]);

        $data = $this->decodeOrFail($response);

        // Open Library returns {} (200 OK, no matching key) for an unknown
        // bibkey rather than a 404 status — so check the body, not the status.
        $book = $data["OLID:{$externalId}"]
            ?? throw MediaNotFoundException::forExternalId(MediaSource::BooksApi, $externalId);

        $isbns = $book['identifiers'] ?? [];

        return new MediaDetails(
            type: MediaType::Book,
            source: MediaSource::BooksApi,
            externalId: $externalId,
            title: $book['title'] ?? '',
            releaseDate: $this->normalizePublishDate($book['publish_date'] ?? null),
            posterUrl: $book['cover']['large'] ?? $book['cover']['medium'] ?? $this->coverUrl($externalId, 'L'),
            // Open Library's per-edition record has no proper synopsis field
            // (jscmd=data carries no "description"); a real one lives on the
            // work and would need a second round trip to /works/{id}.json.
            // Deliberately skipped for this phase (ADR 0001 keeps this phase
            // to one call per operation) — the opening excerpt, when present,
            // is a reasonable stand-in, not a true synopsis.
            overview: $book['excerpts'][0]['text'] ?? null,
            metadata: [
                'authors' => array_map(
                    static fn (array $author) => $author['name'],
                    $book['authors'] ?? []
                ),
                'page_count' => $book['number_of_pages'] ?? null,
                'isbn' => ($isbns['isbn_13'][0] ?? null) ?? ($isbns['isbn_10'][0] ?? null),
            ],
        );
    }

    /**
     * One retry on a connection error or 5xx; see TmdbClient::getOrFail()
     * for the full explanation of why retry()'s first argument is total
     * attempts, not retry count (2 here means "one retry").
     */
    private function getOrFail(string $url, array $query): Response
    {
        try {
            return Http::retry(2, 200, function (Throwable $exception) {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                return $exception instanceof RequestException && $exception->response->serverError();
            }, throw: false)->get($url, $query);
        } catch (ConnectionException $e) {
            throw MediaSourceUnavailableException::for(MediaSource::BooksApi, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeOrFail(Response $response): array
    {
        if ($response->failed()) {
            throw MediaSourceUnavailableException::for(MediaSource::BooksApi);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw MediaSourceUnavailableException::for(MediaSource::BooksApi);
        }

        return $data;
    }

    private function yearToDate(?int $year): ?string
    {
        return $year === null ? null : sprintf('%04d-01-01', $year);
    }

    /**
     * Open Library's publish_date is free text ("October 1, 1988", "1954",
     * "Mar 1990", sometimes unparseable junk) — never a guaranteed ISO date.
     *
     * The bare-year case is checked before strtotime() on purpose: PHP's
     * strtotime("1954") does NOT parse "1954" as a year — it reads it as an
     * HHMM time and silently returns *today's* date instead. Letting
     * strtotime() see a bare year first would corrupt every year-only
     * publish_date into "today." Anything strtotime() can't parse falls
     * back to a loose year search; truly unparseable values become null
     * rather than a guess.
     */
    private function normalizePublishDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);

        if (preg_match('/^(1[0-9]{3}|20[0-9]{2})$/', $trimmed, $matches)) {
            return "{$matches[1]}-01-01";
        }

        $timestamp = strtotime($trimmed);

        if ($timestamp !== false) {
            return gmdate('Y-m-d', $timestamp);
        }

        if (preg_match('/\b(1[0-9]{3}|20[0-9]{2})\b/', $trimmed, $matches)) {
            return "{$matches[1]}-01-01";
        }

        return null;
    }

    private function coverUrl(string $editionKey, string $size): string
    {
        return rtrim(config('services.open_library.covers_base_url'), '/')."/b/olid/{$editionKey}-{$size}.jpg";
    }

    private function baseUrl(): string
    {
        return rtrim(config('services.open_library.base_url'), '/');
    }
}
