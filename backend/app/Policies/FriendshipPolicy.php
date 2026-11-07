<?php

namespace App\Policies;

use App\Models\Friendship;
use App\Models\User;

/**
 * Friend requests apply equally regardless of role, no permission gate,
 * same as Shelf Entries and Tag Friends. See
 * docs/planning/06-roles-permissions.md.
 */
class FriendshipPolicy
{
    /**
     * Accepting or declining a request is the addressee's call only: the
     * requester can't accept or decline their own outgoing request.
     */
    public function respond(User $user, Friendship $friendship): bool
    {
        return $user->id === $friendship->addressee_id;
    }

    /**
     * Unfriending (removing an accepted Friendship) is available to either
     * party. See GLOSSARY.md "Unfriending".
     */
    public function manage(User $user, Friendship $friendship): bool
    {
        return $user->id === $friendship->requester_id || $user->id === $friendship->addressee_id;
    }
}
