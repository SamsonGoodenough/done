<?php

namespace App\Http\Controllers;

use App\Http\Resources\ActivityEntryResource;
use App\Models\LogEntry;
use App\Services\Friends\FriendGraph;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ActivityController extends Controller
{
    /**
     * The caller's accepted friends' Log Entries, every one regardless of
     * rating/review, most-recently-created first. Falls back to the
     * caller's own Log Entries (same composition) when they have no
     * friends yet. See GLOSSARY.md "Activity Feed".
     */
    public function feed(Request $request, FriendGraph $friendGraph): AnonymousResourceCollection
    {
        $viewer = $request->user();
        $friendIds = $friendGraph->friendIds($viewer->id);

        $loggerIds = $friendIds->isEmpty() ? collect([$viewer->id]) : $friendIds;

        $entries = LogEntry::query()
            ->whereHas('userMedia', fn ($query) => $query->whereIn('user_id', $loggerIds))
            ->with(['userMedia.user', 'userMedia.mediaItem', 'participants'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return ActivityEntryResource::collection($entries);
    }

    /**
     * One entry per accepted friend: their single most recent Log Entry
     * where they're the logger (never one where they're merely a tagged
     * participant), sorted by recency, no staleness cutoff. A friend with
     * no Log Entries at all just doesn't appear.
     *
     * One query ranks every friend's entries by recency with a window
     * function (portable across Postgres and sqlite, unlike e.g. Postgres's
     * DISTINCT ON) and keeps only the top-ranked row per friend. This
     * avoids both an N+1 (one query per friend) and the classic
     * MAX(created_at)-plus-self-join bug, which can return two rows for a
     * friend whose two entries happen to share the exact same created_at,
     * since that join only matches on the timestamp value, not a unique
     * row. See GLOSSARY.md "New from Friends".
     */
    public function newFromFriends(Request $request, FriendGraph $friendGraph): AnonymousResourceCollection
    {
        $viewer = $request->user();
        $friendIds = $friendGraph->friendIds($viewer->id);

        if ($friendIds->isEmpty()) {
            return ActivityEntryResource::collection([]);
        }

        $ranked = DB::table('log_entries')
            ->join('user_media', 'user_media.id', '=', 'log_entries.user_media_id')
            ->whereIn('user_media.user_id', $friendIds)
            ->whereNull('log_entries.deleted_at')
            ->whereNull('user_media.deleted_at')
            ->selectRaw('log_entries.*, row_number() over (partition by user_media.user_id order by log_entries.created_at desc, log_entries.id desc) as rn');

        $rows = DB::query()->fromSub($ranked, 'ranked')
            ->where('rn', 1)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $entries = LogEntry::hydrate($rows->all())->load(['userMedia.user', 'userMedia.mediaItem', 'participants']);

        return ActivityEntryResource::collection($entries);
    }
}
