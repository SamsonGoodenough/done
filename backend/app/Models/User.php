<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ProfileVisibility;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'profile_visibility'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'profile_visibility' => ProfileVisibility::class,
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by');
    }

    /**
     * The Shelf: this user's current relationship to each Media Item. See
     * GLOSSARY.md "Shelf Entry".
     *
     * @return HasMany<UserMedia, $this>
     */
    public function shelf(): HasMany
    {
        return $this->hasMany(UserMedia::class);
    }

    /**
     * The diary: every Log Entry this user has made, across their whole
     * Shelf. Ordered most-recently-created first, which is what an activity
     * surface wants, not `logged_date`, since that's the in-universe date
     * of the event and can be backdated independent of when the entry was
     * actually created. See GLOSSARY.md "Activity Feed".
     *
     * @return HasManyThrough<LogEntry, UserMedia, $this>
     */
    public function logEntries(): HasManyThrough
    {
        return $this->hasManyThrough(LogEntry::class, UserMedia::class)->orderByDesc('log_entries.created_at')->orderByDesc('log_entries.id');
    }
}
