<?php

namespace App\Services\Friends;

use App\Models\Friendship;

final readonly class FriendshipResult
{
    public function __construct(
        public Friendship $friendship,
        public bool $wasNew,
    ) {}
}
