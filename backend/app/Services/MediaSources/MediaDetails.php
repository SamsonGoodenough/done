<?php

namespace App\Services\MediaSources;

use App\Enums\MediaSource;
use App\Enums\MediaType;

final readonly class MediaDetails
{
    /**
     * @param  array<string, mixed>  $metadata  Type-specific fields, shaped per
     *                                          the metadata examples in docs/planning/04-data-model.md — copied
     *                                          as-is into media_items.metadata.
     */
    public function __construct(
        public MediaType $type,
        public MediaSource $source,
        public string $externalId,
        public string $title,
        public ?string $releaseDate,
        public ?string $posterUrl,
        public ?string $overview,
        public array $metadata,
    ) {}
}
