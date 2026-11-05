<?php

namespace App\Services\MediaCatalog;

use App\Models\MediaItem;
use App\Services\MediaSources\MediaDetails;

/**
 * Encapsulates the dedup/restore-or-create decision for cataloging a result
 * fetched from an external source. See the dedup/restore note on
 * media_items in docs/planning/04-data-model.md and
 * docs/adr/0001-media-source-integration-architecture.md.
 */
class MediaItemCataloger
{
    public function catalogFromSource(MediaDetails $details): CatalogResult
    {
        $attributes = [
            'type' => $details->type,
            'source' => $details->source,
            'external_id' => $details->externalId,
            'title' => $details->title,
            'release_date' => $details->releaseDate,
            'poster_url' => $details->posterUrl,
            'overview' => $details->overview,
            'metadata' => $details->metadata,
        ];

        $existing = MediaItem::withTrashed()
            ->where('source', $details->source->value)
            ->where('external_id', $details->externalId)
            ->first();

        if ($existing === null) {
            return new CatalogResult(MediaItem::create($attributes), wasNew: true);
        }

        if ($existing->trashed()) {
            // Refresh-on-restore: we already fetched live details, so there's
            // no reason to keep whatever stale metadata the row had when it
            // was soft-deleted.
            $existing->fill($attributes);
            $existing->restore();

            return new CatalogResult($existing, wasNew: true);
        }

        return new CatalogResult($existing, wasNew: false);
    }
}
