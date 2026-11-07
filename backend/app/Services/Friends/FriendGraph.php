<?php

namespace App\Services\Friends;

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Derives "who is this user's friend" from the directional friendships
 * table: a mutual Friendship is `status = Accepted` read with an OR across
 * both directions, there's no single column to query directly. See
 * GLOSSARY.md "Friendship".
 */
class FriendGraph
{
    /**
     * @return Collection<int, int>
     */
    public function friendIds(int $userId): Collection
    {
        return Friendship::query()
            ->where('status', FriendshipStatus::Accepted)
            ->where(function ($query) use ($userId) {
                $query->where('requester_id', $userId)->orWhere('addressee_id', $userId);
            })
            ->get(['requester_id', 'addressee_id'])
            ->map(fn (Friendship $friendship) => $friendship->requester_id === $userId
                ? $friendship->addressee_id
                : $friendship->requester_id);
    }

    /**
     * @return Collection<int, User>
     */
    public function friendsOf(User $user): Collection
    {
        return User::query()->whereIn('id', $this->friendIds($user->id))->orderBy('name')->get();
    }

    public function areFriends(int $userAId, int $userBId): bool
    {
        return Friendship::between($userAId, $userBId)->where('status', FriendshipStatus::Accepted)->exists();
    }
}
