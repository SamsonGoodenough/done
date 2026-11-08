<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\FriendRequestSent;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * Queued after commit since FriendshipManager::resolve() dispatches this
 * from inside a DB transaction — see ADR 0007.
 */
class CreateFriendRequestNotification implements ShouldQueueAfterCommit
{
    public function handle(FriendRequestSent $event): void
    {
        $friendship = $event->friendship;

        Notification::create([
            'user_id' => $friendship->addressee_id,
            'type' => NotificationType::FriendRequest,
            'data' => [
                'friendship_id' => $friendship->id,
                'from_user_id' => $friendship->requester_id,
            ],
        ]);
    }
}
