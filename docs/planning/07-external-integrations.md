# External Integrations

Metadata is pulled from external APIs from v1 (per planning decision), one per media type, with manual entry as the Manager/Admin fallback ([03-features.md](03-features.md)).

## Movies & TV — TMDB (The Movie Database)

- **Why:** Free API key, generous rate limits, covers both movies and TV in one API, includes posters served from a CDN (`image.tmdb.org`), and is the de facto standard for this kind of app (Letterboxd, Trakt-adjacent tools all lean on it or similar).
- **Data pulled:** title, release/air date, overview, poster path, runtime (movies) / seasons & episode counts & status (TV), genres.
- **Auth:** single API key (v3) or bearer token (v4), no OAuth dance.

## Games — RAWG (recommended) vs IGDB

| | RAWG | IGDB |
|---|---|---|
| Auth | Single API key | OAuth2 client-credentials flow via Twitch Developer account |
| Data depth | Good — covers most mainstream + indie titles, platforms, release dates, covers | Deeper (more metadata fields, more actively maintained by gaming-industry contributors), but more auth ceremony |
| Setup effort | Low | Higher (must register a Twitch app to get IGDB credentials) |

**Recommendation: RAWG for v1.** The simpler single-key auth matches the project's small-scale, low-ops posture — no reason to add an OAuth client-credentials flow (and a Twitch developer account dependency) for marginal data-depth gains. IGDB is worth revisiting if RAWG's coverage proves lacking for specific titles the group tracks.

## Books — Open Library (recommended) vs Google Books

| | Open Library | Google Books |
|---|---|---|
| Auth | None required | API key required, has daily quotas |
| Data depth | Good core metadata (title, author, cover, publish date, ISBN), community-maintained | Often richer descriptions/covers for mainstream titles, but quota-limited |
| Cost/limits | Free, no published hard quota for reasonable use | Free tier with a daily request cap |

**Recommendation: Open Library as primary, with Google Books as an optional fallback** if a specific title isn't found or has poor metadata (e.g. missing cover). Starting with just Open Library keeps v1 simpler (no API key to provision/rotate); adding the Google Books fallback is a small, isolated addition later if coverage gaps show up in practice.

## Sync & caching strategy

- When a user searches while adding an item, the search hits the relevant external API live (through the Laravel backend, not directly from the browser, to keep API keys server-side).
- Once a result is selected, its metadata is copied into the local `media_items` row (title, dates, poster URL, `metadata` JSON) — the app does **not** re-fetch from the external API on every page view. This keeps the app usable if an API is briefly down and avoids burning rate limits.
- A lightweight periodic job (e.g. weekly) can refresh metadata for existing items (catching a corrected release date, an updated TV show's episode count now that a new season aired), but this is a nice-to-have, not required for v1.
- Poster/cover images are hotlinked from each API's CDN (TMDB's image CDN, RAWG's/Open Library's cover URLs) rather than downloaded and re-hosted, to avoid storage/bandwidth overhead on the VPS. Revisit only if an API's images prove unreliable.

## Manual entries

Items added manually by a Manager/Admin (no `external_id`, `source = manual`) are excluded from the periodic metadata refresh job (nothing to refresh from) and are visually indicated in the UI (e.g. a small "manually added" badge) so users understand why the metadata might be sparser than an API-sourced item.

## API keys & config

TMDB, RAWG, and (if added) Google Books API keys are configured via environment variables (`.env`, injected through Docker Compose), never committed to the repo. See [08-architecture-deployment.md](08-architecture-deployment.md).
