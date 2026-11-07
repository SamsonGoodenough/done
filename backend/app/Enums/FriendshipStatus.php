<?php

namespace App\Enums;

enum FriendshipStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
