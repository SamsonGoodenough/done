<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserMediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'media_item_id' => $this->media_item_id,
            'media_item' => $this->whenLoaded('mediaItem', fn () => MediaItemResource::make($this->mediaItem)),
            'status' => $this->status,
            'is_favourite' => $this->is_favourite,
            // Only present on queries that select it (the shelf index), and the
            // rating of the newest rated Log Entry under this Shelf Entry.
            'latest_rating' => $this->whenHas(
                'latest_rating',
                fn () => $this->latest_rating === null ? null : (float) $this->latest_rating,
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
