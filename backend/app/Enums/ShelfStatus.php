<?php

namespace App\Enums;

enum ShelfStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Dropped = 'dropped';
    case OnHold = 'on_hold';

    /**
     * Completed is only reachable via logging, never a direct status write.
     * See docs/adr/0003-shelf-status-and-logging-are-decoupled.md. The
     * single source of truth for StoreUserMediaRequest and
     * UpdateUserMediaRequest, so the invariant can't drift between them.
     *
     * @return array<int, string>
     */
    public static function directlySettable(): array
    {
        return array_map(
            fn (self $status) => $status->value,
            array_filter(self::cases(), fn (self $status) => $status !== self::Completed)
        );
    }
}
