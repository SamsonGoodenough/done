# Roadmap

Phased so each stage produces something runnable/testable, rather than one long build-everything-then-test pass. Roughly backend-first (validate via API calls before any frontend exists), then frontend, then deployment.

Each phase has a **Status** line (Not Started / In Progress / Complete) and a checklist. A box is only checked once the work is merged to `main` — code sitting on an open branch is real progress, but it's noted inline rather than checked off, so this file never overstates what's actually shipped. This file is kept current by the `update-roadmap` skill (see `.claude/skills/update-roadmap/`); see also `docs/planning/roadmap.html` for the same information as a browsable dashboard.

## Phase 0 — Planning (this)

**Status:** Complete

Docs in this folder. Output: agreed feature scope, data model, tech choices.

## Phase 1 — Backend foundation

**Status:** Complete
*Merged to `main` via [PR #1](https://github.com/SamsonGoodenough/done/pull/1) on 2026-11-05.*

- [x] Repo layout (`frontend/`, `backend/`, `docker/`, `compose.yml`, see [08-architecture-deployment.md](08-architecture-deployment.md)), Laravel app scaffold, Docker Compose skeleton (nginx + php-fpm + postgres) — merged to `main`; also includes `queue-worker` and `mailhog`, beyond the original nginx/php-fpm/postgres baseline
- [x] `users`, `invitations` migrations — merged to `main`; also adds `avatar_path`/`profile_visibility`/`deactivated_at` and soft deletes beyond the base spec, per the timestamps/soft-deletes convention in [04-data-model.md](04-data-model.md)
- [x] Sanctum SPA auth (login/logout, session cookie) — merged to `main`; verified end-to-end through nginx (csrf-cookie priming, login, `/api/user`, logout, rate-limited lockout after 5 failed attempts on both the wrong-password and deactivated-account paths), plus automated feature tests
- [x] `spatie/laravel-permission` roles/permissions wired up (Admin/Manager/Member) — merged to `main`; roles and a first batch of permissions (`send-invitations`, `change-user-role`, `deactivate-user`) are seeded and enforced via policy/gate checks; `change-user-role`/`deactivate-user` are defined but not yet consumed by an endpoint (that's Phase 10's admin UI backing routes)
- [x] Invitation flow: send, accept, expire; first-admin bootstrap command — merged to `main`; full flow (send/accept/revoke/resend, Manager-can-only-invite-Member rule, duplicate-email/duplicate-invite guards, revoke modeled as a soft delete, invite email via queued Mailable caught by Mailhog, now encrypted while queued) and the `app:create-admin` bootstrap command (guarded against running when a user already exists, and against two concurrent runs both bootstrapping an Admin); verified via curl through nginx and 30 passing feature tests

*No frontend was built this phase beyond a minimal login page to prove auth end-to-end — that's Phase 8.*

Also fixed in passing: PHPUnit's `<env>` overrides in `phpunit.xml` weren't actually taking effect (compose.yml injects real container env vars, which Laravel's `env()` helper can read ahead of PHPUnit's override under some conditions) — tests were silently running `RefreshDatabase` against the real dev Postgres database. Fixed with an explicit `tests/bootstrap.php` that forces `getenv()`/`$_ENV`/`$_SERVER` before Laravel boots, plus a hard guard in `tests/TestCase.php` that refuses to run if `database.default` isn't `sqlite`.

A full code review of PR #1 (security + correctness, high effort) found and fixed five more real bugs before merge: `resend()` couldn't revive an already-expired invitation (the main case it exists for), `accept()` wasn't transactional (a mid-flow failure could orphan a roleless user), `invited_by` was silently missing from every invitation API response, login rate-limiting had a gap on the deactivated-account path, and the first-admin bootstrap had a check-then-create race (closed with `Cache::lock()` rather than a Postgres-specific advisory lock, since the test suite runs on sqlite). Also set up this repo's `docs/agents/*.md` configuration for the engineering skill suite (issue tracker, triage labels, domain docs), unrelated to the roadmap itself but landed in the same PR.

## Phase 2 — Media catalog + external integrations

**Status:** Complete
*Merged to `main` via [PR #2](https://github.com/SamsonGoodenough/done/pull/2) on 2026-11-06. Design for the whole phase locked in via a grilling session — external API client architecture, schema specifics, and API shape are recorded in [ADR 0001](../adr/0001-media-source-integration-architecture.md); mid-design, a games-metadata vendor swap (RAWG → IGDB) happened after RAWG proved unreliable in practice, recorded in [ADR 0002](../adr/0002-games-metadata-vendor-igdb-over-rawg.md) with full research at [`docs/research/games-api-alternatives-to-rawg.md`](../research/games-api-alternatives-to-rawg.md).*

- [x] `media_items` migration and model — merged to `main`; `MediaType`/`MediaSource` enum casts, soft-delete + restore-on-dedup via a partial unique index (originally `(source, external_id)`, see the code-review fix below)
- [x] TMDB integration (movies + TV search, metadata copy-in, plus episode data for TV — see Phase 4) — merged to `main`; `TmdbClient` implements the shared `MediaSourceClient` contract (movie/TV search + details, genre extraction straight from TMDB's detail response, 404-vs-unavailable mapping); 8 passing unit tests against recorded fixtures
- [x] IGDB integration (games) — switched from the originally planned RAWG after reliability concerns, see [ADR 0002](../adr/0002-games-metadata-vendor-igdb-over-rawg.md); merged to `main`; `IgdbClient` (Twitch OAuth2 client-credentials flow, token caching with 401-triggered refresh, Apicalypse queries, platforms/developer extraction); 6 passing unit tests
- [x] Open Library integration (books) — merged to `main`; `OpenLibraryClient`, cataloging at edition granularity (not "work"), free-text `publish_date` normalization; 7 passing unit tests
- [x] Manual media entry (Manager/Admin only) — merged to `main`; `POST /api/media-items/manual`, new `create-media-item` permission granted to Admin+Manager, minimal-required-fields validation (type + title only)
- [x] Basic search-and-add flow in the API — merged to `main`; `GET /api/media-items/search` (flags already-cataloged results, per-user rate limit), `POST /api/media-items` (dedup/restore-with-metadata-refresh), `GET /api/media-items/{id}` show endpoint, uniform 404/503 exception rendering

A full code review (high effort) before merge found and fixed 9 real issues: `media_items`' dedup key colliding across types (TMDB's movie and TV ids share a numbering space under the single `tmdb` source — the unique index and every lookup now also scope by `type`), `store()` always hitting the external API even for an already-cataloged item, a route-parameter casing mismatch silently denying every shelf update, `ShelfEntryManager` silently overwriting an already-active shelf entry's status/favourite on a repeat POST, two unguarded check-then-create races (one needing a nested-transaction savepoint since it runs inside an existing transaction), duplicated validation logic moved onto the enum, shelf-index filters that weren't enum-validated, stale RAWG/`RawgClient` references in ADR 0001 and `GLOSSARY.md` left over from the mid-build vendor swap, and IGDB's retry catching too broadly while dropping its first exception on a double failure. One further issue (pervasive em dash usage across new files, against this repo's style convention) was deliberately deferred as too large to fix safely in the same pass.

## Phase 3 — Shelf, diary, and backlog

**Status:** Complete
*Merged to `main` via [PR #2](https://github.com/SamsonGoodenough/done/pull/2) on 2026-11-06, the same PR as Phase 2 (landed on one branch rather than split out). Design locked in via a second grilling session; one real cross-document contradiction surfaced and got resolved — see [ADR 0003](../adr/0003-shelf-status-and-logging-are-decoupled.md).*

- [x] `user_media`, `log_entries`, and `log_entry_participants` migrations and models — merged to `main`; `user_media` gets the same soft-delete + restore-on-dedup pattern as `media_items`; `log_entries` deliberately excludes `episode_id`/`season_number` (deferred to Phase 4, since the `episodes` table it would validate against doesn't exist yet); `log_entry_participants` is a plain pivot, no model class
- [x] Add to shelf (with status), change status, log a completion (with date/rating/review/spoiler/favourite/rewatch/tagged members) — merged to `main`; `ShelfEntryManager`/`LogEntryRecorder` services back `POST/GET/PATCH/DELETE /api/user-media` and `POST /api/log-entries` — one transaction finds-or-creates the shelf entry, auto-sets status to Completed only on creation/restoration (never touching an existing entry's status), computes `is_rewatch` with a manual-override escape hatch, writes `is_favourite` through from the log form, attaches tagged participants (self-tagging allowed)
- [x] Shelf views by status; item detail page showing all log entries for that item — merged to `main`; `GET /api/user-media` (status/type/favourite filters), `GET /api/media-items/{id}/log-entries` scoped to the caller's own diary only (Phase 5 will extend this once friend visibility exists)

See Phase 2's code-review note above — two of the nine fixes (the overwrite bug and one of the two races) were in this phase's `ShelfEntryManager`/`LogEntryRecorder`.

*89 backend tests passing in total as of merge. Manually verified end-to-end through nginx against the real TMDB/IGDB/Open Library APIs (search, catalog-from-source, shelve, log, diary) before merge.*

## Phase 4 — Episode & season logging (TV)

**Status:** In Progress
*Branch `feature/phase4-episode-season-logging`. Design locked in via a grilling + domain-modeling session (new GLOSSARY.md terms **Episode** and **Logging Target**; see [ADR 0004](../adr/0004-episode-sync-is-lazy-and-self-refreshing.md) for the sync-architecture trade-off). All four items below are implemented and passing locally (21 new/updated backend tests, 110 total passing, Pint clean), but nothing is committed or pushed yet, still an uncommitted working-tree diff on this branch.*

- [ ] `episodes` migration, synced from TMDB per show (implemented, uncommitted). Sync is lazy: the first `GET /api/media-items/{id}/episodes` call for a show fetches every season (a new `SupportsEpisodeSync` contract on `TmdbClient`, since only TV needs this; games/books clients don't implement it), persisting season-by-season so a mid-sync failure keeps whatever already succeeded instead of discarding it; this also self-backfills shows cataloged before this phase existed. A still-airing show (cached status not yet Ended/Canceled) gets re-checked for a newly-appeared season on every subsequent read, with the refreshed status written back as a side effect; the check stops permanently once a show's status goes terminal. A manually-cataloged show (no `external_id`) is left alone entirely, returning whatever's already stored locally.
- [ ] Extend the `log_entries` migration/model with nullable `episode_id` (FK → episodes) and `season_number` columns, so an entry can target the whole show, one season, or one episode (implemented, uncommitted)
- [ ] Application-layer validation: `episode_id` and `season_number` aren't both set on the same entry, and a set `episode_id` actually belongs to the show being logged (implemented, uncommitted); also rejects episode/season granularity on any non-`tv_show` media item, and a season can't be logged until it's actually been synced
- [ ] Rewatch detection (`is_rewatch`) scoped per target, not just per show, a rewatch check for one episode only looks at prior entries for that same episode (implemented, uncommitted); `LogEntryRecorder` scopes by exact Logging Target (same `episode_id`, or same `season_number`, or both null), so a whole-show log entry is never a rewatch relative to a season- or episode-level entry made under it

*A code review (high effort) of the uncommitted diff found and fixed one real bug before this phase goes any further: `EpisodeSyncer::episodesFor()` called `TmdbClient::showSummary()` unconditionally, which crashed with a TypeError for any manually-cataloged (`source = manual`) TV show, since `external_id` is always null for those and reproduced live via `tinker`. Fixed with an early return for `external_id === null`. The same review also flagged this phase's new files for using em dashes against this repo's style convention (`CLAUDE.md`); fixed throughout the new/changed code and this phase's own doc prose, unlike Phase 2's pervasive-em-dash finding this one was small enough to fix in the same pass rather than deferred.*

## Phase 5 — Friends & profile visibility

**Status:** Not Started

- [ ] `friendships` migration and model; send/accept/decline request endpoints
- [ ] `users.profile_visibility` column and settings endpoint
- [ ] Member search/discovery endpoint
- [ ] Visibility-aware profile endpoint (public vs. private vs. friends-only content)
- [ ] Activity feed endpoint: friends' recent reviews, with the no-friends-yet fallback

## Phase 6 — Comments & reactions

**Status:** Not Started

- [ ] `comments` migration (self-referencing `parent_comment_id`, soft delete) and threaded read/write endpoints
- [ ] `reactions` migration (fixed emoji enum) and endpoints
- [ ] Spoiler-gating applied to comment visibility, matching the parent review

## Phase 7 — Notifications

**Status:** Not Started

- [ ] `notifications` migration and model
- [ ] Notification-creating side effects wired into: tagging, friend request/accept, comments/replies, reactions
- [ ] Unread count + mark-read endpoints

## Phase 8 — Frontend: core flows

**Status:** Not Started

- [ ] Vue 3 + Vuetify + Vite scaffold, mobile-first layout (bottom nav: Shelf, Search/Add, Activity, Profile; notifications bell in the top app bar) — the scaffold and libraries (Vuetify, Router, Pinia, vite-plugin-pwa) are already installed as part of Phase 1's Docker environment task, but the mobile-first bottom-nav layout, custom theme, and bespoke components haven't been built
- [ ] Auth screens (login, accept-invite)
- [ ] Search & add flow, shelf views, log-entry creation/edit with half-star rating control
- [ ] Tag Friends control on the log-entry form (avatar row + add-friend picker, label adapts per media type)
- [ ] Item detail page with diary history across whole-show, season, and episode entries (TV)

## Phase 9 — Frontend: social

**Status:** Not Started

- [ ] Profile pages (public/private states, friends-only gating, friend request UI)
- [ ] Activity feed UI, spoiler-blur behavior
- [ ] Comment threads and reaction bar on reviews
- [ ] Notifications bell/list UI
- [ ] Favourites view

## Phase 10 — Roles/admin UI

**Status:** Not Started

- [ ] Invite management UI (send/resend/revoke, assign role)
- [ ] User management (Admin: change roles, deactivate users)
- [ ] Manual media entry UI (Manager/Admin)
- [ ] Comment/review moderation UI (Admin instance-wide, any user on their own review)

## Phase 11 — Deployment hardening

**Status:** Not Started
*The dev-path Docker environment already exists (Phase 1); this phase is specifically about the production path.*

- [ ] Production Dockerfiles under `docker/` (multi-stage builds for Vue assets + PHP-FPM/nginx) — `Dockerfile.prod` files and `compose.prod.yml` exist and validate (`docker compose config`), but have not been smoke-tested end to end
- [ ] TLS setup, `.env.production`, backup cron for `pg_dump`
- [ ] Queue worker container for invite emails + optional metadata refresh job — the `queue-worker` service exists and runs, but there are no jobs to process yet

## Phase 12 — Polish & stretch goals

**Status:** Not Started

- [ ] Mobile UI polish pass (touch target sizing, real-device testing)
- [ ] Friend-match suggestions ("you and X have N reviews in common") — compare accepted-friend-eligible pairs of users by overlapping `media_items` with completed `log_entries`, surface as a suggestion, not automatic

*Other possible fast-follows (not committed, from [01-overview.md](01-overview.md) non-goals): anime/manga as distinct types, podcasts/music, year-in-review stats, data export/import.*

Each phase should end with something demoable; Phases 1–7 can be validated via API calls (Postman/curl) before any frontend exists, which keeps backend and frontend work decoupled.
