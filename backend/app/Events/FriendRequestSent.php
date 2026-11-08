<?php

namespace App\Events;

use App\Models\Friendship;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new (or freshly-restored) pending Friendship was created, requester ->
 * addressee. Not fired for a no-op (an already-pending or already-accepted
 * row found as-is) or for the crossing-request auto-accept path — see
 * FriendRequestAccepted for that one. See ADR 0007.
 */
class FriendRequestSent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Friendship $friendship,
    ) {}
}
