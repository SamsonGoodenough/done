# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository status

The Docker Compose dev environment is scaffolded: a bare Laravel app in `backend/`, a bare Vue app in `frontend/`, and nginx/php-fpm/postgres/queue-worker/mailhog containers wired together. No feature code has been written yet (no custom migrations, models, routes, auth, or components beyond framework defaults). Follow the phase order in `docs/planning/09-roadmap.md` for what to build next (Phase 1: auth foundation).

### Running the stack

```
cp .env.example .env          # first time only
docker compose up -d
```

This brings up (`compose.yml` + `compose.override.yml`, merged automatically):

| Service | Purpose | Host port |
|---|---|---|
| `nginx` | reverse proxy: `/` to the Vite dev server, `/api` to php-fpm | http://localhost:8080 |
| `frontend` | Vite dev server (also reachable directly, with HMR) | http://localhost:5173 |
| `php-fpm` | the Laravel app | (internal only, 9000) |
| `queue-worker` | same Laravel codebase running `php artisan queue:work` | (internal only) |
| `postgres` | PostgreSQL 18 | (internal only, 5432) |
| `mailhog` | catches outgoing mail in dev instead of sending it | http://localhost:8025 |

First-time setup after `docker compose up -d`, since migrations don't run automatically:

```
docker compose exec php-fpm php artisan migrate
```

### Common commands

Run everything inside containers, never on the host:

```
docker compose exec php-fpm php artisan <command>      # artisan
docker compose exec php-fpm composer <command>          # composer
docker compose exec frontend npm run <script>            # npm/vite (lint, format, build)
docker compose logs -f <service>                          # tail logs
docker compose down                                        # stop everything
```

### Production

`compose.prod.yml` is not picked up automatically. It swaps in the `.prod` Dockerfiles under `docker/`, builds the frontend once into a shared volume nginx serves as static files, and disables debug mode:

```
docker compose -f compose.yml -f compose.prod.yml up -d
```

This has been built and smoke-tested for the dev path only so far; the prod path is a scaffold for Phase 11 and hasn't been run end to end yet.

## Working conventions

- **Run commands inside containers.** Once the Docker Compose stack exists, use `docker compose exec <service> <command>` for anything that executes application code: composer, artisan, npm, vite, test runners, migrations, and so on. Do not run these directly on the host. The only things that should run on the host are read-only file search and inspection (grep, find, ls, reading files) since those don't need a container.
- **Avoid AI-typical text patterns.** Do not use em dashes. Write commit messages, comments, and docs plainly, the way a person on this project would write them.
- **Never co-author commits.** Do not add a Co-Authored-By trailer or a Claude session link to any commit in this repo. Commits should read as written entirely by the user, with no attribution to Claude.
- **Branch naming.** New feature work happens on a branch named `feature/<short-description>`, not directly on `main`. Fixes for a tracked GitHub issue happen on `issue/<short-description>` (including the issue number is fine, e.g. `issue/42-login-redirect-loop`). Planning-doc-only changes with no code are the exception and can still go straight to `main`.

## What "Done" is

An invite-only, self-hosted tracker for movies, TV shows, games, and books for a small trusted group (well under 50 users). Rate 0 to 5 with half stars, write reviews with spoiler flags, favourite, log rewatches diary-style, tag friends who shared the experience, and keep a backlog. Full product framing is in `README.md` and `docs/planning/01-overview.md`.

## Where the plan lives

`docs/planning/README.md` is the index. Start there. It links every doc below and keeps a running "Decisions locked in during planning" list; treat that list as the current source of truth for what's been decided. The numbered docs contain the reasoning behind each decision.

| Doc | Covers |
|---|---|
| `02-tech-stack.md` | Full stack choices, plus the "Theming architecture" (single palette file feeding both Vuetify's theme config and bespoke components via CSS custom properties; don't hand-duplicate color values) |
| `03-features.md` | Full v1 feature spec |
| `04-data-model.md` | Entity/schema sketch and the reasoning behind each table |
| `05-database-choice.md` | Why PostgreSQL over MySQL/SQLite |
| `06-roles-permissions.md` | Admin/Manager/Member matrix + invite flow |
| `07-external-integrations.md` | TMDB, RAWG, Open Library: sync/caching strategy |
| `08-architecture-deployment.md` | Docker Compose service layout, nginx, Sanctum auth, backups |
| `09-roadmap.md` | The 12-phase build order, backend first, frontend from Phase 8 |
| `10-open-questions.md` | Open items and assumptions that still need confirming |
| `11-design-system.md` | The chosen visual language ("Passport & Postmark") |

`docs/planning/mockups/passport-and-postmark.html` is the live visual reference. Open it directly in a browser (self-contained HTML/CSS/JS, includes working color pickers and a light/dark toggle). Treat it as the source of truth for visual details; `11-design-system.md` summarizes it in prose.

## Architecture, once code exists

Stack (see `02-tech-stack.md` for full reasoning): Vue 3 + Vuetify 3 + Sass + Vite frontend, Laravel + Sanctum + `spatie/laravel-permission` backend, PostgreSQL, nginx + Docker Compose, self-hosted on a single VPS.

**Repo layout** (see `08-architecture-deployment.md` for the full tree): `frontend/` holds the Vue app, `backend/` holds the Laravel app, `docker/` holds every Dockerfile (dev and production) for every service, and `compose.yml` sits at the repo root. Dockerfiles are centralized under `docker/` rather than inside `frontend/`/`backend/`. Keep it this way; don't scatter Dockerfiles into the app source directories.

Key structural decisions worth knowing before touching the data model (`04-data-model.md` has full detail):
- **`user_media` vs `log_entries` is the core split.** `user_media` is a user's current shelf status for an item (planned/in progress/completed/dropped/on-hold, favourite). `log_entries` is the diary: one row per watch/play/read event, so a rewatch is just another row, not a field update. Don't collapse these back into one table.
- **`media_items` is a shared catalog** across all users, deduplicated by `(source, external_id)`, with a `metadata` JSONB column for the per-type fields (TV seasons/episodes, game platforms, book authors) rather than one table per media type.
- **No separate episode-progress table.** TV shows log at whole-show, season, or single-episode granularity through `log_entries` itself (`episode_id` / `season_number`, both nullable), with rating/review optional at every level. A log entry with no rating is what marks an episode watched; there's no separate watched-checkbox table.
- **Roles (Admin/Manager/Member) are not the same thing as friends/visibility.** Comments, reactions, Tag Friends, and profile visibility apply equally regardless of role; only catalog-editing, invites, and moderation are role-gated.
- **Theming.** Colors must live in exactly one canonical file (see the "Theming architecture" section of `02-tech-stack.md`) that feeds both Vuetify's theme config and custom components. Never hardcode a hex value or duplicate a color in a second Sass variable file.

## Continuing the planning docs

If asked to keep planning (not yet write code): match the existing doc style. Numbered files, cross-linked with relative links, each locked-in decision also appended to the "Decisions locked in during planning" list in `docs/planning/README.md`, open questions tracked in `10-open-questions.md` rather than left implicit. Keep the index table in `docs/planning/README.md` in sync whenever a doc is added or its scope changes.
