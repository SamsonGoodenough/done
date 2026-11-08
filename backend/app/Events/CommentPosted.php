<?php

namespace App\Events;

use App\Models\Comment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new Comment was posted, top-level or a reply. See ADR 0007 and
 * CreateCommentNotification for the fan-out rule.
 */
class CommentPosted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Comment $comment,
    ) {}
}
