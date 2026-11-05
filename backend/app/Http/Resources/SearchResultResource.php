<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps a stdClass{result: SearchResult, media_item_id: ?int} pair — see
 * MediaItemController::search(). `media_item_id` lets a future frontend
 * show "already in catalog" instead of a duplicate add button, per the
 * search-and-add flow decision in docs/adr/0001-media-source-integration-architecture.md.
 */
class SearchResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $result = $this->result;

        return [
            'type' => $result->type,
            'source' => $result->source,
            'external_id' => $result->externalId,
            'title' => $result->title,
            'release_date' => $result->releaseDate,
            'poster_url' => $result->posterUrl,
            'overview' => $result->overview,
            'media_item_id' => $this->media_item_id,
        ];
    }
}
