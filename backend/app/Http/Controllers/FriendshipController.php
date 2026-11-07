<?php

namespace App\Http\Controllers;

use App\Enums\FriendshipStatus;
use App\Http\Requests\StoreFriendshipRequest;
use App\Http\Resources\FriendshipResource;
use App\Models\Friendship;
use App\Services\Friends\FriendshipManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class FriendshipController extends Controller
{
    /** Every Friendship row involving the caller, in either direction, filterable by status. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(FriendshipStatus::class)],
        ]);

        $query = Friendship::query()
            ->where(function ($query) use ($request) {
                $query->where('requester_id', $request->user()->id)->orWhere('addressee_id', $request->user()->id);
            })
            ->with(['requester', 'addressee']);

        if (array_key_exists('status', $validated)) {
            $query->where('status', $validated['status']);
        }

        return FriendshipResource::collection($query->get());
    }

    public function store(StoreFriendshipRequest $request, FriendshipManager $manager): JsonResponse
    {
        $result = $manager->sendRequest($request->user()->id, $request->addresseeId());

        return FriendshipResource::make($result->friendship->load(['requester', 'addressee']))
            ->response()
            ->setStatusCode($result->wasNew ? 201 : 200);
    }

    public function accept(Friendship $friendship): FriendshipResource
    {
        $this->authorize('respond', $friendship);

        abort_if($friendship->status !== FriendshipStatus::Pending, 409, 'This friend request is no longer pending.');

        $friendship->fill(['status' => FriendshipStatus::Accepted, 'responded_at' => now()]);
        $friendship->save();

        return FriendshipResource::make($friendship->load(['requester', 'addressee']));
    }

    public function decline(Friendship $friendship): JsonResponse
    {
        $this->authorize('respond', $friendship);

        abort_if($friendship->status !== FriendshipStatus::Pending, 409, 'This friend request is no longer pending.');

        $friendship->fill(['status' => FriendshipStatus::Declined, 'responded_at' => now()]);
        $friendship->save();
        $friendship->delete();

        return response()->json(status: 204);
    }

    public function destroy(Friendship $friendship): JsonResponse
    {
        $this->authorize('manage', $friendship);

        abort_if($friendship->status !== FriendshipStatus::Accepted, 409, 'Only an accepted friendship can be removed.');

        $friendship->delete();

        return response()->json(status: 204);
    }
}
