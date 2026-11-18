<?php

namespace App\Http\Controllers;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Enums\ProfileVisibility;
use App\Http\Requests\SearchMediaItemsRequest;
use App\Http\Requests\StoreManualMediaItemRequest;
use App\Http\Requests\StoreMediaItemRequest;
use App\Http\Resources\ActivityEntryResource;
use App\Http\Resources\EpisodeResource;
use App\Http\Resources\LogEntryResource;
use App\Http\Resources\MediaItemResource;
use App\Http\Resources\SearchResultResource;
use App\Models\LogEntry;
use App\Models\MediaItem;
use App\Models\User;
use App\Services\Episodes\EpisodeSyncer;
use App\Services\Friends\FriendGraph;
use App\Services\MediaCatalog\MediaItemCataloger;
use App\Services\MediaSources\MediaSourceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MediaItemController extends Controller
{
    private const REVIEW_LIMIT = 100;

    public function __construct(
        private readonly MediaSourceResolver $resolver,
        private readonly MediaItemCataloger $cataloger,
    ) {}

    public function search(SearchMediaItemsRequest $request): AnonymousResourceCollection
    {
        $type = $request->type();
        $results = $this->resolver->forType($type)->search($request->searchQuery(), $type);

        if ($results === []) {
            return SearchResultResource::collection([]);
        }

        $catalogedIds = MediaItem::query()
            ->where('type', $type->value)
            ->where('source', $results[0]->source->value)
            ->whereIn('external_id', array_map(fn ($result) => $result->externalId, $results))
            ->pluck('id', 'external_id');

        return SearchResultResource::collection(array_map(
            fn ($result) => (object) [
                'result' => $result,
                'media_item_id' => $catalogedIds[$result->externalId] ?? null,
            ],
            $results
        ));
    }

    public function store(StoreMediaItemRequest $request): JsonResponse
    {
        $type = $request->type();

        // Already-cataloged items are the common case on a retry or
        // double-submit; check locally before paying for an external API
        // round trip whose result MediaItemCataloger would just discard.
        // A soft-deleted match isn't excluded here (Eloquent's default
        // scope already hides it), so it still falls through to the full
        // fetch-and-restore path below.
        $existing = MediaItem::query()
            ->where('type', $type->value)
            ->where('source', $this->resolver->sourceForType($type)->value)
            ->where('external_id', $request->externalId())
            ->first();

        if ($existing !== null) {
            return MediaItemResource::make($existing)->response()->setStatusCode(200);
        }

        $details = $this->resolver->forType($type)->details($request->externalId(), $type);

        $result = $this->cataloger->catalogFromSource($details);

        return MediaItemResource::make($result->mediaItem)
            ->response()
            ->setStatusCode($result->wasNew ? 201 : 200);
    }

    public function storeManual(StoreManualMediaItemRequest $request): JsonResponse
    {
        $mediaItem = MediaItem::create([
            'type' => $request->type(),
            'source' => MediaSource::Manual,
            'external_id' => null,
            'title' => $request->string('title')->value(),
            'release_date' => $request->input('release_date'),
            'poster_url' => $request->input('poster_url'),
            'overview' => $request->input('overview'),
            'metadata' => $request->input('metadata', []),
            'added_by' => $request->user()->id,
        ]);

        return MediaItemResource::make($mediaItem)->response()->setStatusCode(201);
    }

    public function show(MediaItem $mediaItem): MediaItemResource
    {
        return MediaItemResource::make($mediaItem);
    }

    /**
     * The item detail page's diary. Scoped to the caller's own Log Entries
     * only — Phase 5 doesn't exist yet, so there's no visibility
     * infrastructure to show anyone else's.
     */
    public function logEntries(MediaItem $mediaItem, Request $request): AnonymousResourceCollection
    {
        $viewerId = $request->user()->id;

        $entries = LogEntry::query()
            ->whereHas('userMedia', function ($query) use ($mediaItem, $request) {
                $query->where('media_item_id', $mediaItem->id)
                    ->where('user_id', $request->user()->id);
            })
            ->with('participants')
            ->withCount([
                'comments as comment_count' => fn ($query) => $query->withTrashed(),
                'reactions as reaction_count',
            ])
            ->with(['reactions' => fn ($query) => $query->where('user_id', $viewerId)])
            ->orderByDesc('logged_date')
            ->get();

        return LogEntryResource::collection($entries);
    }

    /**
     * The community view of an item: every reviewed Log Entry the viewer is
     * allowed to see, plus the average rating across the same set of
     * members. "Allowed to see" is the same rule as everywhere else
     * (UserPolicy::viewPrivateContent): the viewer themselves, Public
     * profiles, and accepted friends of a Friends-only profile.
     *
     * The average counts one vote per member: their most recent rated
     * whole-item Log Entry (no episode, no season), so a rewatch replaces
     * the old rating instead of double-counting, and one episode's rating
     * never leaks into a show's score.
     */
    public function reviews(MediaItem $mediaItem, Request $request, FriendGraph $friendGraph): AnonymousResourceCollection
    {
        $viewer = $request->user();
        $friendIds = $friendGraph->friendIds($viewer->id);

        // A subquery, not a plucked id list: the set is resolved in the
        // database for both reads below.
        $visibleUsers = User::query()
            ->select('id')
            ->where(function ($query) use ($viewer, $friendIds) {
                $query->where('id', $viewer->id)
                    ->orWhere('profile_visibility', ProfileVisibility::Public)
                    ->orWhereIn('id', $friendIds);
            });

        // Newest first, capped: this is a page section, not an archive.
        $reviews = LogEntry::query()
            ->whereHas('userMedia', function ($query) use ($mediaItem, $visibleUsers) {
                $query->where('media_item_id', $mediaItem->id)
                    ->whereIn('user_id', $visibleUsers);
            })
            ->whereNotNull('review')
            ->where('review', '!=', '')
            ->with(['userMedia.user', 'userMedia.mediaItem', 'participants'])
            ->withCount([
                'comments as comment_count' => fn ($query) => $query->withTrashed(),
                'reactions as reaction_count',
            ])
            ->with(['reactions' => fn ($query) => $query->where('user_id', $viewer->id)])
            ->orderByDesc('logged_date')
            ->orderByDesc('id')
            ->limit(self::REVIEW_LIMIT)
            ->get();

        // Each member's newest rated whole-item entry, ranked with a window
        // function (portable across Postgres and sqlite, same approach as
        // ActivityController::newFromFriends), then averaged in SQL.
        $ranked = DB::table('log_entries')
            ->join('user_media', 'user_media.id', '=', 'log_entries.user_media_id')
            ->where('user_media.media_item_id', $mediaItem->id)
            ->whereIn('user_media.user_id', $visibleUsers)
            ->whereNull('log_entries.deleted_at')
            ->whereNull('user_media.deleted_at')
            ->whereNotNull('log_entries.rating')
            ->whereNull('log_entries.episode_id')
            ->whereNull('log_entries.season_number')
            ->selectRaw('log_entries.rating as rating, row_number() over (partition by user_media.user_id order by log_entries.logged_date desc, log_entries.id desc) as rn');

        $stats = DB::query()
            ->fromSub($ranked, 'ranked')
            ->where('rn', 1)
            ->selectRaw('count(*) as rating_count, avg(rating) as average_rating')
            ->first();

        return ActivityEntryResource::collection($reviews)->additional([
            'meta' => [
                'average_rating' => $stats->rating_count > 0 ? round((float) $stats->average_rating, 1) : null,
                'rating_count' => (int) $stats->rating_count,
            ],
        ]);
    }

    /**
     * A show's episode list, synced from its source on first request. See
     * docs/adr/0004-episode-sync-is-lazy-and-self-refreshing.md.
     */
    public function episodes(MediaItem $mediaItem, EpisodeSyncer $syncer): AnonymousResourceCollection
    {
        if ($mediaItem->type !== MediaType::TvShow) {
            abort(422, 'Only a tv_show media item has episodes.');
        }

        return EpisodeResource::collection($syncer->episodesFor($mediaItem));
    }
}
