<?php

namespace App\Enums;

/**
 * Permission slugs for spatie/laravel-permission, added incrementally as the
 * features that need them land. See docs/planning/06-roles-permissions.md
 * for the full target matrix. Only Phase 1's permissions exist here so far.
 */
enum UserPermission: string
{
    case SendInvitations = 'send-invitations';
    case ChangeUserRole = 'change-user-role';
    case DeactivateUser = 'deactivate-user';
}
