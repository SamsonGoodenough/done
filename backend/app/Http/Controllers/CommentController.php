<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\LogEntry;
use App\Services\Comments\CommentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommentController extends Controller
{
    /**
     * A flat, created_at-ordered list of every Comment on this Log Entry's
     * Review, including soft-deleted ones (the client reconstructs the
     * thread from parent_comment_id and renders a deleted one as
     * "[deleted]"). See GLOSSARY.md "Comment".
     *
     * `id` is a tiebreaker for comments created in the same instant (second-
     * precision timestamps), so render order stays deterministic instead of
     * depending on the query planner — same reasoning as
     * ActivityController::newFromFriends's `id DESC` tiebreak.
     */
    public function index(LogEntry $logEntry): AnonymousResourceCollection
    {
        $this->authorize('viewPrivateContent', $logEntry->userMedia->user);

        $comments = $logEntry->comments()
            ->withTrashed()
            ->with('user')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return CommentResource::collection($comments);
    }

    /**
     * No $this->authorize() call here: visibility is checked in
     * StoreCommentRequest::authorize() instead, which Laravel runs before
     * validation, so an unauthorized stranger gets a 403 rather than a 422
     * that would leak whether the log entry exists or has a review.
     */
    public function store(StoreCommentRequest $request, LogEntry $logEntry, CommentManager $comments): JsonResponse
    {
        $comment = $comments->create($request->user(), $logEntry, $request->parentCommentId(), $request->body());

        return CommentResource::make($comment->load('user'))->response()->setStatusCode(201);
    }

    /**
     * Always a soft-delete, never a hard delete, so replies survive. See
     * CommentPolicy for who may delete another user's Comment
     * (Moderation).
     */
    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return response()->json(status: 204);
    }
}
