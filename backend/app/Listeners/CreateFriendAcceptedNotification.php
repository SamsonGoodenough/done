<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\FriendRequestAccepted;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * Queued after commit since FriendshipManager::resolve() dispatches this
 * from inside a DB transaction — see ADR 0007.
 */
class CreateFriendAcceptedNotification implements ShouldQueueAfterCommit
{
    public function handle(FriendRequestAccepted $event): void
    {
        $friendship = $event->friendship;

        Notification::create([
            'user_id' => $friendship->requester_id,
            'type' => NotificationType::FriendAccepted,
            'data' => [
                'friendship_id' => $friendship->id,
                'from_user_id' => $friendship->addressee_id,
            ],
        ]);
    }
}
