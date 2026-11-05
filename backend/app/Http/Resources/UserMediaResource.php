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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
