<?php

namespace App\Services\Shelf;

use App\Enums\ShelfStatus;
use App\Models\UserMedia;

/**
 * Dedup/restore for adding to the shelf — the same pattern as
 * MediaItemCataloger for media_items (source, external_id). See
 * docs/planning/04-data-model.md and
 * docs/adr/0001-media-source-integration-architecture.md.
 */
class ShelfEntryManager
{
    public function addToShelf(int $userId, int $mediaItemId, ShelfStatus $status, bool $isFavourite): UserMedia
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
            return UserMedia::create($attributes);
        }

        $existing->fill($attributes);

        if ($existing->trashed()) {
            $existing->restore();
        } else {
            $existing->save();
        }

        return $existing;
    }
}
