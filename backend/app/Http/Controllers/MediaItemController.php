<?php

namespace App\Http\Controllers;

use App\Enums\MediaSource;
use App\Http\Requests\SearchMediaItemsRequest;
use App\Http\Requests\StoreManualMediaItemRequest;
use App\Http\Requests\StoreMediaItemRequest;
use App\Http\Resources\MediaItemResource;
use App\Http\Resources\SearchResultResource;
use App\Models\MediaItem;
use App\Services\MediaCatalog\MediaItemCataloger;
use App\Services\MediaSources\MediaSourceResolver;
use Illuminate\Http\JsonResponse;
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
}
