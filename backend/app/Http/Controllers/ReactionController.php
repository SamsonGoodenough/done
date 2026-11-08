<?php

namespace App\Http\Controllers;

use App\Enums\ReactionEmoji;
use App\Http\Requests\StoreReactionRequest;
use App\Http\Resources\ReactionSummaryResource;
use App\Models\LogEntry;
use App\Services\Reactions\ReactionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReactionController extends Controller
{
    /**
     * The full Reaction breakdown for this Log Entry's Review: counts per
     * emoji, who reacted with each, and the viewer's own Reaction(s). See
     * ADR 0006 for why this full shape is reserved for this endpoint
     * rather than embedded on every list item.
     */
    public function index(LogEntry $logEntry): ReactionSummaryResource
    {
        $this->authorize('viewPrivateContent', $logEntry->userMedia->user);

        $logEntry->load('reactions.user');

        return ReactionSummaryResource::make($logEntry);
    }

    /**
     * No $this->authorize() call here: visibility is checked in
     * StoreReactionRequest::authorize() instead, which Laravel runs before
     * validation, so an unauthorized stranger gets a 403 rather than a 422
     * that would leak whether the log entry exists or has a review.
     */
    public function store(StoreReactionRequest $request, LogEntry $logEntry, ReactionManager $reactions): JsonResponse
    {
        $reactions->react($request->user(), $logEntry, $request->emoji());

        $logEntry->load('reactions.user');

        return ReactionSummaryResource::make($logEntry)->response()->setStatusCode(201);
    }

    /**
     * Removing your own Reaction. Idempotent (204 whether or not it was
     * there) — there's no moderation concept for Reactions, only for
     * Comments, so this only ever touches the caller's own.
     */
    public function destroy(Request $request, LogEntry $logEntry, ReactionEmoji $emoji, ReactionManager $reactions): JsonResponse
    {
        $this->authorize('viewPrivateContent', $logEntry->userMedia->user);

        $reactions->unreact($request->user(), $logEntry, $emoji);

        return response()->json(status: 204);
    }
}
