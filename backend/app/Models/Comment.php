<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A threaded remark on a Log Entry's Review, optionally a reply to another
 * Comment. Always soft-deleted, never hard-deleted — see GLOSSARY.md
 * "Comment".
 */
#[Fillable(['log_entry_id', 'user_id', 'parent_comment_id', 'body'])]
class Comment extends Model
{
    use SoftDeletes;

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

    /**
     * @return BelongsTo<Comment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_comment_id');
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_comment_id');
    }
}
