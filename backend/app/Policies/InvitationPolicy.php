<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{
    /**
     * Revoking or resending an invitation is available to whoever sent it,
     * or any Admin. See docs/planning/06-roles-permissions.md.
     */
    public function manage(User $user, Invitation $invitation): bool
    {
        return $user->id === $invitation->invited_by || $user->hasRole(UserRole::Admin->value);
    }
}
