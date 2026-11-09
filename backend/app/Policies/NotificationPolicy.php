<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

class NotificationPolicy
{
    /** A Notification can only ever be marked read by its own recipient. */
    public function markRead(User $user, Notification $notification): bool
    {
        return $user->id === $notification->user_id;
    }
}
