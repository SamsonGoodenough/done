# Architecture & Deployment

## High-level shape

A Vue 3 SPA (built by Vite into static assets) served by nginx, which also reverse-proxies API requests to a Laravel backend running under PHP-FPM. Everything runs as Docker Compose services on a single VPS.

```
                    ┌────────────────────────────┐
   Browser  ───────▶│           nginx            │
  (phone/desktop)    │  - serves built Vue SPA     │
                    │  - proxies /api/* to PHP-FPM │
                    └───────────┬────────────────┘
                                │
                     ┌──────────┴───────────┐
                     │       PHP-FPM          │
                     │   Laravel API app      │
                     └───┬───────────┬───────┘
                         │           │
                 ┌───────┴───┐   ┌───┴──────────┐
                 │ PostgreSQL │   │ Queue worker  │
                 │            │   │ (invites,     │
                 └────────────┘   │ metadata sync)│
                                  └──┬────────────┘
                                     │
                          ┌──────────┴──────────┐
                          │  External APIs       │
                          │  TMDB / IGDB / Open  │
                          │  Library             │
                          └──────────────────────┘
```

## Repository layout

```
done/
├── frontend/              # Vue 3 + Vuetify + Vite source
├── backend/                # Laravel app source
├── docker/                 # All Dockerfiles and container-specific config, dev and production
│   ├── frontend/
│   │   ├── Dockerfile           # dev image, Vite dev server
│   │   └── Dockerfile.prod      # production build, multi-stage, outputs static assets
│   ├── backend/
│   │   ├── Dockerfile           # dev image, PHP-FPM
│   │   └── Dockerfile.prod      # production image
│   └── nginx/
│       ├── Dockerfile
│       └── nginx.conf
├── compose.yml              # base service definitions
├── compose.override.yml     # local dev conveniences, picked up automatically alongside compose.yml
└── docs/planning/
```

Dockerfiles are centralized under `docker/` rather than living inside `frontend/`/`backend/`, including the production variants, so the app source directories stay framework-only and all container concerns are in one place.

## Docker Compose services

- **`nginx`** — serves the Vue production build (static files, mounted or copied in at image build time, including the PWA manifest/service worker from `vite-plugin-pwa`) and reverse-proxies `/api` (and any other backend routes) to `php-fpm`. Also serves uploaded avatar images from a shared volume. Terminates TLS, or sits behind a small Caddy/certbot sidecar for Let's Encrypt renewal.
- **`php-fpm`** — the Laravel app. Handles all API requests, Sanctum auth, roles/permissions, avatar upload validation/resizing, and dispatches queued jobs.
- **`postgres`** — the database (see [05-database-choice.md](05-database-choice.md)). Named volume for data persistence.
- **`queue-worker`** — a second container running the same Laravel codebase as `php-fpm` but executing `php artisan queue:work` instead of serving requests. Handles sending invite emails and (optionally) periodic metadata refresh jobs, so these don't block web requests.
- **`redis`** *(optional)* — if the database queue driver proves too slow or a cache layer is wanted later; not required for v1 at this scale (Laravel's database queue driver is fine for low volume).

`compose.yml` at the repo root defines all of these plus shared networks and volumes; a `.env` file (not committed) holds secrets: DB credentials, `APP_KEY`, TMDB API key, IGDB/Twitch client credentials, mail credentials. A named volume (e.g. `avatars`) is mounted into both `php-fpm` (to write uploads) and `nginx` (to serve them) for user-uploaded avatar images.

## Auth mechanism

**Laravel Sanctum, SPA (cookie) mode** — chosen because the Vue app and Laravel API are served from the same top-level domain behind one nginx (e.g. `done.example.com` for the SPA, `done.example.com/api` for the backend, or a subdomain with CORS + `SESSION_DOMAIN` configured). This avoids needing a token-issuing OAuth server or manually managing bearer tokens in the frontend — Sanctum's CSRF-protected cookie session is the standard fit for exactly this "first-party SPA talking to its own Laravel API" setup.

## TLS

Let's Encrypt certificates, either via `certbot` running alongside nginx with a renewal cron, or an nginx-proxy + acme-companion pair of containers that automate this. Either is fine at this scale — pick whichever is less fiddly to operate; this is a deployment detail, not an architectural one.

## Backups

- **Database:** scheduled `pg_dump` (e.g. nightly, via a cron job on the host or a small `cron` sidecar container) writing to a local path, optionally synced off-box (e.g. to object storage) so a VPS failure doesn't also destroy the backups.
- **Uploaded avatars:** the only local file assets in v1 (posters/covers are still hotlinked from external APIs, per [07-external-integrations.md](07-external-integrations.md)) — small in volume (one small image per user), so the `avatars` volume can be included in the same backup job as a simple archive/copy alongside the `pg_dump` output, rather than needing its own backup pipeline.

## Environments

- **Local development:** `compose.yml` plus `compose.override.yml` for dev conveniences (bind-mounted `frontend/`/`backend/` source for hot reload via Vite's dev server, Laravel's debug mode on, Mailhog or similar for catching invite emails locally instead of sending real mail). Uses the dev Dockerfiles under `docker/`.
- **Production:** the same base `compose.yml`, built from the `.prod` Dockerfiles under `docker/` (multi-stage: one stage builds the Vite assets, final stage is a slim PHP-FPM + nginx pairing), `.env.production` values, debug mode off.

## First-admin bootstrap

Since there's no public registration, the first Admin account is created via an Artisan command run once after the first deploy (e.g. `php artisan app:create-admin --email=... `), or a database seeder gated to run only when no users exist. This is documented as a required first deployment step, not left implicit.
