<?php

namespace App\Services\MediaSources\Exceptions;

use App\Enums\MediaSource;
use RuntimeException;
use Throwable;

/**
 * The source itself is the problem: a timeout, a 5xx, a malformed response,
 * or an auth failure, after the client's single retry already failed.
 * Distinct from MediaNotFoundException, which means the source responded
 * cleanly but the requested external_id doesn't exist. See
 * docs/adr/0001-media-source-integration-architecture.md.
 */
class MediaSourceUnavailableException extends RuntimeException
{
    public static function for(MediaSource $source, ?Throwable $previous = null): self
    {
        return new self("The {$source->value} source is currently unavailable.", previous: $previous);
    }
}
