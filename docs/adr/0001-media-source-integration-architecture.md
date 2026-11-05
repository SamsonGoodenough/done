# External media source integration architecture

**Status:** accepted

TMDB, RAWG, and Open Library are each wrapped in a hand-rolled client on Laravel's `Http` facade, no third-party SDK packages, all implementing a shared `MediaSource` contract (`search()` / `details()`) so the catalog-search and catalog-add flows never branch per-provider. A failure maps to one of two typed exceptions: "not found" (a bad `external_id`, no match at the source) and `MediaSourceUnavailableException` (timeout, 5xx, malformed response, retried once with a short backoff before it's thrown). The controller turns these into a consistent 404 vs 503 regardless of which source failed. We skip caching search results and the periodic metadata-refresh job for v1, per `07-external-integrations.md`, relying only on a basic per-user rate limit on our own search endpoint to stop an accidental retry loop from burning real API quota.

The `MediaSource` enum backing `media_items.source` is named by capability (`games_api`, `books_api`), not vendor, matching `04-data-model.md`'s literal values. The concrete vendor clients (`IgdbClient`, `OpenLibraryClient`) are bound to those enum cases at the service-container level, so swapping a vendor later touches only the binding, never the enum or stored rows (this is exactly what happened mid-build: `games_api` was originally meant to bind `RawgClient`, but RAWG was dropped before that client was ever written, see ADR 0002). Each `type` (movie/tv_show/game/book) maps to exactly one source; there's no cross-type merged search.

## Considered options

- **TMDB/RAWG SDK packages** — rejected: the surface area we need (search + detail lookup) is small, and hand-rolled clients on `Http` stay fully `Http::fake()`-able in tests without fighting a package's own client internals.
- **Open Library work-level dedup** — rejected: `04-data-model.md`'s book metadata already commits to `isbn`/`page_count`, which only exist at edition granularity, so we catalog at the edition level and accept that two editions of the same book become two `media_items` rows.
- **Vendor-named source enum** (`Rawg`, `OpenLibrary`) — rejected in favor of capability-named cases, to decouple the stored catalog data from whichever vendor happens to back it today.
