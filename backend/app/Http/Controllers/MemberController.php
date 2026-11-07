<?php

namespace App\Http\Controllers;

use App\Enums\FriendshipStatus;
use App\Http\Requests\SearchMembersRequest;
use App\Http\Resources\MemberProfileResource;
use App\Http\Resources\MemberResource;
use App\Models\Friendship;
use App\Models\User;
use App\Services\Friends\FriendGraph;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MemberController extends Controller
{
    /**
     * Every other member, optionally filtered by name, with the caller's
     * own relationship to each attached. Discoverability isn't gated by
     * Profile Visibility, see GLOSSARY.md "Profile Visibility".
     */
    public function search(SearchMembersRequest $request): AnonymousResourceCollection
    {
        $viewer = $request->user();

        $query = User::query()->where('id', '!=', $viewer->id)->orderBy('name');

        if ($request->searchQuery() !== null) {
            // whereRaw('LOWER(...) LIKE ?') rather than 'ilike' (Postgres-only)
            // so this also runs against the sqlite connection tests use.
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($request->searchQuery()).'%']);
        }

        $members = $query->get();

        $friendshipsByOtherUserId = Friendship::query()
            ->where(function ($query) use ($viewer) {
                $query->where('requester_id', $viewer->id)->orWhere('addressee_id', $viewer->id);
            })
            ->get()
            ->keyBy(fn (Friendship $friendship) => $friendship->requester_id === $viewer->id
                ? $friendship->addressee_id
                : $friendship->requester_id);

        foreach ($members as $member) {
            $member->relationship_status = $this->relationshipStatus(
                $viewer->id,
                $friendshipsByOtherUserId->get($member->id)
            );
        }

        return MemberResource::collection($members);
    }

    /**
     * A member's profile: basic info and relationship status are always
     * shown, shelf/diary/friends are only attached when the viewer is
     * allowed to see this member's private content. See
     * UserPolicy::viewPrivateContent and GLOSSARY.md "Profile Visibility".
     */
    public function show(Request $request, User $user, FriendGraph $friendGraph): MemberProfileResource
    {
        $viewer = $request->user();

        $canView = $viewer->can('viewPrivateContent', $user);

        $user->relationship_status = $this->relationshipStatus($viewer->id, Friendship::between($viewer->id, $user->id)->first());
        $user->can_view_content = $canView;

        if ($canView) {
            $user->load(['shelf.mediaItem', 'logEntries.participants']);
            $user->setAttribute('friendsList', $friendGraph->friendsOf($user));
        }

        return MemberProfileResource::make($user);
    }

    private function relationshipStatus(int $viewerId, ?Friendship $friendship): ?string
    {
        if ($friendship === null) {
            return null;
        }

        if ($friendship->status === FriendshipStatus::Accepted) {
            return 'friends';
        }

        // Only Pending can reach here: a Declined row is soft-deleted
        // immediately, so the default query scope already excludes it.
        return $friendship->requester_id === $viewerId ? 'pending_sent' : 'pending_received';
    }
}
