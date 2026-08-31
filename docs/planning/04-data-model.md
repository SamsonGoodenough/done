# Data Model

This is a schema *sketch* for planning purposes — column types and indexes will be finalized when migrations are written, but the entities, relationships, and the reasoning behind each are settled here.

## Entity overview

```
users ──< invitations (invited_by)
users ──< user_media >── media_items
user_media ──< log_entries
users ──< log_entries
users >──< log_entries  (via log_entry_participants, Tag Friends)
users >──< users        (via friendships, request/accept)
media_items (type = movie | tv_show | game | book)
media_items ──< episodes                (tv_show only)
log_entries >── episodes  (optional — an episode-level log entry references one; a season-level entry just stores a season_number)
log_entries ──< comments (self-referencing parent_comment_id, threaded)
log_entries ──< reactions >── users
users ──< notifications
```

## Timestamps & soft deletes (default convention)

Every table below gets `created_at`/`updated_at` and a nullable `deleted_at` (Eloquent's `SoftDeletes`) unless its own section says otherwise. Soft-deleting is the default over hard-deleting anywhere a row might be referenced by other rows that should survive it (a log entry pointing at a soft-deleted episode, comments under a soft-deleted review, and so on) — the alternative is cascading deletes that quietly erase history, which cuts against the point of a diary/tracker people want to keep.

Noted exceptions:

- **`users`** — already has its own `deactivated_at` for this purpose (see below), deliberately *not* Eloquent `SoftDeletes`: a deactivated user's content has to keep showing up in normal queries (comments, tags, reviews), not get hidden the way `SoftDeletes` hides a row by default.
- **`log_entry_participants`** — a plain many-to-many pivot with no model class; un-tagging someone is just a delete, there's no history worth keeping for a tag that no longer applies.
- **`reactions`** — atomic by nature (you either reacted or you didn't); there's nothing to *update*, and soft-deleting would complicate the `(log_entry_id, user_id, emoji)` uniqueness for no real benefit — un-reacting is a hard delete, re-reacting inserts a fresh row.

Where a soft-deletable table also has a uniqueness constraint (`media_items` on `(source, external_id)`, `friendships` on `(requester_id, addressee_id)`), the app layer should restore a matching soft-deleted row instead of inserting a duplicate; finalize as a filtered/partial unique index (`WHERE deleted_at IS NULL`) if the database should enforce that directly.

## `users`

| Column | Notes |
|---|---|
| id | PK |
| name | |
| email | unique |
| password | nullable until an invitation is accepted |
| avatar_path | nullable — path to a locally-stored uploaded image; null means "use the generated fallback" (initials/identicon, computed on the fly, not stored) |
| profile_visibility | enum: `public` (default), `private` — see "Friends & profile visibility" in [03-features.md](03-features.md) |
| deactivated_at | nullable — set instead of deleting the row when an Admin removes a user; a non-null value blocks login but leaves all their content (log entries, comments, reactions, tags) intact and attributed to them as normal |
| created_at / updated_at | |

Roles are managed via `spatie/laravel-permission`'s pivot tables (`model_has_roles`, `roles`, `permissions`) rather than a `role` column — see [06-roles-permissions.md](06-roles-permissions.md).

## `invitations`

| Column | Notes |
|---|---|
| id | PK |
| email | who's being invited |
| token | random, hashed at rest, looked up by raw token from the emailed link |
| role | the role the invitee will get on acceptance |
| invited_by | FK → users.id |
| expires_at | e.g. now + 7 days |
| accepted_at | nullable — null until used |
| created_at / updated_at | |
| deleted_at | nullable — set when an invitation is revoked before being accepted |

## `media_items`

The shared catalog — one row per distinct movie/show/game/book, regardless of how many users have it on their shelf. Deduplicated by `(source, external_id)` when sourced from an API.

| Column | Notes |
|---|---|
| id | PK |
| type | enum: `movie`, `tv_show`, `game`, `book` |
| source | enum: `tmdb`, `games_api`, `books_api`, `manual` |
| external_id | nullable — null for manually-added items |
| title | |
| release_date | nullable |
| poster_url | nullable |
| overview | text, nullable |
| metadata | JSON — type-specific fields (see below) |
| added_by | FK → users.id, nullable — set when `source = manual` |
| created_at / updated_at | |
| deleted_at | nullable — removed from the active catalog, but existing `user_media`/`log_entries`/`episodes` rows referencing it are preserved |

**Why a `metadata` JSON column instead of one table per type:** four media types each need a handful of extra fields (TV needs season/episode counts and airing status; games need platforms; books need authors and page count). Four separate tables with polymorphic joins is more ceremony than this scale needs. A JSON column keeps `media_items` a single queryable table for cross-type views (the shelf, search, activity feed) while still holding type-specific data. This is a deliberate small-scale tradeoff — see [05-database-choice.md](05-database-choice.md) for how this interacts with the database choice (PostgreSQL's JSONB makes this comfortable to query if it's ever needed; it wouldn't be as pleasant in plain MySQL JSON or SQLite's JSON1).

Example `metadata` shapes:

```json
// tv_show
{ "seasons": 5, "episodes": 62, "status": "ended" }

// game
{ "platforms": ["PS5", "PC"], "developer": "FromSoftware" }

// book
{ "authors": ["Frank Herbert"], "page_count": 412, "isbn": "9780441013593" }
```

## `user_media`

The "shelf" — one row per user per media item, tracking their current relationship to it independent of any specific log entry.

| Column | Notes |
|---|---|
| id | PK |
| user_id | FK → users.id |
| media_item_id | FK → media_items.id |
| status | enum: `planned`, `in_progress`, `completed`, `dropped`, `on_hold` |
| is_favourite | boolean, default false |
| created_at / updated_at | |
| deleted_at | nullable — removed from a user's shelf; `log_entries` underneath are preserved |

Unique on `(user_id, media_item_id)` — a user has at most one shelf entry per item, but that item can have many `log_entries` underneath it (diary-style).

## `log_entries`

The diary. One row per watch/play/read event.

| Column | Notes |
|---|---|
| id | PK |
| user_media_id | FK → user_media.id |
| episode_id | nullable, FK → episodes.id — set when this entry logs a single episode (tv_show only) |
| season_number | nullable, int — set when this entry logs an entire season as one unit (tv_show only); null whenever `episode_id` is set, or when logging the whole item |
| logged_date | the date the user finished it (or logged it) |
| rating | decimal, nullable, 0.0–5.0 in 0.5 steps |
| review | text, nullable |
| contains_spoilers | boolean, default false |
| is_rewatch | boolean — true if a prior `completed`-resulting log entry already exists for the same target (same `episode_id`, or same `season_number`, or the whole item when both are null) |
| created_at / updated_at | |
| deleted_at | nullable — deleting a log entry/review is soft, so its comments/reactions/tags aren't orphaned |

**Why `rating` isn't just an integer 0–10:** storing it as a decimal (`0.0`–`5.0`, step `0.5`) keeps the display logic (star components) and the stored value in the same unit, rather than storing "half-stars as an int 0-10" and converting everywhere. Validated at the application layer to only allow 0.5 steps.

**Logging granularity for TV shows:** a `log_entries` row can target the whole show (`episode_id` and `season_number` both null — same as movies/games/books), a single season (`season_number` set, `episode_id` null), or a single episode (`episode_id` set). All three share the exact same fields, and rating/review stay optional at every level — logging an episode with no rating is how a user notes "I watched this" without a separate progress-tracking table. The application layer enforces that `episode_id` and `season_number` aren't both set on the same row, and that a set `episode_id` actually belongs to the show being logged (via `media_item_id` on `user_media`).

## `log_entry_participants`

Pivot table backing Tag Friends (displayed as "Watched with" / "Played with" / "Read with" depending on the item's `type`).

| Column | Notes |
|---|---|
| log_entry_id | FK → log_entries.id |
| user_id | FK → users.id — the tagged member |
| created_at | |

Composite PK on `(log_entry_id, user_id)`, no surrogate `id` needed. Deliberately just a plain many-to-many pivot — no free-text guest name column, since tagging is members-only per the planning answer. A member can be tagged on any number of log entries; a log entry can tag any number of members (including the entry's own author, though the UI wouldn't offer that as an option). Deleting a user should cascade-delete their rows here (they just stop appearing as tagged) without touching the log entry itself.

## `friendships`

Backs the friend request/accept flow and gates profile visibility.

| Column | Notes |
|---|---|
| id | PK |
| requester_id | FK → users.id |
| addressee_id | FK → users.id |
| status | enum: `pending`, `accepted`, `declined` |
| created_at | when the request was sent |
| updated_at | |
| responded_at | nullable — when accepted/declined |
| deleted_at | nullable — e.g. a future "unfriend" action, or clearing out a declined request |

Kept directional (requester/addressee) rather than a symmetric pair of rows — simpler to reason about "who sent this," and a mutual friendship is just `status = accepted` read with an `OR` across both directions when checking "are these two users friends." Unique on `(requester_id, addressee_id)` to prevent duplicate pending requests; the application layer blocks sending a second request in the reverse direction while one is already pending (that should just accept the existing one).

## `episodes`

TV-show-only. Synced from TMDB when a show is added to the catalog.

| Column | Notes |
|---|---|
| id | PK |
| media_item_id | FK → media_items.id |
| season_number | |
| episode_number | |
| title | nullable |
| air_date | nullable |
| created_at / updated_at | |
| deleted_at | nullable — preserves `log_entries.episode_id` references if a show's episode list is corrected after a metadata refresh |

## `comments`

Threaded comments on a review (a `log_entries` row with a non-null `review`).

| Column | Notes |
|---|---|
| id | PK |
| log_entry_id | FK → log_entries.id |
| user_id | FK → users.id — the commenter |
| parent_comment_id | nullable, FK → comments.id — self-referencing, enables nesting |
| body | text |
| deleted_at | nullable (soft delete) |
| created_at / updated_at | |

Soft-deleted (`deleted_at` set, row kept) rather than hard-deleted so a deleted comment's replies aren't orphaned — the UI renders a soft-deleted comment as "[deleted]" but keeps its children in the thread.

## `reactions`

Fixed-emoji reactions on a review.

| Column | Notes |
|---|---|
| id | PK |
| log_entry_id | FK → log_entries.id |
| user_id | FK → users.id |
| emoji | enum — the fixed reaction set (e.g. `thumbs_up`, `heart`, `laugh`, `wow`, `sad`, `fire`) |
| created_at | |

Unique on `(log_entry_id, user_id, emoji)` — a user can leave multiple *different* reactions on the same review but not stack the same one twice.

## `notifications`

Backs the in-app bell/badge.

| Column | Notes |
|---|---|
| id | PK |
| user_id | FK → users.id — the recipient |
| type | enum: `tagged`, `friend_request`, `friend_accepted`, `comment_on_review`, `comment_reply`, `reaction` |
| data | JSON — polymorphic reference to the relevant entity (e.g. `log_entry_id`, `comment_id`, `from_user_id`) plus whatever the notification UI needs to render without extra lookups |
| read_at | nullable |
| created_at / updated_at | |
| deleted_at | nullable — lets a user clear a notification from their list without losing it server-side |

`data` as JSON rather than a rigid set of nullable FK columns because the six notification types reference different combinations of entities — same reasoning as `media_items.metadata` in [05-database-choice.md](05-database-choice.md)'s JSONB discussion.

## Why `user_media` *and* `log_entries` as separate tables

This is the main structural decision in the schema, driven directly by the "diary-style logging" and "backlog" answers from planning:

- `user_media` answers "where does this stand for this user *right now*" (planned/in progress/completed/dropped, favourite or not) — one row, cheap to query for shelf views.
- `log_entries` answers "what's the full history of me finishing this" — potentially many rows, each with its own date/rating/review, which is exactly what diary-style rewatch tracking requires.
- Backlog items (`status = planned`) simply have zero `log_entries`. Moving to `completed` and creating the first `log_entries` row happen together in one action from the UI, but they're modeled separately so a `dropped` or `on_hold` item can still have log entries — e.g. dropping a TV show after loving season 1 is a real log entry with `season_number = 1`, its own rating and review, independent of the show's overall shelf status.

## Roles & permissions tables

Provided by `spatie/laravel-permission`: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`. See [06-roles-permissions.md](06-roles-permissions.md) for the actual role/permission matrix.
