<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One Log Entry as it appears in the Activity Feed or New from Friends,
 * same fields as LogEntryResource, plus `user`, since these surfaces mix
 * entries from several members and need to say whose entry each one is.
 *
 * `comment_count`/`reaction_summary` are a condensed view (total counts
 * plus the viewer's own Reaction(s), not a full reactor breakdown) — see
 * docs/adr/0006-comment-reaction-summaries-embedded-in-existing-endpoints.md.
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
            'comment_count' => $this->comment_count ?? 0,
            'reaction_summary' => [
                'count' => $this->reaction_count ?? 0,
                'viewer_reactions' => $this->relationLoaded('reactions') ? $this->reactions->pluck('emoji')->values() : [],
            ],
            'created_at' => $this->created_at,
        ];
    }
}
