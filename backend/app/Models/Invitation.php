<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['email', 'token', 'role', 'invited_by', 'expires_at', 'accepted_at'])]
#[Hidden(['token'])]
class Invitation extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * A fresh random token. The raw value is only ever held in memory (to
     * put in the emailed link); hashToken() is what gets stored.
     */
    public static function generateRawToken(): string
    {
        return Str::random(40);
    }

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public static function findByRawToken(string $rawToken): ?self
    {
        return static::where('token', static::hashToken($rawToken))->first();
    }

    public function hasBeenAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** Not expired and not yet accepted. */
    public function isPending(): bool
    {
        return ! $this->hasBeenAccepted() && ! $this->hasExpired();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }
}
