<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\ReactionAdded;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class CreateReactionNotification implements ShouldQueueAfterCommit
{
    /**
     * userMedia is loaded withTrashed(): the Shelf Entry a review's Log
     * Entry hangs off of can be soft-deleted independently (removing an item
     * from your shelf doesn't touch its diary), and the review stays fully
     * intact when that happens, so the owner still needs to resolve and
     * still needs to be notified.
     */
    public function handle(ReactionAdded $event): void
    {
        $reaction = $event->reaction->loadMissing(['logEntry.userMedia' => fn ($query) => $query->withTrashed()]);
        $recipientId = $reaction->logEntry->userMedia->user_id;

        if ($reaction->user_id === $recipientId) {
            return;
        }

        Notification::create([
            'user_id' => $recipientId,
            'type' => NotificationType::Reaction,
            'data' => [
                'log_entry_id' => $reaction->log_entry_id,
                'from_user_id' => $reaction->user_id,
                'emoji' => $reaction->emoji->value,
            ],
        ]);
    }
}
