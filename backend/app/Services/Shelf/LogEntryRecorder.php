<?php

namespace App\Services\Shelf;

use App\Enums\ShelfStatus;
use App\Events\UserTagged;
use App\Models\LogEntry;
use App\Models\User;
use App\Models\UserMedia;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The core "log a completion" flow: finds-or-creates the Shelf Entry and
 * creates the Log Entry in one transaction. A Shelf Entry's status is only
 * ever auto-set to Completed when this action is what creates it; an
 * existing Shelf Entry's status is never touched here. See
 * docs/adr/0003-shelf-status-and-logging-are-decoupled.md.
 */
class LogEntryRecorder
{
    private const UNIQUE_VIOLATION = '23505';

    /**
     * @param  array<int, int>  $taggedUserIds
     */
    public function record(
        User $user,
        int $mediaItemId,
        ?int $episodeId,
        ?int $seasonNumber,
        string $loggedDate,
        ?float $rating,
        ?string $review,
        bool $containsSpoilers,
        ?bool $isFavourite,
        ?bool $isRewatchOverride,
        array $taggedUserIds,
    ): LogEntry {
        return DB::transaction(function () use (
            $user, $mediaItemId, $episodeId, $seasonNumber, $loggedDate, $rating, $review,
            $containsSpoilers, $isFavourite, $isRewatchOverride, $taggedUserIds
        ) {
            $userMedia = $this->findOrCreateShelfEntry($user->id, $mediaItemId, $isFavourite);

            $isRewatch = $isRewatchOverride
                ?? $this->priorEntryExistsForTarget($userMedia->id, $episodeId, $seasonNumber);

            $logEntry = LogEntry::create([
                'user_media_id' => $userMedia->id,
                'episode_id' => $episodeId,
                'season_number' => $seasonNumber,
                'logged_date' => $loggedDate,
                'rating' => $rating,
                'review' => $review,
                'contains_spoilers' => $containsSpoilers,
                'is_rewatch' => $isRewatch,
            ]);

            if ($taggedUserIds !== []) {
                $logEntry->participants()->attach(array_unique($taggedUserIds));
                $logEntry->load('participants');

                foreach ($logEntry->participants as $participant) {
                    UserTagged::dispatch($logEntry, $participant, $user);
                }

                return $logEntry;
            }

            return $logEntry->load('participants');
        });
    }

    /**
     * A rewatch is scoped to the exact same Logging Target, never "does this
     * logically overlap with past entries." Finishing a whole show is a
     * different target from, and never a rewatch relative to, a season- or
     * episode-level entry made under it. See GLOSSARY.md "Logging Target"
     * and docs/planning/04-data-model.md.
     */
    private function priorEntryExistsForTarget(int $userMediaId, ?int $episodeId, ?int $seasonNumber): bool
    {
        return LogEntry::where('user_media_id', $userMediaId)
            ->when(
                $episodeId !== null,
                fn ($query) => $query->where('episode_id', $episodeId),
                fn ($query) => $query->whereNull('episode_id')->where('season_number', $seasonNumber)
            )
            ->exists();
    }

    private function findOrCreateShelfEntry(int $userId, int $mediaItemId, ?bool $isFavourite): UserMedia
    {
        $existing = UserMedia::withTrashed()
            ->where('user_id', $userId)
            ->where('media_item_id', $mediaItemId)
            ->first();

        // No active (or previously-removed) shelf entry at all: this log
        // action is what creates it, so it starts Completed, same as a
        // soft-deleted one being brought back by logging it again.
        if ($existing === null || $existing->trashed()) {
            $attributes = [
                'user_id' => $userId,
                'media_item_id' => $mediaItemId,
                'status' => ShelfStatus::Completed,
                'is_favourite' => $isFavourite ?? false,
            ];

            if ($existing === null) {
                try {
                    // Nested DB::transaction() (we're already inside the
                    // outer one from record()) so a unique-violation here
                    // only rolls back to a savepoint, not the whole
                    // transaction, letting us recover and keep going.
                    return DB::transaction(fn () => UserMedia::create($attributes));
                } catch (QueryException $e) {
                    if ($e->getCode() !== self::UNIQUE_VIOLATION) {
                        throw $e;
                    }

                    return UserMedia::withTrashed()
                        ->where('user_id', $userId)
                        ->where('media_item_id', $mediaItemId)
                        ->firstOrFail();
                }
            }

            $existing->fill($attributes);
            $existing->restore();

            return $existing;
        }

        // An active shelf entry already exists: its status is left
        // untouched regardless of what this log entry turns out to be
        // (e.g. logging season 1 of an already-Dropped show never flips it
        // back to Completed) — only favourite can be updated here.
        if ($isFavourite !== null) {
            $existing->is_favourite = $isFavourite;
            $existing->save();
        }

        return $existing;
    }
}
