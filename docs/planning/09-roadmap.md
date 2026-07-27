# Roadmap

Phased so each stage produces something runnable/testable, rather than one long build-everything-then-test pass. Roughly backend-first (validate via API calls before any frontend exists), then frontend, then deployment.

## Phase 0 — Planning (this)

Docs in this folder. Output: agreed feature scope, data model, tech choices.

## Phase 1 — Backend foundation

- Repo layout (`frontend/`, `backend/`, `docker/`, `compose.yml`, see [08-architecture-deployment.md](08-architecture-deployment.md)), Laravel app scaffold, Docker Compose skeleton (nginx + php-fpm + postgres)
- `users`, `invitations` migrations
- Sanctum SPA auth (login/logout, session cookie)
- `spatie/laravel-permission` roles/permissions wired up (Admin/Manager/Member)
- Invitation flow: send, accept, expire; first-admin bootstrap command
- No frontend yet beyond a minimal login page to prove auth end-to-end

## Phase 2 — Media catalog + external integrations

- `media_items` migration and model
- TMDB integration (movies + TV search, metadata copy-in, plus episode data for TV — see Phase 4)
- RAWG integration (games)
- Open Library integration (books)
- Manual media entry (Manager/Admin only)
- Basic search-and-add flow in the API

## Phase 3 — Shelf, diary, and backlog

- `user_media`, `log_entries`, and `log_entry_participants` migrations and models
- Add to shelf (with status), change status, log a completion (with date/rating/review/spoiler/favourite/rewatch/tagged members)
- Shelf views by status; item detail page showing all log entries for that item

## Phase 4 — Episode-level progress (TV)

- `episodes` migration, synced from TMDB per show
- `user_episode_progress` migration and model; mark-episode-watched endpoint
- "Watched X of Y" / "watched up to" query logic per show
- Prompt-to-log-as-completed flow when all episodes are marked watched

## Phase 5 — Friends & profile visibility

- `friendships` migration and model; send/accept/decline request endpoints
- `users.profile_visibility` column and settings endpoint
- Member search/discovery endpoint
- Visibility-aware profile endpoint (public vs. private vs. friends-only content)
- Activity feed endpoint: friends' recent reviews, with the no-friends-yet fallback

## Phase 6 — Comments & reactions

- `comments` migration (self-referencing `parent_comment_id`, soft delete) and threaded read/write endpoints
- `reactions` migration (fixed emoji enum) and endpoints
- Spoiler-gating applied to comment visibility, matching the parent review

## Phase 7 — Notifications

- `notifications` migration and model
- Notification-creating side effects wired into: tagging, friend request/accept, comments/replies, reactions
- Unread count + mark-read endpoints

## Phase 8 — Frontend: core flows

- Vue 3 + Vuetify + Vite scaffold, mobile-first layout (bottom nav: Shelf, Search/Add, Activity, Profile; notifications bell in the top app bar)
- Auth screens (login, accept-invite)
- Search & add flow, shelf views, log-entry creation/edit with half-star rating control
- Tag Friends control on the log-entry form (avatar row + add-friend picker, label adapts per media type)
- Item detail page with diary history and episode progress checklist (TV)

## Phase 9 — Frontend: social

- Profile pages (public/private states, friends-only gating, friend request UI)
- Activity feed UI, spoiler-blur behavior
- Comment threads and reaction bar on reviews
- Notifications bell/list UI
- Favourites view

## Phase 10 — Roles/admin UI

- Invite management UI (send/resend/revoke, assign role)
- User management (Admin: change roles, deactivate users)
- Manual media entry UI (Manager/Admin)
- Comment/review moderation UI (Admin instance-wide, any user on their own review)

## Phase 11 — Deployment hardening

- Production Dockerfiles under `docker/` (multi-stage builds for Vue assets + PHP-FPM/nginx)
- TLS setup, `.env.production`, backup cron for `pg_dump`
- Queue worker container for invite emails + optional metadata refresh job

## Phase 12 — Polish & stretch goals

- Mobile UI polish pass (touch target sizing, real-device testing)
- Friend-match suggestions ("you and X have N reviews in common") — compare accepted-friend-eligible pairs of users by overlapping `media_items` with completed `log_entries`, surface as a suggestion, not automatic
- Other possible fast-follows (not committed, from [01-overview.md](01-overview.md) non-goals): anime/manga as distinct types, podcasts/music, year-in-review stats, data export/import

Each phase should end with something demoable; Phases 1–7 can be validated via API calls (Postman/curl) before any frontend exists, which keeps backend and frontend work decoupled.
