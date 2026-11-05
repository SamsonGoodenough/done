<?php

namespace App\Services\MediaCatalog;

use App\Models\MediaItem;
use App\Services\MediaSources\MediaDetails;
use Illuminate\Database\QueryException;

/**
 * Encapsulates the dedup/restore-or-create decision for cataloging a result
 * fetched from an external source. See the dedup/restore note on
 * media_items in docs/planning/04-data-model.md and
 * docs/adr/0001-media-source-integration-architecture.md.
 */
class MediaItemCataloger
{
    private const UNIQUE_VIOLATION = '23505';

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
            ->where('type', $details->type->value)
            ->where('source', $details->source->value)
            ->where('external_id', $details->externalId)
            ->first();

        if ($existing === null) {
            try {
                return new CatalogResult(MediaItem::create($attributes), wasNew: true);
            } catch (QueryException $e) {
                if ($e->getCode() !== self::UNIQUE_VIOLATION) {
                    throw $e;
                }

                // Lost a create-create race to a concurrent request for the
                // same (type, source, external_id); the row that won is
                // exactly what we'd have returned anyway.
                return new CatalogResult(
                    MediaItem::where('type', $details->type->value)
                        ->where('source', $details->source->value)
                        ->where('external_id', $details->externalId)
                        ->firstOrFail(),
                    wasNew: false,
                );
            }
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
