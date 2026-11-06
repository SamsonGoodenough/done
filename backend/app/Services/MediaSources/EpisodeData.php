<?php

namespace App\Services\MediaSources;

final readonly class EpisodeData
{
    public function __construct(
        public int $seasonNumber,
        public int $episodeNumber,
        public ?string $title,
        public ?string $airDate,
    ) {}
}
