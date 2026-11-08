<?php

namespace App\Events;

use App\Models\Reaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new Reaction was added. Not fired for a repeat react (ReactionManager's
 * firstOrCreate is idempotent) since there's nothing new to notify about.
 * See ADR 0007.
 */
class ReactionAdded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Reaction $reaction,
    ) {}
}
