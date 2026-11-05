<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserMedia;

class UserMediaPolicy
{
    /**
     * A Shelf Entry belongs to exactly the user who shelved it — no
     * role-based override, unlike invitations. See
     * docs/planning/06-roles-permissions.md: shelving/logging applies
     * equally regardless of role.
     */
    public function manage(User $user, UserMedia $userMedia): bool
    {
        return $user->id === $userMedia->user_id;
    }
}
