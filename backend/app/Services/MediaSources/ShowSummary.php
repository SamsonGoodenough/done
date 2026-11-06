<?php

namespace App\Services\MediaSources;

final readonly class ShowSummary
{
    /**
     * @param  array<int, int>  $seasonNumbers  Every season number the source currently lists for this show, including specials (0).
     */
    public function __construct(
        public string $status,
        public array $seasonNumbers,
    ) {}
}
