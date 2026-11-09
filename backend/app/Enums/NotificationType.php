<?php

namespace App\Enums;

enum NotificationType: string
{
    case Tagged = 'tagged';
    case FriendRequest = 'friend_request';
    case FriendAccepted = 'friend_accepted';
    case CommentOnReview = 'comment_on_review';
    case CommentReply = 'comment_reply';
    case Reaction = 'reaction';
}
