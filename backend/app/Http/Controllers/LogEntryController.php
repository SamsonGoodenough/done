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
            $request->loggedDate(),
            $request->rating(),
            $request->review(),
            $request->containsSpoilers(),
            $request->isFavourite(),
            $request->isRewatchOverride(),
            $request->taggedUserIds(),
        );

        return LogEntryResource::make($logEntry)->response()->setStatusCode(201);
    }
}
