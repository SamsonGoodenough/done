# Shelf status and logging are decoupled, except reaching Completed

**Status:** accepted

`03-features.md` says "logging a completed movie you saw last week creates the shelf entry as Completed directly," while `04-data-model.md`'s own example says dropping a TV show after loving season 1 produces "a real log entry... independent of the show's overall shelf status." Taken together literally, these contradict: one implies logging always sets status, the other implies logging never touches status that already exists.

Resolved as: logging a completion auto-sets a Shelf Entry's status to Completed only when that action is what creates the Shelf Entry (no prior row existed). Once a Shelf Entry exists, a new Log Entry never changes its status — status changes are always a separate, explicit action. The companion invariant: the plain shelf-status-change endpoint (planned/in_progress/dropped/on_hold) rejects `completed` outright, so Completed is only ever reachable by actually logging something, and a Shelf Entry marked Completed with zero Log Entries underneath it can never exist.

Worth recording because it's genuinely not obvious from the code alone why logging sometimes changes status and sometimes doesn't, and both alternative readings (always-set, or never-set) were defensible from the planning docs before this was pinned down.
