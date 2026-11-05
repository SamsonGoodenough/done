<?php

namespace App\Services\MediaSources;

use App\Enums\MediaType;
use App\Services\MediaSources\Exceptions\MediaNotFoundException;
use App\Services\MediaSources\Exceptions\MediaSourceUnavailableException;

/**
 * Implemented once per vendor (TmdbClient, IgdbClient, ...) so the
 * catalog-search and catalog-add flows never branch per-provider. See
 * docs/adr/0001-media-source-integration-architecture.md.
 */
interface MediaSourceClient
{
    /**
     * @return array<int, SearchResult>
     *
     * @throws MediaSourceUnavailableException
     */
    public function search(string $query, MediaType $type): array;

    /**
     * @throws MediaNotFoundException
     * @throws MediaSourceUnavailableException
     */
    public function details(string $externalId, MediaType $type): MediaDetails;
}
