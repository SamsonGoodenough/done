<?php

namespace App\Services\Friends;

use App\Enums\FriendshipStatus;
use App\Events\FriendRequestAccepted;
use App\Events\FriendRequestSent;
use App\Models\Friendship;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Resolves a friend request against whatever already exists between the two
 * users, including a crossing request in the opposite direction, which this
 * auto-accepts instead of creating a second pending row. A previously
 * declined or unfriended pair (soft-deleted) starts over clean, no
 * cooldown. See GLOSSARY.md "Friendship" and docs/planning/04-data-model.md.
 */
class FriendshipManager
{
    private const UNIQUE_VIOLATION = '23505';

    public function sendRequest(int $requesterId, int $addresseeId): FriendshipResult
    {
        return DB::transaction(fn () => $this->resolve($requesterId, $addresseeId));
    }

    /**
     * The reverse/forward/trashed checks below are a read-then-write
     * sequence with no row to lock on the common "nothing exists yet"
     * path, so without this they're vulnerable to a genuine crossing
     * request arriving on two connections at once: both could read "no
     * reverse row yet" and each create their own pending row instead of
     * one of them auto-accepting the other. A Postgres advisory lock keyed
     * by the (lower id, higher id) pair, held for the transaction, fully
     * serializes concurrent calls for the same two users. It's a no-op
     * under sqlite (the test connection), which doesn't exercise true
     * concurrency anyway.
     */
    private function resolve(int $requesterId, int $addresseeId): FriendshipResult
    {
        if (DB::getDriverName() === 'pgsql') {
            $lockKeys = $requesterId < $addresseeId ? [$requesterId, $addresseeId] : [$addresseeId, $requesterId];
            DB::select('select pg_advisory_xact_lock(?, ?)', $lockKeys);
        }

        $reverse = Friendship::where('requester_id', $addresseeId)
            ->where('addressee_id', $requesterId)
            ->first();

        if ($reverse !== null && $reverse->status === FriendshipStatus::Pending) {
            $reverse->fill(['status' => FriendshipStatus::Accepted, 'responded_at' => now()]);
            $reverse->save();

            FriendRequestAccepted::dispatch($reverse);

            return new FriendshipResult($reverse, wasNew: false);
        }

        if ($reverse !== null && $reverse->status === FriendshipStatus::Accepted) {
            return new FriendshipResult($reverse, wasNew: false);
        }

        $forward = Friendship::where('requester_id', $requesterId)
            ->where('addressee_id', $addresseeId)
            ->first();

        if ($forward !== null) {
            // Already pending or already accepted in this exact direction:
            // POST here is "ensure a request exists," not an upsert.
            return new FriendshipResult($forward, wasNew: false);
        }

        $trashed = Friendship::onlyTrashed()
            ->where('requester_id', $requesterId)
            ->where('addressee_id', $addresseeId)
            ->first();

        if ($trashed !== null) {
            $trashed->fill(['status' => FriendshipStatus::Pending, 'responded_at' => null]);
            $trashed->restore();

            FriendRequestSent::dispatch($trashed);

            return new FriendshipResult($trashed, wasNew: true);
        }

        try {
            $created = Friendship::create([
                'requester_id' => $requesterId,
                'addressee_id' => $addresseeId,
                'status' => FriendshipStatus::Pending,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() !== self::UNIQUE_VIOLATION) {
                throw $e;
            }

            // Lost a create-create race to a concurrent request; the row
            // that won is exactly what we'd have returned anyway, and its
            // own path already dispatched FriendRequestSent.
            $created = Friendship::where('requester_id', $requesterId)
                ->where('addressee_id', $addresseeId)
                ->firstOrFail();

            return new FriendshipResult($created, wasNew: false);
        }

        FriendRequestSent::dispatch($created);

        return new FriendshipResult($created, wasNew: true);
    }
}
