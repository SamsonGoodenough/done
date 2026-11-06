<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single Episode of a TV show, synced from TMDB lazily. See GLOSSARY.md
 * and docs/adr/0004-episode-sync-is-lazy-and-self-refreshing.md.
 */
#[Fillable(['media_item_id', 'season_number', 'episode_number', 'title', 'air_date'])]
class Episode extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'air_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<MediaItem, $this>
     */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class);
    }
}
