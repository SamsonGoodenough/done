<?php

namespace App\Enums;

enum ShelfStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Dropped = 'dropped';
    case OnHold = 'on_hold';
}
