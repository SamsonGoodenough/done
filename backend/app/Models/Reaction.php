<?php

namespace App\Models;

use App\Enums\ReactionEmoji;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fixed-emoji reaction on a Log Entry's Review. Atomic — no update, only
 * create/delete. No updated_at column, same as the log_entry_participants
 * pivot; created_at is set by the column's own DB default, not managed by
 * Eloquent. See GLOSSARY.md "Reaction".
 */
#[Fillable(['log_entry_id', 'user_id', 'emoji'])]
class Reaction extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'emoji' => ReactionEmoji::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LogEntry, $this>
     */
    public function logEntry(): BelongsTo
    {
        return $this->belongsTo(LogEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
