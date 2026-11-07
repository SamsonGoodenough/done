<?php

namespace App\Policies;

use App\Enums\ProfileVisibility;
use App\Models\User;
use App\Services\Friends\FriendGraph;

/**
 * Gates a user's shelf, diary, reviews, and friend list behind Profile
 * Visibility, content only, never existence/search. Open to the user
 * themselves, anyone if the profile is Public, or an accepted friend
 * otherwise. Not role-gated. See GLOSSARY.md "Profile Visibility" and
 * docs/planning/06-roles-permissions.md.
 */
class UserPolicy
{
    public function __construct(private readonly FriendGraph $friendGraph) {}

    public function viewPrivateContent(User $viewer, User $target): bool
    {
        if ($viewer->id === $target->id) {
            return true;
        }

        if ($target->profile_visibility === ProfileVisibility::Public) {
            return true;
        }

        return $this->friendGraph->areFriends($viewer->id, $target->id);
    }
}
