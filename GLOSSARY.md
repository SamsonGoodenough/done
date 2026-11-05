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
One dated record of a user finishing (or re-finishing) a Media Item — its own date, rating, review, and spoiler flag (`log_entries`). A Shelf Entry can have many Log Entries over time (first watch, a rewatch years later, ...); the diary is the full history of Log Entries for an item.
_Avoid_: "Review" alone (a review is just the optional text field on a Log Entry, not the whole record)

**Logging** (Phase 3):
The act of creating a Log Entry. Distinct from Shelving: logging records a dated completion event, shelving just sets a Shelf Entry's current status and can happen with no Log Entry at all (e.g. marking something Planned).

**Rewatch**:
A Log Entry for a target that already has at least one prior Log Entry. Auto-detected by default (a prior Log Entry exists for the same target), but a user can override it manually, e.g. when importing history where the first watch was never logged. Called "Rewatch" generically in code/data regardless of media type; the UI labels it per type (reread, replay).

**Edition**:
A specific published version of a book (its own ISBN, cover, page count). Books are cataloged at edition granularity, not at the "work" level (a work is the abstract book across all its editions) — two editions of the same book are two separate Media Items.
_Avoid_: Work, title (for books specifically)
