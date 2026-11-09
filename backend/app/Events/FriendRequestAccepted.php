<?php

namespace App\Events;

use App\Models\Friendship;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * $friendship just became Accepted, whether via the addressee explicitly
 * accepting or via a crossing request auto-resolving it. The requester is
 * always who gets notified, the addressee is always the actor who completed
 * it, in both cases. See ADR 0007.
 */
class FriendRequestAccepted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Friendship $friendship,
    ) {}
}
