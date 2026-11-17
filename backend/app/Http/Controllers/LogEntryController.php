<?php

namespace App\Http\Controllers;

use App\Events\UserTagged;
use App\Http\Requests\StoreLogEntryRequest;
use App\Http\Requests\TagParticipantRequest;
use App\Http\Requests\UpdateLogEntryRequest;
use App\Http\Resources\LogEntryResource;
use App\Models\LogEntry;
use App\Models\User;
use App\Services\Shelf\LogEntryRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogEntryController extends Controller
{
    public function store(StoreLogEntryRequest $request, LogEntryRecorder $recorder): JsonResponse
    {
        $logEntry = $recorder->record(
            $request->user(),
            $request->mediaItemId(),
            $request->episodeId(),
            $request->seasonNumber(),
            $request->loggedDate(),
            $request->rating(),
            $request->review(),
            $request->containsSpoilers(),
            $request->isFavourite(),
            $request->isRewatchOverride(),
            $request->taggedUserIds(),
        );

        // A brand-new entry has no Comments/Reactions yet, but the response
        // carries the same comment_count/reaction_summary shape as every
        // other LogEntryResource for consistency with the list endpoints.
        $logEntry->loadCount(['comments as comment_count', 'reactions as reaction_count']);
        $logEntry->load(['reactions' => fn ($query) => $query->where('user_id', $request->user()->id)]);

        return LogEntryResource::make($logEntry)->response()->setStatusCode(201);
    }

    public function update(UpdateLogEntryRequest $request, LogEntry $logEntry, LogEntryRecorder $recorder): LogEntryResource
    {
        $logEntry = $recorder->update($logEntry, $request->changes());

        return LogEntryResource::make($this->loadResourceRelations($logEntry, $request));
    }

    /**
     * Tags a member onto an already-existing entry — idempotent, same as
     * ReactionController::store: re-tagging someone already tagged no-ops
     * rather than erroring, and only dispatches UserTagged for a genuinely
     * new tag.
     */
    public function addParticipant(TagParticipantRequest $request, LogEntry $logEntry): LogEntryResource
    {
        $taggedUserId = $request->taggedUserId();
        $alreadyTagged = $logEntry->participants()->where('user_id', $taggedUserId)->exists();

        $logEntry->participants()->syncWithoutDetaching([$taggedUserId]);

        if (! $alreadyTagged) {
            UserTagged::dispatch($logEntry, User::findOrFail($taggedUserId), $request->user());
        }

        $logEntry->load('participants');

        return LogEntryResource::make($this->loadResourceRelations($logEntry, $request));
    }

    /**
     * Untagging is a hard delete, same as the pivot itself (see
     * log_entry_participants migration) — there's no UserUntagged event,
     * nothing needs to be notified of a tag going away.
     */
    public function removeParticipant(Request $request, LogEntry $logEntry, User $user): LogEntryResource
    {
        $this->authorize('manage', $logEntry);

        $logEntry->participants()->detach($user->id);
        $logEntry->load('participants');

        return LogEntryResource::make($this->loadResourceRelations($logEntry, $request));
    }

    private function loadResourceRelations(LogEntry $logEntry, Request $request): LogEntry
    {
        $logEntry->loadCount(['comments as comment_count', 'reactions as reaction_count']);
        $logEntry->load(['reactions' => fn ($query) => $query->where('user_id', $request->user()->id)]);

        return $logEntry;
    }
}
