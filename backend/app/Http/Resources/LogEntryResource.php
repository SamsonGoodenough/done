<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_media_id' => $this->user_media_id,
            'logged_date' => $this->logged_date,
            'rating' => $this->rating === null ? null : (float) $this->rating,
            'review' => $this->review,
            'contains_spoilers' => $this->contains_spoilers,
            'is_rewatch' => $this->is_rewatch,
            'tagged_user_ids' => $this->whenLoaded('participants', fn () => $this->participants->pluck('id')->values()),
            'created_at' => $this->created_at,
        ];
    }
}
