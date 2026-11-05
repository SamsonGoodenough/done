<?php

namespace App\Models;

use App\Enums\ShelfStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A Shelf Entry: a user's current relationship to one Media Item (status,
 * favourite), independent of their diary history. See GLOSSARY.md and
 * docs/planning/04-data-model.md.
 */
#[Fillable(['user_id', 'media_item_id', 'status', 'is_favourite'])]
class UserMedia extends Model
{
    use SoftDeletes;

    protected $table = 'user_media';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShelfStatus::class,
            'is_favourite' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<MediaItem, $this>
     */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class);
    }

    /**
     * @return HasMany<LogEntry, $this>
     */
    public function logEntries(): HasMany
    {
        return $this->hasMany(LogEntry::class);
    }
}
