<?php

namespace App\Policies;

use App\Models\LogEntry;
use App\Models\User;

class LogEntryPolicy
{
    /**
     * A Log Entry belongs to exactly the user who owns the Shelf Entry it
     * hangs off — no role-based override, same as UserMediaPolicy::manage.
     * See docs/planning/06-roles-permissions.md.
     */
    public function manage(User $user, LogEntry $logEntry): bool
    {
        return $user->id === $logEntry->userMedia->user_id;
    }
}
