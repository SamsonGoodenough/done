<?php

namespace App\Events;

use App\Models\LogEntry;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * $actor tagged $taggedUser as a participant on $logEntry. See ADR 0007.
 */
class UserTagged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public LogEntry $logEntry,
        public User $taggedUser,
        public User $actor,
    ) {}
}
