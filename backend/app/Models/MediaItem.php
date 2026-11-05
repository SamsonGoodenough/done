<?php

namespace App\Models;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['type', 'source', 'external_id', 'title', 'release_date', 'poster_url', 'overview', 'metadata', 'added_by'])]
class MediaItem extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'source' => MediaSource::class,
            'release_date' => 'date',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
