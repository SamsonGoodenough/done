<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\UserTagged;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * Queued after commit since LogEntryRecorder::record() dispatches this from
 * inside a DB transaction — see ADR 0007.
 */
class CreateTaggedNotification implements ShouldQueueAfterCommit
{
    public function handle(UserTagged $event): void
    {
        if ($event->actor->id === $event->taggedUser->id) {
            return;
        }

        Notification::create([
            'user_id' => $event->taggedUser->id,
            'type' => NotificationType::Tagged,
            'data' => [
                'log_entry_id' => $event->logEntry->id,
                'from_user_id' => $event->actor->id,
            ],
        ]);
    }
}
