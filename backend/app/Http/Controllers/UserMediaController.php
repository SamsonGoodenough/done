<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserMediaRequest;
use App\Http\Requests\UpdateUserMediaRequest;
use App\Http\Resources\UserMediaResource;
use App\Models\UserMedia;
use App\Services\Shelf\ShelfEntryManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserMediaController extends Controller
{
    /** The shelf view: the caller's own Shelf Entries, filterable by status/type/favourite. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string'],
            'type' => ['sometimes', 'string'],
            'favourite' => ['sometimes', 'boolean'],
        ]);

        $query = UserMedia::query()
            ->where('user_id', $request->user()->id)
            ->with('mediaItem');

        if (array_key_exists('status', $validated)) {
            $query->where('status', $validated['status']);
        }

        if (array_key_exists('favourite', $validated)) {
            $query->where('is_favourite', $request->boolean('favourite'));
        }

        if (array_key_exists('type', $validated)) {
            $type = $validated['type'];
            $query->whereHas('mediaItem', fn ($mediaItemQuery) => $mediaItemQuery->where('type', $type));
        }

        return UserMediaResource::collection($query->get());
    }

    public function store(StoreUserMediaRequest $request, ShelfEntryManager $shelfEntryManager): JsonResponse
    {
        $result = $shelfEntryManager->addToShelf(
            $request->user()->id,
            $request->mediaItemId(),
            $request->status(),
            $request->isFavourite(),
        );

        return UserMediaResource::make($result->userMedia->load('mediaItem'))
            ->response()
            ->setStatusCode($result->wasNew ? 201 : 200);
    }

    public function update(UpdateUserMediaRequest $request, UserMedia $userMedia): UserMediaResource
    {
        $userMedia->fill($request->changes());
        $userMedia->save();

        return UserMediaResource::make($userMedia->load('mediaItem'));
    }

    public function destroy(UserMedia $userMedia): JsonResponse
    {
        $this->authorize('manage', $userMedia);

        $userMedia->delete();

        return response()->json(status: 204);
    }
}
