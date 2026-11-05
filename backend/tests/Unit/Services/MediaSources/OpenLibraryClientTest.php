<?php

namespace Tests\Unit\Services\MediaSources;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Services\MediaSources\Exceptions\MediaNotFoundException;
use App\Services\MediaSources\Exceptions\MediaSourceUnavailableException;
use App\Services\MediaSources\Providers\OpenLibraryClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenLibraryClientTest extends TestCase
{
    private OpenLibraryClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Deterministic test config, independent of defaults.
        config([
            'services.open_library.base_url' => 'https://openlibrary.org',
            'services.open_library.covers_base_url' => 'https://covers.openlibrary.org',
        ]);

        $this->client = new OpenLibraryClient;
    }

    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/open_library/{$name}.json")), true);
    }

    public function test_search_maps_results_falls_back_to_edition_key_and_skips_editionless_works(): void
    {
        Http::fake([
            'https://openlibrary.org/search.json*' => Http::response($this->fixture('search-results'), 200),
        ]);

        $results = $this->client->search('lord of the rings', MediaType::Book);

        // The third fixture doc has neither cover_edition_key nor edition_key
        // at all — nothing to catalog, so it's skipped rather than erroring.
        $this->assertCount(2, $results);

        $first = $results[0];
        $this->assertSame(MediaType::Book, $first->type);
        $this->assertSame(MediaSource::BooksApi, $first->source);
        $this->assertSame('OL51694024M', $first->externalId);
        $this->assertSame('The Lord of the Rings', $first->title);
        $this->assertSame('1954-01-01', $first->releaseDate);
        $this->assertSame('https://covers.openlibrary.org/b/olid/OL51694024M-M.jpg', $first->posterUrl);
        $this->assertNull($first->overview);

        // Second doc has no cover_edition_key, so it falls back to edition_key[0].
        $second = $results[1];
        $this->assertSame('OL7353617M', $second->externalId);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://openlibrary.org/search.json')
                && $request['q'] === 'lord of the rings'
                && $request['fields'] === 'key,title,first_publish_year,edition_key,cover_edition_key';
        });
    }

    public function test_details_maps_authors_isbn_pagecount_and_cover(): void
    {
        Http::fake([
            'https://openlibrary.org/api/books*' => Http::response($this->fixture('book-details'), 200),
        ]);

        $details = $this->client->details('OL7353617M', MediaType::Book);

        $this->assertSame(MediaType::Book, $details->type);
        $this->assertSame(MediaSource::BooksApi, $details->source);
        $this->assertSame('Fantastic Mr. Fox', $details->title);
        $this->assertSame('1988-10-01', $details->releaseDate);
        $this->assertSame('https://covers.openlibrary.org/b/id/15152634-L.jpg', $details->posterUrl);
        $this->assertSame('Down in the valley there were three farms.', $details->overview);
        $this->assertSame([
            'authors' => ['Roald Dahl'],
            'page_count' => 96,
            // isbn_13 is preferred over isbn_10 when both are present.
            'isbn' => '9780140328721',
        ], $details->metadata);
    }

    public function test_details_handles_a_sparse_record_with_bare_year_and_no_isbn13(): void
    {
        Http::fake([
            'https://openlibrary.org/api/books*' => Http::response($this->fixture('book-details-sparse'), 200),
        ]);

        $details = $this->client->details('OL62536872M', MediaType::Book);

        // The critical regression case: a bare "1954" publish_date must NOT
        // be misread by strtotime() as "today at 19:54" — it must normalize
        // to the year itself.
        $this->assertSame('1954-01-01', $details->releaseDate);
        $this->assertSame('J.R.R. Tolkien', $details->metadata['authors'][0]);
        $this->assertNull($details->metadata['page_count']);
        // No isbn_13 in this fixture — falls back to isbn_10.
        $this->assertSame('0261103571', $details->metadata['isbn']);
        $this->assertNull($details->overview);
        // No "cover" object in this fixture — falls back to a constructed URL.
        $this->assertSame('https://covers.openlibrary.org/b/olid/OL62536872M-L.jpg', $details->posterUrl);
    }

    public function test_details_throws_not_found_when_open_library_returns_an_empty_object(): void
    {
        Http::fake([
            'https://openlibrary.org/api/books*' => Http::response($this->fixture('not-found'), 200),
        ]);

        $this->expectException(MediaNotFoundException::class);

        $this->client->details('OL00000000M', MediaType::Book);
    }

    public function test_search_failure_retries_once_then_throws_unavailable(): void
    {
        Http::fake([
            'https://openlibrary.org/search.json*' => Http::response($this->fixture('server-error'), 500),
        ]);

        $this->expectException(MediaSourceUnavailableException::class);

        try {
            $this->client->search('anything', MediaType::Book);
        } finally {
            // the one retry means two total attempts
            Http::assertSentCount(2);
        }
    }

    public function test_connection_failure_throws_unavailable(): void
    {
        Http::fake([
            'https://openlibrary.org/search.json*' => Http::failedConnection(),
        ]);

        $this->expectException(MediaSourceUnavailableException::class);

        $this->client->search('anything', MediaType::Book);
    }

    public function test_malformed_response_body_throws_unavailable(): void
    {
        Http::fake([
            'https://openlibrary.org/api/books*' => Http::response('not json at all', 200),
        ]);

        $this->expectException(MediaSourceUnavailableException::class);

        $this->client->details('OL7353617M', MediaType::Book);
    }
}
