<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A Log Entry: one dated record of finishing (or re-finishing) a Media
 * Item. Create-only in Phase 3 — no edit/delete yet, see
 * docs/planning/09-roadmap.md and GLOSSARY.md.
 */
#[Fillable(['user_media_id', 'logged_date', 'rating', 'review', 'contains_spoilers', 'is_rewatch'])]
class LogEntry extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'logged_date' => 'date',
            'rating' => 'decimal:1',
            'contains_spoilers' => 'boolean',
            'is_rewatch' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<UserMedia, $this>
     */
    public function userMedia(): BelongsTo
    {
        return $this->belongsTo(UserMedia::class);
    }

    /**
     * Tagged members — "Watched with" / "Played with" / "Read with"
     * depending on the item's type. See GLOSSARY.md "Tag Friends" in
     * docs/planning/03-features.md.
     *
     * Deliberately no withTimestamps(): the pivot only has created_at (no
     * updated_at), set by the column's own DB default — see the
     * log_entry_participants migration. Eloquent's withTimestamps() assumes
     * both columns exist, so it's left off entirely rather than fought.
     *
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'log_entry_participants');
    }
}
