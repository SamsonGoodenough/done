<?php

namespace App\Services\Shelf;

use App\Models\UserMedia;

final readonly class ShelfEntryResult
{
    public function __construct(
        public UserMedia $userMedia,
        public bool $wasNew,
    ) {}
}
