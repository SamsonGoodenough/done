<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'source' => $this->source,
            'external_id' => $this->external_id,
            'title' => $this->title,
            'release_date' => $this->release_date,
            'poster_url' => $this->poster_url,
            'overview' => $this->overview,
            'metadata' => $this->metadata,
            'added_by' => $this->added_by,
            'created_at' => $this->created_at,
        ];
    }
}
