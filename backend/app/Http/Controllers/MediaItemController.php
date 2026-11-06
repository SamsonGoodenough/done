<?php

namespace App\Http\Controllers;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Http\Requests\SearchMediaItemsRequest;
use App\Http\Requests\StoreManualMediaItemRequest;
use App\Http\Requests\StoreMediaItemRequest;
use App\Http\Resources\EpisodeResource;
use App\Http\Resources\LogEntryResource;
use App\Http\Resources\MediaItemResource;
use App\Http\Resources\SearchResultResource;
use App\Models\LogEntry;
use App\Models\MediaItem;
use App\Services\Episodes\EpisodeSyncer;
use App\Services\MediaCatalog\MediaItemCataloger;
use App\Services\MediaSources\MediaSourceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MediaItemController extends Controller
{
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
        $entries = LogEntry::query()
            ->whereHas('userMedia', function ($query) use ($mediaItem, $request) {
                $query->where('media_item_id', $mediaItem->id)
                    ->where('user_id', $request->user()->id);
            })
            ->with('participants')
            ->orderByDesc('logged_date')
            ->get();

        return LogEntryResource::collection($entries);
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
