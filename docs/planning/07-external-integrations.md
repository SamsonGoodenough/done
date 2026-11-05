# External Integrations

Metadata is pulled from external APIs from v1 (per planning decision), one per media type, with manual entry as the Manager/Admin fallback ([03-features.md](03-features.md)).

## Movies & TV — TMDB (The Movie Database)

- **Why:** Free API key, generous rate limits, covers both movies and TV in one API, includes posters served from a CDN (`image.tmdb.org`), and is the de facto standard for this kind of app (Letterboxd, Trakt-adjacent tools all lean on it or similar).
- **Data pulled:** title, release/air date, overview, poster path, runtime (movies) / seasons & episode counts & status (TV), genres.
- **Auth:** single API key (v3) or bearer token (v4), no OAuth dance.

## Games — IGDB (chosen; supersedes an earlier RAWG recommendation)

| | IGDB | RAWG (rejected) |
|---|---|---|
| Auth | OAuth2 client-credentials flow via Twitch Developer account (2FA required, Client ID/Secret exchanged for a cached bearer token) | Single API key |
| Data depth | All five fields this project needs (title, release date, cover, platforms, developer) confirmed directly from current official docs | Comparable on paper, but see reliability below |
| Setup effort | Higher — register a Twitch Developer app, implement token fetch/cache | Low |
| Rate limit | 4 req/s, 8 concurrent (documented) | Unconfirmed — its own docs were unreachable during the research that led to this switch |
| Reliability | A couple of dated, resolved incidents on a third-party monitor; the Twitch OAuth dependency has a real public status page | Undocumented multi-day outages (e.g. a full-day blackout on 2024-05-05) with no status page and no public incident communication |

**RAWG was the original v1 recommendation** (single-key auth, matching the project's small-scale, low-ops posture) but was dropped after RAWG proved unreliable in practice: its site, live API, and docs all went down simultaneously (Cloudflare 522) during the research that triggered this change, on top of a previously documented full-day outage with zero public acknowledgement. GameVault, a comparable self-hosted game-library project, hit the same wall and migrated to IGDB for the same reason.

**Recommendation: IGDB.** The extra OAuth setup ceremony (Twitch Developer account, 2FA, client-credentials token fetch/cache) is a one-time implementation cost, not recurring ops burden, and is exactly the kind of vendor-specific detail [ADR 0001](../adr/0001-media-source-integration-architecture.md) already isolates behind a swappable client binding — the `games_api` capability name in `media_items.source` doesn't change. Full research, citations, and the runner-up (Giant Bomb) is in [`docs/research/games-api-alternatives-to-rawg.md`](../research/games-api-alternatives-to-rawg.md); see [ADR 0002](../adr/0002-games-metadata-vendor-igdb-over-rawg.md) for the decision record.

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

TMDB's API key, IGDB's Twitch Client ID/Secret, and (if added) a Google Books API key are configured via environment variables (`.env`, injected through Docker Compose), never committed to the repo. See [08-architecture-deployment.md](08-architecture-deployment.md).
