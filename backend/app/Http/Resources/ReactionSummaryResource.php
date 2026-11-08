<?php

namespace App\Http\Resources;

use App\Enums\ReactionEmoji;
use App\Models\Reaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full Reaction breakdown for a single Log Entry: counts per emoji, who
 * reacted with each, and the viewer's own Reaction(s). Only used on the
 * dedicated GET .../reactions endpoint — list endpoints (Activity Feed, New
 * from Friends, item-detail diary) embed a condensed summary instead,
 * directly on LogEntryResource/ActivityEntryResource. See ADR 0006.
 *
 * @property Collection<int, Reaction> $reactions
 */
class ReactionSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reactions = $this->reactions;
        $viewerId = $request->user()->id;

        $byEmoji = collect(ReactionEmoji::cases())
            ->map(function (ReactionEmoji $emoji) use ($reactions) {
                $forEmoji = $reactions->where('emoji', $emoji);

                return [
                    'emoji' => $emoji->value,
                    'count' => $forEmoji->count(),
                    'users' => MemberResource::collection($forEmoji->pluck('user')->values()),
                ];
            })
            ->filter(fn (array $entry) => $entry['count'] > 0)
            ->values();

        return [
            'log_entry_id' => $this->id,
            'reactions' => $byEmoji,
            'viewer_reactions' => $reactions->where('user_id', $viewerId)->pluck('emoji')->values(),
        ];
    }
}
