<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One Log Entry as it appears in the Activity Feed or New from Friends,
 * same fields as LogEntryResource, plus `user`, since these surfaces mix
 * entries from several members and need to say whose entry each one is.
 */
class ActivityEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('userMedia', fn () => MemberResource::make($this->userMedia->user)),
            'media_item' => $this->whenLoaded('userMedia', fn () => MediaItemResource::make($this->userMedia->mediaItem)),
            'episode_id' => $this->episode_id,
            'season_number' => $this->season_number,
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
