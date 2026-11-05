<?php

namespace App\Services\Shelf;

use App\Enums\ShelfStatus;
use App\Models\UserMedia;
use Illuminate\Database\QueryException;

/**
 * Dedup/restore for adding to the shelf — the same pattern as
 * MediaItemCataloger for media_items (source, external_id). See
 * docs/planning/04-data-model.md and
 * docs/adr/0001-media-source-integration-architecture.md.
 */
class ShelfEntryManager
{
    private const UNIQUE_VIOLATION = '23505';

    public function addToShelf(int $userId, int $mediaItemId, ShelfStatus $status, bool $isFavourite): ShelfEntryResult
    {
        $attributes = [
            'user_id' => $userId,
            'media_item_id' => $mediaItemId,
            'status' => $status,
            'is_favourite' => $isFavourite,
        ];

        $existing = UserMedia::withTrashed()
            ->where('user_id', $userId)
            ->where('media_item_id', $mediaItemId)
            ->first();

        if ($existing === null) {
            try {
                return new ShelfEntryResult(UserMedia::create($attributes), wasNew: true);
            } catch (QueryException $e) {
                if ($e->getCode() !== self::UNIQUE_VIOLATION) {
                    throw $e;
                }

                // Lost a create-create race to a concurrent request; the
                // row that won is exactly what we'd have returned anyway.
                $existing = UserMedia::withTrashed()
                    ->where('user_id', $userId)
                    ->where('media_item_id', $mediaItemId)
                    ->firstOrFail();

                return new ShelfEntryResult($existing, wasNew: false);
            }
        }

        if ($existing->trashed()) {
            // Refresh-on-restore, mirroring MediaItemCataloger: the caller
            // just supplied fresh attributes, no reason to keep stale ones
            // from when this was removed from the shelf.
            $existing->fill($attributes);
            $existing->restore();

            return new ShelfEntryResult($existing, wasNew: true);
        }

        // An active shelf entry already exists: leave it untouched. POST
        // here is "ensure this is on my shelf," not an upsert — changing an
        // existing entry's status/favourite is PATCH's job
        // (UpdateUserMediaRequest), so a stray repeat POST can't silently
        // clobber a status set by logging.
        return new ShelfEntryResult($existing, wasNew: false);
    }
}
