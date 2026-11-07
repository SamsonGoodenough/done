<?php

namespace App\Models;

use App\Enums\FriendshipStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A Friendship: one directional request/addressee row. Mutual once
 * `status` is Accepted, checked by OR across both directions, there's no
 * separate reverse row. See GLOSSARY.md "Friendship" and
 * docs/planning/04-data-model.md.
 */
#[Fillable(['requester_id', 'addressee_id', 'status', 'responded_at'])]
class Friendship extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FriendshipStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    /**
     * The user who sent the Friend Request.
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * The user being asked.
     *
     * @return BelongsTo<User, $this>
     */
    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }

    /**
     * Rows involving these two users in either direction: a mutual
     * Friendship has no single "requester is always A" convention, so
     * anything checking "is there a row between these two" needs both
     * orderings.
     *
     * @param  Builder<Friendship>  $query
     * @return Builder<Friendship>
     */
    public function scopeBetween($query, int $userAId, int $userBId)
    {
        return $query->where(function ($query) use ($userAId, $userBId) {
            $query->where('requester_id', $userAId)->where('addressee_id', $userBId);
        })->orWhere(function ($query) use ($userAId, $userBId) {
            $query->where('requester_id', $userBId)->where('addressee_id', $userAId);
        });
    }
}
