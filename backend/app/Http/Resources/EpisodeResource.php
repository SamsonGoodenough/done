<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EpisodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'media_item_id' => $this->media_item_id,
            'season_number' => $this->season_number,
            'episode_number' => $this->episode_number,
            'title' => $this->title,
            'air_date' => $this->air_date,
        ];
    }
}
