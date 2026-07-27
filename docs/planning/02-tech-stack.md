# Tech Stack

## Frontend

| Piece | Choice | Why |
|---|---|---|
| Framework | Vue 3 (Composition API, `<script setup>`) | Requested; good fit for a component-heavy, form-and-list-driven app |
| UI library | Vuetify 3 | Material-based components out of the box (cards, ratings, forms, bottom nav), strong mobile-first support, saves building a design system from scratch |
| Styling | Sass | For overrides on top of Vuetify's theme/tokens and any custom components — see "Theming architecture" below for how color variables stay globally editable from one place |
| Build tool | Vite | Fast dev server, native Vue 3 support, simple production build for nginx to serve |
| State management | Pinia | Standard for Vue 3, used for auth/session state, current user, and cached media/log data |
| Routing | Vue Router | SPA navigation, route guards for auth + role-gated pages |
| HTTP client | Axios (via a thin API wrapper) | Works cleanly with Laravel Sanctum's cookie-based auth flow |
| PWA | `vite-plugin-pwa` | Generates the web app manifest + service worker needed for add-to-homescreen installability and static-asset caching (see [08-architecture-deployment.md](08-architecture-deployment.md)) |
| Theming | Vuetify's built-in theme system | Light + dark themes are both in scope for v1; Vuetify's theme tokens make supporting both from the start low-cost rather than retrofitting colors/contrast later |

**Mobile-first note:** Vuetify's grid and components are built mobile-first already, but layout decisions (bottom navigation vs. a side nav, touch target sizing, how much metadata shows on a card at 375px width) should be designed for a phone screen first and expanded up to desktop, not the reverse.

## Theming architecture

**Requirement:** theme colors (see [11-design-system.md](11-design-system.md)'s token table) must live in one clearly-located, globally-editable place — change a value once, it propagates everywhere — rather than being hardcoded per component. This mirrors a pattern used successfully on past projects (Vite globally injecting a single Sass variables file), adapted here to also cover Vuetify's own theme system rather than fighting it:

1. **Canonical palette in one JS/TS file** — e.g. `src/theme/palette.ts`, exporting a plain object with the light and dark variants of every token from [11-design-system.md](11-design-system.md) (`primary`, `accent`, `paper`, `paper-2`, `ink`, `line`). This file is the single source of truth — nowhere else defines a color literal.
2. **Fed directly into Vuetify's theme config** — `createVuetify({ theme: { themes: { light: { colors: {...} }, dark: { colors: {...} } } } })` imports from that same file. This drives every stock Vuetify component (buttons, chips, bottom nav, etc.) automatically.
3. **Bespoke components consume the same values via CSS custom properties, not a second Sass copy** — Vuetify 3 already exposes each theme color as a runtime CSS variable (`--v-theme-primary`, `--v-theme-surface`, etc.) on the root once step 2 is configured. The custom, non-Vuetify components this design calls for (ticket-stub card, stamp corner, avatar stack, half-star fill — see [11-design-system.md](11-design-system.md)) reference `var(--v-theme-primary)` and friends in their Sass rather than hardcoding hex or duplicating a parallel `$primary` Sass variable. One palette, two consumers (Vuetify's JS config and plain CSS), zero risk of the two drifting out of sync.
4. **A small companion Sass partial for anything outside Vuetify's theme model** — tokens like the dashed hairline `line` color aren't part of Vuetify's built-in theme color set. These live in `src/styles/_tokens.scss`, generated from/kept in sync with the same `palette.ts` values, and loaded globally via Vite's `css.preprocessorOptions.scss.additionalData` config so it's available in every `<style lang="scss">` block without a manual `@use` per file — this is the direct equivalent of the "Vite globally loads a Sass variables file" pattern from previous projects, just scoped to the handful of tokens Vuetify doesn't already cover.

This avoids the two-sources-of-truth trap a pure "global Sass file" approach would hit here: Vuetify wants its theme colors as a JS config object, not Sass variables, so mirroring the same hex values by hand in both a Sass file and a JS config would drift over time. Steps 1–3 mean there's exactly one file to edit for anything Vuetify already themes; step 4 covers the rest through the same globally-loaded-partial mechanism as before.

## Backend

| Piece | Choice | Why |
|---|---|---|
| Framework | Laravel (latest LTS-equivalent version) | Requested; mature ecosystem for auth, queues, and API resources |
| API style | RESTful JSON API via Laravel API resources | Straightforward mapping to a Vue SPA; no need for GraphQL's flexibility at this scale |
| Auth | Laravel Sanctum (SPA cookie-based auth) | Fits a same-site SPA + API setup behind one nginx host; no OAuth server needed for an invite-only app |
| Roles & permissions | [spatie/laravel-permission](https://spatie.be/docs/laravel-permission) | Well-established package for role/permission management, avoids hand-rolling an ACL |
| Queues | Laravel's built-in queue (database or Redis driver) | Needed for background jobs: syncing external API metadata, sending invite emails |
| Mail | Laravel Mail via SMTP (e.g. a transactional provider, or self-hosted SMTP) | For invite emails and password resets |
| File storage | Laravel's local disk driver | Stores user-uploaded avatar images (resized/validated on upload); posters/covers stay hotlinked from external APIs so this only needs to hold small profile images — see [08-architecture-deployment.md](08-architecture-deployment.md) for the volume/backup implications |
| Database | See [05-database-choice.md](05-database-choice.md) | |

## Infrastructure

| Piece | Choice | Why |
|---|---|---|
| Web/reverse proxy | nginx | Requested; serves the built Vue SPA as static files and reverse-proxies `/api` to PHP-FPM |
| Containerization | Docker Compose | Self-hosted single-VPS deployment; one `compose.yml` brings up nginx, PHP-FPM, the database, and a queue worker. Repo layout is `frontend/`, `backend/`, `docker/` (Dockerfiles, dev and production), `compose.yml` at root, see [08-architecture-deployment.md](08-architecture-deployment.md) |
| TLS | Let's Encrypt (certbot or a Caddy/nginx-proxy sidecar) | Free certs for a small self-hosted deployment |

See [08-architecture-deployment.md](08-architecture-deployment.md) for how these containers fit together.
