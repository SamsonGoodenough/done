# Activity Feed and New from Friends surface every Log Entry, not just rated/reviewed ones

**Status:** accepted

The obvious design for a social activity surface is to show only Log Entries with a rating or review attached, treating a bare "watched, no rating" entry as too thin to be worth surfacing. We rejected that: some friends in the target user base consistently log without rating, and seeing that they watched something is itself the value, not a side effect of them also reviewing it. Both the Activity Feed and New from Friends show every Log Entry regardless of rating/review, with no filtering by content richness.

This has one accepted cost: a show logged episode-by-episode posts one feed item per entry, with no rollup or burst-grouping. That's deliberately deferred rather than designed around, since nothing has demonstrated it's actually a problem yet.

New from Friends (one entry per friend: their single most recent Log Entry, sorted by recency, no staleness cutoff) is a separate query from the Activity Feed rather than a client-side filter over it. Deriving it from the paginated feed would silently drop a friend who hasn't logged recently once their entry falls off the page, which defeats the section's purpose: showing every friend's latest, however old. It only counts entries where the friend is the logger (`log_entries.user_id`), never one where they're tagged as a participant, consistent with Tag Friends being purely informational and never surfacing on the tagged user's own activity.
