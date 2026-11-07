<?php

namespace App\Services\Comments;

use App\Models\Comment;
use App\Models\LogEntry;
use App\Models\User;

class CommentManager
{
    public function create(User $user, LogEntry $logEntry, ?int $parentCommentId, string $body): Comment
    {
        return Comment::create([
            'log_entry_id' => $logEntry->id,
            'user_id' => $user->id,
            'parent_comment_id' => $parentCommentId,
            'body' => $body,
        ]);
    }
}
