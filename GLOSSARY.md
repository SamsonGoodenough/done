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
Where a Media Item's metadata originates: `tmdb`, `games_api` (RAWG), `books_api` (Open Library), or `manual`.
_Avoid_: Provider, API

**External ID**:
The identifier a Source uses for a Media Item (e.g. a TMDB movie ID). Null for manually-entered items.

**Cataloging** (Phase 2):
Adding a Media Item to the shared Catalog, either by copying in metadata from an external Source or by manual entry. Distinct from Shelving.
_Avoid_: "Adding an item" on its own — ambiguous with Shelving

**Shelving** (Phase 3, not yet built):
A user adding a cataloged Media Item to their personal shelf (`user_media`), tracking their own status and favourite flag independent of the shared catalog. A Media Item can be cataloged with nobody shelving it yet.
_Avoid_: "Adding an item" on its own — ambiguous with Cataloging

**Edition**:
A specific published version of a book (its own ISBN, cover, page count). Books are cataloged at edition granularity, not at the "work" level (a work is the abstract book across all its editions) — two editions of the same book are two separate Media Items.
_Avoid_: Work, title (for books specifically)
