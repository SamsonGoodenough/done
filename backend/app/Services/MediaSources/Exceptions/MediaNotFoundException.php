<?php

namespace App\Services\MediaSources\Exceptions;

use App\Enums\MediaSource;
use RuntimeException;

/**
 * The source responded cleanly but has nothing for this external_id (a
 * stale or mistyped id) — a bad client input, not a source outage. Distinct
 * from MediaSourceUnavailableException. See
 * docs/adr/0001-media-source-integration-architecture.md.
 */
class MediaNotFoundException extends RuntimeException
{
    public static function forExternalId(MediaSource $source, string $externalId): self
    {
        return new self("No {$source->value} item found for external_id [{$externalId}].");
    }
}
