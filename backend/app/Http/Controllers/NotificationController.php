<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

class NotificationController extends Controller
{
    /** Every Notification belonging to the caller, most recent first. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $this->attachActors($notifications);

        return NotificationResource::collection($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)->whereNull('read_at')->count();

        return response()->json(['count' => $count]);
    }

    public function markRead(Notification $notification): NotificationResource
    {
        $this->authorize('markRead', $notification);

        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }

        $this->attachActors(collect([$notification]));

        return NotificationResource::make($notification);
    }

    /** Every unread Notification belonging to the caller, marked read in one query. */
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(status: 204);
    }

    /**
     * Resolves each Notification's `from_user_id` (inside its JSON `data`
     * payload, not a foreign key column) into an `actor` relation in one
     * bulk query, to avoid an N+1 across the list.
     *
     * @param  Collection<int, Notification>  $notifications
     */
    private function attachActors(Collection $notifications): void
    {
        $actorIds = $notifications->pluck('data')->pluck('from_user_id')->filter()->unique()->values();
        $actorsById = User::whereIn('id', $actorIds)->get()->keyBy('id');

        $notifications->each(fn (Notification $notification) => $notification->setRelation(
            'actor',
            $actorsById->get($notification->data['from_user_id'] ?? null)
        ));
    }
}
