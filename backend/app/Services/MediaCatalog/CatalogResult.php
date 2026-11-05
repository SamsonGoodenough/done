<?php

namespace App\Services\MediaCatalog;

use App\Models\MediaItem;

final readonly class CatalogResult
{
    public function __construct(
        public MediaItem $mediaItem,
        public bool $wasNew,
    ) {}
}
