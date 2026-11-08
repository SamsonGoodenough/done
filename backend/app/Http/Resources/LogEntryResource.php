<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogEntryResource extends JsonResource
{
    /**
     * `comment_count`/`reaction_summary` are a condensed view (total counts
     * plus the viewer's own Reaction(s), not a full reactor breakdown) —
     * see docs/adr/0006-comment-reaction-summaries-embedded-in-existing-endpoints.md.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_media_id' => $this->user_media_id,
            'episode_id' => $this->episode_id,
            'season_number' => $this->season_number,
            'logged_date' => $this->logged_date,
            'rating' => $this->rating === null ? null : (float) $this->rating,
            'review' => $this->review,
            'contains_spoilers' => $this->contains_spoilers,
            'is_rewatch' => $this->is_rewatch,
            'tagged_user_ids' => $this->whenLoaded('participants', fn () => $this->participants->pluck('id')->values()),
            'comment_count' => $this->comment_count ?? 0,
            'reaction_summary' => [
                'count' => $this->reaction_count ?? 0,
                'viewer_reactions' => $this->relationLoaded('reactions') ? $this->reactions->pluck('emoji')->values() : [],
            ],
            'created_at' => $this->created_at,
        ];
    }
}
