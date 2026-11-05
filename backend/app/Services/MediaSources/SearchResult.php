<?php

namespace App\Services\MediaSources;

use App\Enums\MediaSource;
use App\Enums\MediaType;

final readonly class SearchResult
{
    public function __construct(
        public MediaType $type,
        public MediaSource $source,
        public string $externalId,
        public string $title,
        public ?string $releaseDate,
        public ?string $posterUrl,
        public ?string $overview,
    ) {}
}
