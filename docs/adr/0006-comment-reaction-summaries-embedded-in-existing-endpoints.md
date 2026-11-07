# Comment/Reaction summaries are embedded in existing log-entry endpoints, not left for the feed UI to assemble

**Status:** accepted

Phase 6's checklist only calls for new `comments` and `reactions` tables and their own dedicated endpoints. The obvious reading of that scope is to stop there and let whichever phase builds the feed UI (Phase 9) decide how to fetch comment counts and reaction summaries against those new endpoints. We rejected that: Phase 2 and Phase 5 each shipped with a real N+1 that only surfaced in code review (store() hitting the external API for already-cataloged items; one query per friend in New from Friends), and a feed/diary UI that has to make a side call per rendered Log Entry to learn its comment count and reaction state is the same shape of problem, just deferred to whoever builds Phase 9 instead of caught now.

So Phase 6 also touches the Activity Feed, New from Friends, and item-detail diary endpoints (all shipped in Phases 3-5) to add a `comment_count` and a condensed reaction summary (total count + the viewer's own Reaction(s)) directly onto each Log Entry's serialization. `comment_count` includes soft-deleted ("[deleted]") Comments, since it's meant to reflect the thread's actual size as a reader would experience it, not just its live content.

The full per-emoji breakdown and reactor list (who reacted with what) stays off these list endpoints and lives only on the dedicated `GET /api/log-entries/{id}/reactions` endpoint, fetched when a single Review is opened. Embedding that full shape on every list item would mean a reactor list per entry across a feed that may hold dozens of Log Entries, for data only worth seeing once a user has actually opened a given Review.
