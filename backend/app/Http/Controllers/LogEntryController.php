<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLogEntryRequest;
use App\Http\Resources\LogEntryResource;
use App\Services\Shelf\LogEntryRecorder;
use Illuminate\Http\JsonResponse;

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
}
