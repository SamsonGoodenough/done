<?php

namespace App\Services\Reactions;

use App\Enums\ReactionEmoji;
use App\Events\ReactionAdded;
use App\Models\LogEntry;
use App\Models\Reaction;
use App\Models\User;

class ReactionManager
{
    /**
     * Idempotent: a repeat of the same emoji from the same user is a no-op,
     * not an error, since a Reaction is atomic (see GLOSSARY.md
     * "Reaction"). Only dispatches ReactionAdded when it actually created a
     * row — nothing new to notify about on a repeat.
     */
    public function react(User $user, LogEntry $logEntry, ReactionEmoji $emoji): Reaction
    {
        $reaction = Reaction::firstOrCreate([
            'log_entry_id' => $logEntry->id,
            'user_id' => $user->id,
            'emoji' => $emoji,
        ]);

        if ($reaction->wasRecentlyCreated) {
            ReactionAdded::dispatch($reaction);
        }

        return $reaction;
    }

    /**
     * Also idempotent: removing a Reaction that was never there is a no-op,
     * not a 404 — the caller only ever asks to remove their own.
     */
    public function unreact(User $user, LogEntry $logEntry, ReactionEmoji $emoji): void
    {
        Reaction::query()
            ->where('log_entry_id', $logEntry->id)
            ->where('user_id', $user->id)
            ->where('emoji', $emoji)
            ->delete();
    }
}
