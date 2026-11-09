<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\CommentPosted;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * A top-level comment notifies only the review's owner (comment_on_review);
 * a reply at any depth notifies only its immediate parent comment's author
 * (comment_reply) — never both for the same comment. See the Phase 7 design
 * session note in docs/planning/09-roadmap.md and ADR 0007.
 */
class CreateCommentNotification implements ShouldQueueAfterCommit
{
    /**
     * userMedia is loaded withTrashed(): the Shelf Entry a review's Log
     * Entry hangs off of can be soft-deleted independently (removing an item
     * from your shelf doesn't touch its diary), and the review/comment stay
     * fully intact when that happens, so the owner still needs to resolve
     * and still needs to be notified.
     */
    public function handle(CommentPosted $event): void
    {
        $comment = $event->comment->loadMissing([
            'logEntry.userMedia' => fn ($query) => $query->withTrashed(),
            'parent',
        ]);

        if ($comment->parent_comment_id === null) {
            $recipientId = $comment->logEntry->userMedia->user_id;
            $type = NotificationType::CommentOnReview;
        } else {
            $recipientId = $comment->parent->user_id;
            $type = NotificationType::CommentReply;
        }

        if ($comment->user_id === $recipientId) {
            return;
        }

        Notification::create([
            'user_id' => $recipientId,
            'type' => $type,
            'data' => [
                'log_entry_id' => $comment->log_entry_id,
                'comment_id' => $comment->id,
                'parent_comment_id' => $comment->parent_comment_id,
                'from_user_id' => $comment->user_id,
            ],
        ]);
    }
}
