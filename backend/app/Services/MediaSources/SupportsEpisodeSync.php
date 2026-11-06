<?php

namespace App\Services\MediaSources;

use App\Services\MediaSources\Exceptions\MediaNotFoundException;
use App\Services\MediaSources\Exceptions\MediaSourceUnavailableException;

/**
 * Implemented only by the source client(s) that back tv_show. Episode-level
 * sync is TV-only, so this stays out of the shared MediaSourceClient contract
 * that the games/books clients also implement. See
 * docs/adr/0004-episode-sync-is-lazy-and-self-refreshing.md.
 */
interface SupportsEpisodeSync
{
    /**
     * @throws MediaNotFoundException
     * @throws MediaSourceUnavailableException
     */
    public function showSummary(string $externalId): ShowSummary;

    /**
     * @return array<int, EpisodeData>
     *
     * @throws MediaNotFoundException
     * @throws MediaSourceUnavailableException
     */
    public function episodesForSeason(string $externalId, int $seasonNumber): array;
}
