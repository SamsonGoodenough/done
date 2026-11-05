# Research: alternatives to RAWG for games metadata

**Date:** 2026-11-05
**Trigger:** RAWG reportedly has undocumented multi-day outages with no status communication, which is a dealbreaker for Done's games integration. This note checks that premise against real evidence and evaluates replacements.
**Status:** research only — no decision made here. See [07-external-integrations.md](../planning/07-external-integrations.md) (current RAWG vs IGDB writeup), [04-data-model.md](../planning/04-data-model.md) (`media_items` game `metadata` shape), and [ADR 0001](../adr/0001-media-source-integration-architecture.md) (integration architecture, designed so swapping the games vendor only touches a client binding).

## Note on this document's location

No `docs/research/` folder or other research-notes convention existed in this repo before this file. This location and filename were invented for this task; if a different convention gets established later, this file should move to match it.

## 1. Grounding the premise: is RAWG actually unreliable?

Yes, and I was able to reproduce it live during this research session, not just find old reports.

**Live reproduction (2026-11-05, ~15:23 UTC):** three separate direct requests from this session all failed identically with Cloudflare's `522 Connection timed out` (Cloudflare reached RAWG's edge but RAWG's origin server never responded):

- `https://rawg.io` → HTTP 522 (after ~19.5s)
- `https://api.rawg.io/api/games?page_size=1` → HTTP 522 (after ~19.8s)
- `https://api.rawg.io/docs/` → HTTP 522 (after ~19.5s)

All three of RAWG's surfaces (marketing site, live API, API docs) were down at the same moment, which also means I could not pull RAWG's own current rate-limit/pricing text directly from its docs for this note — that's a direct, first-hand gap caused by the very problem this research is investigating.

**Documented prior outage:** a full-day outage on **May 5, 2024** is described in a GameVault (a comparable self-hosted game-library project) blog post: "the situation peaked on May 5th when the service went down completely for a day," after the author describes RAWG having become "an unmaintained abandonware, causing frequent downtime and issues like incorrect search results and registration problems." ([gamevau.lt/blog/2024/05/07](https://gamevau.lt/blog/2024/05/07/)) A contemporaneous r/OpenAPI thread corroborates it from the developer side, with one commenter saying the whole site appeared down and asking if anyone had the data saved. ([reddit via redlib mirror](https://redlib.ssps.io/r/OpenAPI/comments/1ckswdm/rawg_api/l2vn5qy/?context=3)) Neither source shows any public statement from RAWG acknowledging the outage.

**No status page:** I found no official RAWG status page anywhere. The only "status" trackers are third-party, crowdsourced monitors (e.g. updownradar.com), which are not authoritative and showed inconsistent/contradictory readings across different pages for the same service.

**A peer project already switched away from RAWG for this exact reason:** GameVault (open-source, self-hosted game library/tracker — functionally the closest public analog to this project's games feature) is moving to a provider-agnostic design and "will primarily use IGDB's APIs out of the box," with a Steam-backed shim (`rawg-to-steam-redirect`, github.com/Phalcode/rawg-to-steam-redirect) as a stopgap. This is independent, real-world validation of both the premise (RAWG is unreliable enough to migrate away from) and one candidate answer (IGDB).

**Conclusion on the premise:** confirmed, not just "reportedly." Treat RAWG as currently down-or-flaky as of this research, with at least one well-documented full-day blackout in its history and zero public incident communication channel.

## 2. Candidates evaluated

Per the task, I went to each provider's own current official documentation first. Two of the three alternative providers' live sites (Giant Bomb, MobyGames) blocked automated fetches with a Cloudflare bot challenge (403 / JS challenge page) in this session — this is ordinary bot-blocking, not evidence of an outage. For those two I used the Internet Archive's Wayback Machine to retrieve a recent archived copy of the same official docs page, and I say so explicitly wherever that applies. IGDB's official docs were fetched live and directly.

### IGDB

Source: `https://api-docs.igdb.com/` — fetched **live**, directly, in this session (HTTP 200).

- **Auth / setup:** Requires a Twitch account with two-factor authentication enabled, then registering an application in the Twitch Developer console to get a Client ID and Client Secret. Those are exchanged via an OAuth2 **client-credentials** grant against `https://id.twitch.tv/oauth2/token` for a bearer access token, which is then sent alongside the `Client-ID` header on requests to `https://api.igdb.com/v4`. This matches exactly what `07-external-integrations.md` already describes — this research didn't find anything simpler or less ceremonious than that characterization.
- **Pricing:** Free for non-commercial use — "The IGDB.com API is free for non-commercial usage under the terms of the Twitch Developer Service Agreement." Commercial partnerships exist but aren't relevant here.
- **Rate limits (documented in the official docs):** **4 requests per second**, with up to **8 concurrent open requests**; exceeding this returns HTTP 429. This is the only candidate with an explicit, provider-stated numeric rate limit I could confirm directly from primary docs.
- **Field confirmation (all quoted/paraphrased from the Games/Cover/Platform/Involved Company/Company field tables in the docs):**
  - Title → `name` (String) on the Game object.
  - Release date → `first_release_date` (datetime) on the Game object.
  - Cover/box-art image URL → `cover` is a reference ID to a `Cover` object; the `Cover` object has `url` (String) — "The website address (URL) of the item." Resolvable in one query via IGDB's field-expansion syntax, e.g. `fields name,cover.url,first_release_date;`.
  - Platforms (list) → `platforms` is an array of Platform reference IDs; the `Platform` object carries a `name`. Resolvable in one query via `platforms.name`.
  - Developer/studio name → `involved_companies` is an array of `Involved Company` reference IDs; each has `company` (reference to a `Company`), plus booleans `developer`, `publisher`, `porting`, `supporting`. The `Company` object has `name` (String). Resolvable in one query via `involved_companies.company.name` filtered where `involved_companies.developer = true`.
  - All five required fields are confirmed present and gettable in a **single** IGDB Apicalypse query using dot-notation expansion — this is not materially more round trips than RAWG's flatter JSON, just a different query syntax.
- **Reliability signal:** Twitch's own public status page, `status.twitch.tv`, is live and reachable (HTTP 200 checked in this session) and covers the OAuth dependency IGDB's auth rides on. A third-party synthetic monitor (isdown.app) has logged occasional IGDB incidents — e.g. a ~2 hour "Web API login" outage around March 1–2, 2026, and a separate multi-day "intermittent issues with web api" incident starting March 2, 2026 — so IGDB is not perfectly uptime-proof either, but these are discrete, dated, resolved incidents, a materially different situation from RAWG's undocumented, unexplained multi-day blackouts.
- **Caveat found in the docs:** IGDB has an active "Important Changes Coming to the IGDB API" notice about migrating from static enum values to table-based structures. Per the docs text, this is scoped to enum-style fields (the example given is Age Rating categories) — it does not appear to affect the core `name`/`cover`/`platforms`/`involved_companies` fields this project needs, but it's a live schema-evolution notice worth knowing about before building against v4.

### Giant Bomb

Source: `https://www.giantbomb.com/api/documentation` — live fetch blocked by Cloudflare bot challenge (HTTP 403) in this session; used the Wayback Machine's archived copy from **2025-11-28** instead (`http://web.archive.org/web/20251128200808/https://www.giantbomb.com/api/documentation/`). Flagging this clearly: this is an archival stand-in for the official docs, not a live read.

- **Auth / setup:** Single `api_key` query parameter on every request (e.g. `?api_key=[YOUR API KEY]`), obtained from a Giant Bomb account. This is RAWG-equivalent in simplicity — the lowest-friction of all candidates checked. I could not independently verify the account sign-up flow itself (giantbomb.com's live site was Cloudflare-blocked throughout this session).
- **Pricing:** Free; no tiers found in the archived docs.
- **Field confirmation (from the archived Game resource field list):**
  - Title → `name` ("Name of the game").
  - Release date → `original_release_date` ("Giant Bomb's best guess release date of the game"), with `expected_release_day/month/quarter/year` as fallbacks when an exact date isn't known.
  - Cover/box-art image → `image` ("Main image of the game").
  - Platforms (list) → `platforms` ("Platforms the game appeared in").
  - Developer/studio name → `developers` ("Companies who developed the game"), distinct from `publishers` ("Companies who published the game").
  - All five fields are present directly on the flat Game resource — no reference-ID joins needed, the simplest schema of the three alternatives.
- **Rate limits:** **Not stated anywhere in the official docs page itself** that I could find (archived or otherwise). Third-party client libraries and community write-ups consistently cite an unofficial **200 requests per resource per hour per API key**, with a separate per-second burst check, returning 429 when exceeded — but this number is secondhand (Ruby/R client library docs, a machine-readable third-party API spec), not confirmed from Giant Bomb's own documentation. Treat the actual ceiling as unconfirmed.
- **Reliability signal:** weak and mixed. A third-party MCP-connector listing flagged the underlying API as reported "offline as of 2026-05" — this is a single secondary source, not independently verified by me, so I'm flagging it as a yellow flag rather than a confirmed outage. I was also unable to reach the live giantbomb.com site at all in this session (Cloudflare bot-block, not necessarily downtime) — which means, unlike IGDB and the live RAWG check, I have no firsthand read on Giant Bomb's current live reachability, only the archived docs snapshot.

### MobyGames

Source: `https://www.mobygames.com/info/api` — live fetch blocked by Cloudflare bot challenge in this session; used the Wayback Machine's archived copy from **2026-09-12** instead (`http://web.archive.org/web/20260912233428/https://www.mobygames.com/info/api/`) — a recent snapshot, about 7 weeks old.

- **Auth / setup:** API key via the `api_key` query parameter, but per the docs, the key itself is obtained by **subscribing** to the API ("You can subscribe to our API to get an API key. We have options for hobbyists as well as users with a commercial application in mind."). Unlike RAWG, IGDB, and Giant Bomb, this is not simply "sign up and get a free key" — it's a paid subscription product (branded "MobyPro"/"MobyPlus" in secondary sources), with a hobbyist tier whose actual price I could **not** confirm from any primary source in this research. Secondary sources mention a separate "Moby Professional" membership at roughly $14.99/mo, but I could not confirm that figure applies to API access specifically rather than to an unrelated professional-networking product on the same site — this is a real gap in what I could verify.
- **Rate limits (documented in the official docs):** non-commercial API requests limited to **720 per hour** (roughly one every five seconds, max rate 1/second); a "legacy" non-commercial tier is capped at **360 per hour**. These are the only other explicitly-documented numeric rate limits found besides IGDB's.
- **Field confirmation (from the docs' own `/games` JSON samples, `format=normal`):**
  - Title → `title`.
  - Release date → **not a single top-level field** — `platforms[].first_release_date` is per-platform (e.g. `"platform_name": "Windows", "first_release_date": "1998"`), so a single `release_date` would need to be derived (e.g. earliest platform date), a structural mismatch against this project's flat `release_date` column.
  - Cover/box-art image → `sample_cover.image` / `sample_cover.thumbnail_image`.
  - Platforms (list) → `platforms[].platform_name`, confirmed.
  - Developer/studio name → **not present in the main `/games` or `/games/{id}` response at all.** It only appears nested under the separate `/games/{id}/platforms/{platform_id}` endpoint, inside `releases[].companies[].role` (the one sample shown uses `"role": "Published by"` — I could not confirm from the docs that a "Developed by" role string is actually used, only inferred it by analogy). Getting a developer name would require one extra API call per platform per game, plus string-matching a role field whose exact developer-indicating value isn't shown in the docs I could retrieve.
- **Reliability:** no outage reports or status page found either way — neither evidence of problems nor evidence of a published uptime commitment.

### Steam (official Web API + unofficial storefront API) — ruled out

- The official, documented Steam Web API (free, API-key gated, capped at 100,000 calls/day per Valve's terms per secondary sources — I did not fetch Valve's own terms page directly in this session) is built around player stats, achievements, news, and app ownership — it has no documented endpoint for searchable game catalog metadata (title/release date/cover/platforms/developer).
- The endpoint that actually has that data, the storefront `appdetails` endpoint (returns `name`, `release_date`, `header_image`, `platforms`, `developers`), is **explicitly undocumented by Valve**. This is confirmed via multiple community/secondary sources (the Revadike "Internal Steam Web API" GitHub wiki; the SteamWebApiLib project's own disclaimer that "the Steam API is not officially available or documented... collected by trial and error"). No official docs exist to check these fields against, which fails this task's own evidentiary bar outright — and there's no SLA, no rate-limit guarantee, and the endpoint can change without notice.
- Also structurally narrower: Steam only covers titles sold on Steam, which undercounts console-only and older titles the project's game metadata needs (the data model's own example metadata — `"platforms": ["PS5", "PC"]` — assumes non-Steam-exclusive coverage).
- **Ruled out** on both grounds: no official documentation to confirm fields against, and real coverage gaps.

## 3. Rate limits / pricing summary (at this project's scale: well under 50 users)

| | Auth | Documented rate limit | Pricing |
|---|---|---|---|
| RAWG (current) | Single API key | Unconfirmed in this session (docs unreachable — see §1) | Free (per prior docs) |
| **IGDB** | OAuth2 client-credentials via Twitch Dev account + 2FA | **4 req/s, 8 concurrent** (documented) | Free, non-commercial |
| Giant Bomb | Single API key | Not in official docs; ~200/hr/key per unofficial third-party sources | Free |
| MobyGames | Single API key, but key requires a paid subscription | **720/hr non-commercial** (documented) | **Paid** (hobbyist tier price unconfirmed) |
| Steam (storefront) | None documented (undocumented endpoint) | None documented | Free, but not an official API at all |

At well under 50 users, IGDB's 4 req/s / 8-concurrent ceiling and Giant Bomb's ~200/hr figure are both comfortably sufficient for this project's "search on add, cache in `media_items`, no periodic re-fetch except an optional weekly refresh" pattern described in `07-external-integrations.md`. MobyGames' 720/hr is also sufficient but is the only candidate with a real, confirmed cost.

## 4. Recommendation

**IGDB should replace RAWG.** The auth/setup complexity is worth it at this project's small-scale, low-ops posture, for these reasons weighed together:

1. It's the only candidate where **all five required fields are confirmed directly from current official documentation**, with exact field names cited above (`name`, `first_release_date`, `cover.url`, `platforms.name`, `involved_companies.company.name` + `.developer`).
2. It's the only candidate with an **explicit, provider-published numeric rate limit** that I could confirm live from the provider's own docs (Giant Bomb's is secondhand; MobyGames' is real but gated behind a paid key; RAWG's docs were unreachable entirely during this research).
3. It's free for this project's non-commercial, self-hosted use.
4. Its OAuth dependency (Twitch) has a real public status page, giving this project an actual signal to check when game-metadata search misbehaves, something RAWG has never had.
5. A directly comparable peer project (GameVault — another self-hosted game-library tracker) independently hit the same RAWG reliability wall this project is reacting to, and chose IGDB as its primary replacement. That's real-world validation, not just documentation-reading.

The Twitch OAuth client-credentials setup is genuinely more ceremony than RAWG's static key (register a Twitch Developer app, enable 2FA on the account, implement a token-fetch-and-cache step instead of embedding one key) — but per ADR 0001, this project already isolates vendor specifics behind a `MediaSource` client binding, and a client-credentials token fetch is a one-time implementation cost absorbed inside `RawgClient`'s eventual replacement (`IgdbClient` or similar), not a recurring operational burden for the one person running this VPS. Laravel's `Http` client plus a cached bearer token (refreshed on 401/expiry) is a well-worn pattern, not a bespoke one.

**If IGDB turns out to be annoying in practice,** Giant Bomb is the one other candidate worth a second look — it matches RAWG's single-API-key simplicity and has the flattest schema of all four candidates (every field sits directly on the Game resource, no reference-ID joins). But I'm not recommending it as the primary pick today because two real unknowns remain unresolved by this research: its rate limit isn't in its own official docs (only inferred from third-party client libraries), and I could not reach its live site at all in this session to check current reachability firsthand (only an archived snapshot). Those are gaps worth closing (e.g. by emailing Giant Bomb's API contact, or simply trying it) before leaning on it, not reasons to rule it out.

**MobyGames and Steam are not recommended.** MobyGames has a real data-depth gap (no single-call developer name, release date only available per-platform) and is the only candidate that costs money, with the actual cost unconfirmed. Steam has no official catalog-metadata API to even evaluate against this task's "confirm from official docs" bar.

## 5. Gaps and caveats in this research

- RAWG's own current docs/rate-limit/pricing text could not be checked **at all** in this session — its docs endpoint was down (522) the whole time, which is itself the headline finding, but it also means I'm relying on `07-external-integrations.md`'s prior summary of RAWG rather than a fresh read for the "before" side of this comparison.
- Giant Bomb and MobyGames docs were both read via Wayback Machine archives, not live fetches, because their live sites returned Cloudflare bot-challenge pages to this session's fetches. The archives are recent (2025-11-28 and 2026-09-12 respectively) and I have no reason to think the documented fields/auth model have changed since, but this is a real difference in evidentiary strength versus IGDB and RAWG, which were (attempted to be) read live.
- Giant Bomb's actual rate limit and current live reachability are both unconfirmed by me firsthand — flagged above, not papered over.
- MobyGames' actual hobbyist-tier API subscription price could not be confirmed from any primary source.
- I did not find Valve's own Web API terms page directly (the 100,000 calls/day figure is cited via secondary sources), though this didn't change the Steam ruling since the real disqualifier is the undocumented storefront endpoint, not the official API's quota.
- "IGDB reliability" evidence is thinner than RAWG's: a couple of dated, resolved incidents from a third-party monitor (isdown.app) rather than IGDB's own status page (I did not find one), plus Twitch's own status page covering just the auth leg. This is still a materially stronger reliability posture than RAWG's zero-incident-trail blackouts, but it's not literally "IGDB has never gone down."
