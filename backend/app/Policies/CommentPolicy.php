<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    /**
     * Deleting a Comment (always a soft-delete, see GLOSSARY.md
     * "Moderation") is available to the Comment's own author, the author of
     * the Review it's under, or any Admin instance-wide. hasRole(Admin)
     * rather than a permission for the same reason as
     * InvitationPolicy::manage(): this is "any Admin can override", not a
     * capability that gets delegated to specific users. See
     * docs/planning/06-roles-permissions.md.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id
            || $user->id === $comment->logEntry->userMedia->user_id
            || $user->hasRole(UserRole::Admin->value);
    }
}
