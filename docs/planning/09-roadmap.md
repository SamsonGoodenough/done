# Roadmap

Phased so each stage produces something runnable/testable, rather than one long build-everything-then-test pass. Roughly backend-first (validate via API calls before any frontend exists), then frontend, then deployment.

Each phase has a **Status** line (Not Started / In Progress / Complete) and a checklist. A box is only checked once the work is merged to `main` — code sitting on an open branch is real progress, but it's noted inline rather than checked off, so this file never overstates what's actually shipped. This file is kept current by the `update-roadmap` skill (see `.claude/skills/update-roadmap/`); see also `docs/planning/roadmap.html` for the same information as a browsable dashboard.

## Phase 0 — Planning (this)

**Status:** Complete

Docs in this folder. Output: agreed feature scope, data model, tech choices.

## Phase 1 — Backend foundation

**Status:** In Progress
*Backend work so far lives on `feature/phase1-user-invitation-models` (not yet merged, no PR opened yet).*

- [x] Repo layout (`frontend/`, `backend/`, `docker/`, `compose.yml`, see [08-architecture-deployment.md](08-architecture-deployment.md)), Laravel app scaffold, Docker Compose skeleton (nginx + php-fpm + postgres) — merged to `main`; also includes `queue-worker` and `mailhog`, beyond the original nginx/php-fpm/postgres baseline
- [ ] `users`, `invitations` migrations — built and verified on the open branch; also adds `avatar_path`/`profile_visibility`/`deactivated_at` and soft deletes beyond the base spec, per the timestamps/soft-deletes convention in [04-data-model.md](04-data-model.md)
- [ ] Sanctum SPA auth (login/logout, session cookie) — not started, `laravel/sanctum` not yet installed
- [ ] `spatie/laravel-permission` roles/permissions wired up (Admin/Manager/Member) — package installed and `HasRoles` added to `User` on the open branch; Admin/Manager/Member roles aren't seeded yet and no policies/gates are enforced
- [ ] Invitation flow: send, accept, expire; first-admin bootstrap command — `Invitation` model/migration exist on the open branch; no mail sending, accept endpoint, or `artisan app:create-admin` command yet

*No frontend yet beyond a minimal login page to prove auth end-to-end — a boundary for this phase, not a task to check off.*

## Phase 2 — Media catalog + external integrations

**Status:** Not Started

- [ ] `media_items` migration and model
- [ ] TMDB integration (movies + TV search, metadata copy-in, plus episode data for TV — see Phase 4)
- [ ] RAWG integration (games)
- [ ] Open Library integration (books)
- [ ] Manual media entry (Manager/Admin only)
- [ ] Basic search-and-add flow in the API

## Phase 3 — Shelf, diary, and backlog

**Status:** Not Started

- [ ] `user_media`, `log_entries`, and `log_entry_participants` migrations and models
- [ ] Add to shelf (with status), change status, log a completion (with date/rating/review/spoiler/favourite/rewatch/tagged members)
- [ ] Shelf views by status; item detail page showing all log entries for that item

## Phase 4 — Episode & season logging (TV)

**Status:** Not Started

- [ ] `episodes` migration, synced from TMDB per show
- [ ] Extend the `log_entries` migration/model with nullable `episode_id` (FK → episodes) and `season_number` columns, so an entry can target the whole show, one season, or one episode
- [ ] Application-layer validation: `episode_id` and `season_number` aren't both set on the same entry, and a set `episode_id` actually belongs to the show being logged
- [ ] Rewatch detection (`is_rewatch`) scoped per target, not just per show — a rewatch check for one episode only looks at prior entries for that same episode

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
