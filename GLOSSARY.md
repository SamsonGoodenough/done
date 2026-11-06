# Done — Core Domain

A self-hosted media tracker for movies, TV, games, and books. Single bounded context; this glossary covers the whole app.

## Language

**Media Item**:
A single movie, TV show, game, or book in the shared catalog, deduplicated across all users by `(source, external_id)`.
_Avoid_: Title, work, content item

**Catalog**:
The full set of Media Items known to the app, independent of any individual user's shelf. Populated by cataloging items from an external source or by manual entry.
_Avoid_: Library, database

**Source**:
Where a Media Item's metadata originates: `tmdb`, `games_api` (IGDB), `books_api` (Open Library), or `manual`.
_Avoid_: Provider, API

**External ID**:
The identifier a Source uses for a Media Item (e.g. a TMDB movie ID). Null for manually-entered items.

**Cataloging** (Phase 2):
Adding a Media Item to the shared Catalog, either by copying in metadata from an external Source or by manual entry. Distinct from Shelving.
_Avoid_: "Adding an item" on its own — ambiguous with Shelving

**Shelving** (Phase 3):
The act of a user adding a cataloged Media Item to their personal shelf, creating a Shelf Entry. A Media Item can be cataloged with nobody shelving it yet.
_Avoid_: "Adding an item" on its own — ambiguous with Cataloging

**Shelf Entry** (Phase 3):
A user's current relationship to one Media Item — status (planned/in progress/completed/dropped/on-hold) and favourite flag (`user_media`) — independent of their diary history. One per user per Media Item.
_Avoid_: user_media on its own outside schema contexts

**Log Entry** (Phase 3):
One dated record of a user finishing (or re-finishing) a Logging Target: its own date, rating, review, and spoiler flag (`log_entries`). A Shelf Entry can have many Log Entries over time (first watch, a rewatch years later, ...); the diary is the full history of Log Entries for an item.
_Avoid_: "Review" alone (a review is just the optional text field on a Log Entry, not the whole record)

**Logging** (Phase 3):
The act of creating a Log Entry. Distinct from Shelving: logging records a dated completion event, shelving just sets a Shelf Entry's current status and can happen with no Log Entry at all (e.g. marking something Planned).

**Logging Target** (Phase 4):
What a single Log Entry records completion of: the whole Media Item (`episode_id` and `season_number` both null, the only option for movies/games/books), one season of a TV show (`season_number` set, `episode_id` null), or one Episode (`episode_id` set). Two Log Entries only ever refer to "the same thing" if they share the exact same Logging Target: finishing an entire show is a different target from, and never a rewatch relative to, a season- or episode-level entry made under it, even though it necessarily re-covers that ground.
_Avoid_: "target" on its own outside schema/code contexts where the meaning is already narrowed

**Rewatch**:
A Log Entry for a Logging Target that already has at least one prior Log Entry for that same target. Auto-detected by default, but a user can override it manually, e.g. when importing history where the first watch was never logged. Called "Rewatch" generically in code/data regardless of media type; the UI labels it per type (reread, replay).

**Episode** (Phase 4):
A single installment of a TV show, belonging to one season (`season_number`) and numbered within it (`episode_number`). Synced from TMDB lazily (the first time anything asks for a given show's episode list) and cached permanently in the catalog (`episodes`), never re-fetched live. There is no separate "Season" entity: a season is just the `season_number` value shared by a group of Episodes, and a season-level Logging Target is just a Log Entry with `season_number` set and no `episode_id`.
_Avoid_: "Season" as if it were its own table/model

**Edition**:
A specific published version of a book (its own ISBN, cover, page count). Books are cataloged at edition granularity, not at the "work" level (a work is the abstract book across all its editions) — two editions of the same book are two separate Media Items.
_Avoid_: Work, title (for books specifically)

**Friendship** (Phase 5):
A relationship between two users, stored as one directional row (requester, addressee, status) that becomes mutual once accepted — checked by OR across both directions, never duplicated into a second row. If both users request each other before either responds, the second request auto-resolves the first to accepted rather than creating a conflicting row.
_Avoid_: Connection, follow — there's no asymmetric following in this app

**Friend Request**:
The pending state of a Friendship. Declining doesn't block a future request from either party: there's no cooldown and no block concept in v1.

**Unfriending**:
Removing an accepted Friendship (soft-deleted via `deleted_at`), after which either party can send a fresh Friend Request and start over.

**Profile Visibility**:
A per-user Public/Private setting (`users.profile_visibility`) that gates a private user's shelf, diary, reviews, and friend list from anyone who isn't an accepted friend. Existence, name, and avatar stay searchable regardless of this setting — visibility gates content, not discoverability.
_Avoid_: Privacy setting, profile privacy

**Activity Feed** (Phase 5):
A user's reverse-chronological feed of their accepted friends' recent Log Entries, shown regardless of whether a given entry carries a rating or review — a bare "watched, no rating" entry is still activity worth seeing, with no rollup of bursts (e.g. a show logged episode-by-episode posts one feed item per entry). Falls back to the user's own recent Log Entries, same composition, when they have no friends yet.
_Avoid_: Timeline, activity stream

**New from Friends** (Phase 5):
A homescreen section distinct from the Activity Feed: one entry per friend, their single most recent Log Entry where they're the logger (not merely a tagged participant, per Tag Friends), sorted by recency, with no staleness cutoff — a friend shows with their latest entry even if it's months old. Needs its own query rather than being derived from the (paginated) Activity Feed, since a quiet friend's entry would otherwise fall off the page and the friend would wrongly disappear from the section.
_Avoid_: Friends' Latest, recent activity widget

**Tag Friends** (Phase 3, clarified Phase 5):
Marking another user as a participant who shared the experience behind a Log Entry (`log_entry_participants`). Purely informational: it never changes who can see the entry, never surfaces the entry on the tagged user's own diary/profile, and never injects it into the tagged user's friends' Activity Feed — only the logger's own friends see it there.
_Avoid_: Co-watcher (in code/schema contexts), follower
