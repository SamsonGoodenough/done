<?php

namespace App\Services\Episodes;

use App\Enums\MediaType;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Services\MediaSources\MediaSourceResolver;
use App\Services\MediaSources\SupportsEpisodeSync;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Keeps a TV show's episodes in sync with its source, lazily: the first
 * read for a show fetches every season, later reads just query the DB. See
 * docs/adr/0004-episode-sync-is-lazy-and-self-refreshing.md.
 */
class EpisodeSyncer
{
    private const UNIQUE_VIOLATION = '23505';

    /** Source statuses that mean a show will never get a new season. */
    private const TERMINAL_STATUSES = ['Ended', 'Canceled'];

    public function __construct(private readonly MediaSourceResolver $resolver) {}

    /**
     * @return Collection<int, Episode>
     */
    public function episodesFor(MediaItem $show): Collection
    {
        if ($show->type !== MediaType::TvShow) {
            throw new RuntimeException("Cannot sync episodes for a [{$show->type->value}] media item.");
        }

        $synced = $this->syncedEpisodes($show);

        // A manually-cataloged show has no external_id to sync against; it
        // only ever has whatever episodes were already stored locally.
        if ($show->external_id === null) {
            return $synced;
        }

        $cachedStatus = $show->metadata['status'] ?? null;

        if ($synced->isNotEmpty() && in_array($cachedStatus, self::TERMINAL_STATUSES, true)) {
            return $synced;
        }

        $client = $this->resolver->forType(MediaType::TvShow);

        if (! $client instanceof SupportsEpisodeSync) {
            throw new RuntimeException('The tv_show source client does not support episode sync.');
        }

        $summary = $client->showSummary($show->external_id);

        $missingSeasons = array_values(array_diff(
            $summary->seasonNumbers,
            $synced->pluck('season_number')->unique()->all()
        ));

        foreach ($missingSeasons as $seasonNumber) {
            $this->syncSeason($show, $client, $seasonNumber);
        }

        if ($summary->status !== $cachedStatus) {
            $show->metadata = [...$show->metadata, 'status' => $summary->status];
            $show->save();
        }

        return $missingSeasons === [] ? $synced : $this->syncedEpisodes($show);
    }

    /**
     * @return Collection<int, Episode>
     */
    private function syncedEpisodes(MediaItem $show): Collection
    {
        return $show->episodes()->orderBy('season_number')->orderBy('episode_number')->get();
    }

    /**
     * Persists one season's episodes as soon as they're fetched, independent
     * of any other season: a failure on a later season never loses an
     * earlier one already synced this call.
     */
    private function syncSeason(MediaItem $show, SupportsEpisodeSync $client, int $seasonNumber): void
    {
        $episodes = $client->episodesForSeason($show->external_id, $seasonNumber);

        try {
            DB::transaction(function () use ($show, $episodes) {
                foreach ($episodes as $episode) {
                    Episode::create([
                        'media_item_id' => $show->id,
                        'season_number' => $episode->seasonNumber,
                        'episode_number' => $episode->episodeNumber,
                        'title' => $episode->title,
                        'air_date' => $episode->airDate,
                    ]);
                }
            });
        } catch (QueryException $e) {
            // Another concurrent request already synced this season: the
            // unique index on (media_item_id, season_number, episode_number)
            // caught it first, nothing left to do.
            if ($e->getCode() !== self::UNIQUE_VIOLATION) {
                throw $e;
            }
        }
    }
}
