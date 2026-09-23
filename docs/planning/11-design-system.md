# Design System — "Stub & Stamp, Cinematic Cut"

Chosen visual direction after reviewing three mocked-up options (ticket-stub archive, cinema marquee/arcade, naturalist field log), refined once into the "Passport & Postmark" execution (light kraft-paper surfaces, monospace-only type, a favourite heart-chain stamp, an "All Activity" / "My Activity" carousel structure), then revisited a second time in a newer prototype that fuses the original **Stub & Stamp** ink-stamp motifs with a **grainy, cinematic, large-serif** direction — big film-grain hero art, a serif display face, clean paper-toned reading sections for review/comment text. That second pass, "Cinematic Cut," is the current chosen direction and is what's documented below. Passport & Postmark's mockup has been retired in favour of it; anything from that era that Cinematic Cut doesn't explicitly revisit is called out under [Carried over, not yet re-validated](#carried-over-not-yet-re-validated-in-cinematic-cut) below rather than assumed still correct.

This doc records the design language so it survives past the mockup — colors, type, layout motifs, iconography, and the nav decision — for translation into the real Vuetify build in [02-tech-stack.md](02-tech-stack.md).

## Reference mockup

Unlike Passport & Postmark (a single self-contained, editable HTML page), Cinematic Cut exists as a design canvas prototype with no single interactive file to open. What's saved in this repo instead:

- [`mockups/design-tokens.css`](mockups/design-tokens.css) — the actual CSS custom properties and motif classes (`.grain`, `.stamp-postmark`, `.tear`, `.status-tag`), ready to import as-is.
- [`mockups/screens/`](mockups/screens) — full-page PNG exports of all six artboards (Shelf, Detail, Activity, each mobile 390×844 and desktop 1440×900), for visual reference without needing the original canvas tool.

The live canvas itself (editable, if the tool is available): https://claude.ai/artifact/FpLknz2FhJVfHRu4WoEmfj. There's also a separate, tangential prototype that restyled the `04-data-model.md` documentation page itself (not the app) in a related but distinct palette: https://claude.ai/artifact/VRTJn63LJkayxydjvG48Yr — lower priority, not incorporated into this doc, noted here only so the link isn't lost.

Sample content on the artboards uses placeholder titles (*Dune: Part Two*, *Severance*, *Baldur's Gate 3*, *Project Hail Mary*, *The Bear*, *Oppenheimer*, *Hades*, *Klara and the Sun*) and placeholder friend names (Jules, Priya, Theo) — none of it is real data.

## Concept

A ticket-booklet reading of your library: a completed item is a torn stub you keep, not just a list row. Ratings, spoiler warnings, and completion status get an ink-stamp treatment (rotated corner stamps, postmark rings, dashed "tear" lines, monospace ticket-printer labels). Cinematic Cut renders that concept against near-black, film-grain hero and poster art — big, cinematic visuals — carrying the ink-stamp details in the original oxblood/mustard palette, alternating with clean cream paper sections for the actual reading (reviews, comments) so long-form text doesn't fight the grain. Built for a small circle who already know each other's taste and just want the receipts — plain, a little nostalgic, not trying to be a social network.

## Color

| Token | Hex | Used for |
|---|---|---|
| `--bg` | `#120E0A` | Dark/cinematic surface — hero art, poster grids, nav |
| `--bg-2` | `#1C1611` | Card/nav surface on dark |
| `--ink` | `#F4EEDF` | Primary text on dark |
| `--ink-dim` | `#B7AC97` | Secondary text on dark |
| `--paper` | `#F6F1E6` | Light surface — review/comment reading sections |
| `--paper-ink` | `#1F2A44` | Primary text on paper |
| `--paper-dim` | `#6B6152` | Secondary text **on paper only** — fails contrast on `--bg`, use `--ink-dim` there instead |
| `--primary` | `#B23A2E` | Oxblood ink — Completed status, rating-stamp ring, heart reaction |
| `--accent` | `#B8892F` | Mustard gold — In Progress status, thumbs-up reaction, hero kicker text |
| `--teal` | `#6E9491` | On Hold status, fire/flame reaction |

`--primary` and `--accent` are carried over unchanged from Passport & Postmark (and the Stub & Stamp exploration before it) for continuity — same ink, now stamped onto a darker, grainier ground. `--teal` is new in this pass (previously On Hold shared a neutral tag color).

**Open question — light/dark mode:** [02-tech-stack.md](02-tech-stack.md) commits to Vuetify light *and* dark themes for v1, and Passport & Postmark had a full light/dark pair for every token. Cinematic Cut's `--bg` / `--paper` split reads instead as two surface *types within one screen* (dark hero vs. paper reading section), not a user-togglable light/dark mode. Whether Cinematic Cut's dark surface simply *is* the app's dark theme (with a separate, not-yet-designed light theme built from the same tokens), or whether the light/dark toggle decision needs revisiting now that the direction is dark-first, is tracked in [10-open-questions.md](10-open-questions.md).

## Typography

Three families, all Google Fonts, loaded via one `<link>` in the prototype:

```
https://fonts.googleapis.com/css2?family=Bodoni+Moda:ital,opsz,wght@0,6..96,400;0,6..96,500;1,6..96,400;1,6..96,500&family=Archivo:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap
```

| Role | Face | Usage |
|---|---|---|
| Display | **Bodoni Moda** (`--font-serif`) | App wordmark ("*Done.*", italic), media titles, section headers. Large, high-contrast, cinematic. |
| Body | **Archivo** (`--font-sans`) | Reviews, comments, running text. |
| Labels / meta / stamps | **IBM Plex Mono** (`--font-mono`) | Status tags, meta lines ("Film · 2024"), stamp numerals, nav labels — uppercase + letter-spaced. |

This reverses Passport & Postmark's "no webfonts, system stacks only" decision (that constraint was about the mockup tool's CSP and avoiding a font-loading dependency; Cinematic Cut's large-serif display type isn't achievable from a system stack). **Open question:** whether the real build loads these from Google Fonts' CDN or self-hosts the three families — self-hosting fits the app's self-hosted/offline-PWA posture better (no third-party request, works if Google Fonts is unreachable) but adds the font files to the repo/image. Tracked in [10-open-questions.md](10-open-questions.md).

## Motifs — how each is actually built

All implemented as plain CSS/SVG, no images. See [`mockups/design-tokens.css`](mockups/design-tokens.css) for copy-pasteable versions of the ones below marked *(css)*.

- **Film grain** *(css)* — a `.grain` overlay div (`position:absolute;inset:0`) with an inline SVG `feTurbulence` data-URI background, `mix-blend-mode:overlay`, low opacity. Stack it as the first child of any `position:relative` dark surface (hero art, poster cards).
- **Postmark rating badge** *(css)* — `.stamp-postmark`: a circle, `background:var(--paper)`, `border:1.5px solid var(--primary)`, a dashed inner ring via `::before` inset border, `transform:rotate(-9deg)`, drop shadow. Holds the numeric rating (e.g. "4.5") + "RATED" in mono. This is the *display* treatment for a logged rating.
- **Visa-stamp rewatch history** — smaller dashed-ring circles (no fill badge, just the dashed ring + mono month/day), shown in a row for each prior watch of a re-logged item.
- **Ticket perforation** *(css)* — `.tear`: a dashed `border-top`, used as a section divider (rating → tag-friends → review → footer) inside the cream reading sections.
- **Status tag** *(css)* — `.status-tag`: mono uppercase pill, `border:1px solid currentColor`, `rotate(-3deg)`. Color = `--primary` (Completed), `--accent` (In Progress), `--ink-dim`/`--paper-dim` (Planned/Dropped, dark/light bg respectively), `--teal` (On Hold).
- **Spoiler mask** — inline `span` with `color:transparent; text-shadow:0 0 8px color-mix(in srgb, var(--paper-ink) 65%, transparent)` — blurs the masked text without hiding its shape. Paired with a "Contains Spoilers" status tag.
- **Reaction pills** — small rounded-pill badges, inline SVG icon (stroke-based, never emoji) + mono count. Only 3 of the schema's 6 reaction types are illustrated so far (heart, thumbs-up, flame) — see Open items below.
- **Avatar stack** *(carried over)* — Tag Friends / "Watched with" renders as overlapping circular avatars (negative margin overlap) ending in a dashed-outline "+" add slot.
- **Half-star rating** *(carried over)* — a two-layer text trick for *entering* a rating: an outline row of five `★` in a dim color, with a second identical row in `--primary`/`--accent` clipped to `width: calc(rating / 5 * 100%)` via `overflow: hidden`. Cheap in any framework, half-star precision falls out for free from the percentage width. Cinematic Cut's screens only show the postmark badge (display, not entry) — this widget is assumed to still be how a rating gets entered in the add/rate flow, which Cinematic Cut hasn't designed yet (see Open items).
- **Favourite background stamp** *(carried over)* — a favourited item gets a large postmark rendered *behind* the card's content: two concentric dashed/solid rings centered just past the card's top-right corner (clipped by the card's rounded edge) at low opacity, with a chain of 5 small heart icons strung along the ring gap. Not shown on any Cinematic Cut artboard, so unconfirmed whether it survives the shelf-card redesign; see Open items.
- **Reduced motion respected** *(carried over)* — no motion beyond default UI transitions; `prefers-reduced-motion: reduce` disables what little there is.

## Screens

All three screens exist as both a mobile artboard (390×844) and a desktop artboard (1440×900). See [`mockups/screens/`](mockups/screens) for the exports.

### Shelf
Poster grid (2-col mobile, 4-col desktop) of the signed-in user's `UserMedia` rows. Each card: gradient/grain "poster" placeholder, a type tag (Film/TV/Game/Book), a postmark rating badge when `LogEntry.rating` exists, a rotated status tag (`UserMedia.status`), title + type/year meta below. Filter chips across the top match the status enum exactly (All / Planned / In Progress / Completed / On Hold / Dropped). Mobile: bottom tab bar (Shelf/Search/Add/Activity/Profile — see Navigation below). Desktop: left sidebar nav instead.

### Detail
One `LogEntry` in full: big hero poster art with gradient scrim + overlapping postmark rating badge, title, status + logged date, "Watched with" tagged friends (`LogEntryParticipant`), rewatch visa-stamps (prior `LogEntry` rows for the same target) when `is_rewatch`, the review text with spoiler mask (`contains_spoilers`), reaction pills (3 of 6 types shown), and a couple of `Comment` previews with a "view all N" link. Mobile stacks hero-over-body; desktop splits into a sticky poster column + scrolling content column.

### Activity
A single chronological feed of friends' `LogEntry` rows (queried via the `Friendship` graph). Each item: a small postmark date stamp, friend avatar + name, poster thumbnail, type/rating or type/status meta, a short review excerpt, and reaction/comment counts. Desktop adds a connecting vertical rail between postmark stamps (timeline feel) and more items per view. **This is a single feed** — it doesn't distinguish "your own activity" from "friends' activity" the way Passport & Postmark's two-section (All Activity / My Activity, with a swipeable "boarding pass" carousel for your own entries) structure did. Whether that split still applies on top of this visual treatment, or whether a single feed is the intended simplification, is tracked in [10-open-questions.md](10-open-questions.md).

## Navigation — 5-slot bar with a raised center "Add"

Consistent with Passport & Postmark's decision, carried through unchanged: **fast, low-friction review logging is the app's core loop** ("finish something → log it," from [03-features.md](03-features.md)'s mobile-first notes), so the add action gets the most prominent single slot in the bar rather than being buried inside Search.

- **Layout:** Shelf · Search · **Add** (raised, primary-red fill, drop shadow) · Activity · Profile.
- **Behavior:** tapping Add opens the fast-log flow directly — search/select an item and log a completed watch/play/read with rating, review, spoiler flag, favourite, and Tag Friends in one flow — rather than routing through the Search tab first.
- **Search tab** still exists for browsing the catalog / adding something to your backlog (Planned status) without necessarily logging a completion yet.

This is a UX decision, not just a visual one — see the note in [03-features.md](03-features.md).

## Carried over, not yet re-validated in Cinematic Cut

Cinematic Cut's prototype only covers Shelf, Detail, and Activity — it's a style and layout reference, not production code (no framework, no real API data, no accessibility pass beyond basic contrast). The following decisions from Passport & Postmark aren't contradicted by anything in Cinematic Cut, but also aren't shown on any of its artboards, so treat them as still-standing defaults rather than confirmed:

- The favourite heart-chain background stamp.
- The half-star rating widget as the rating-*entry* control (vs. the postmark badge, which is confirmed as the rating-*display* treatment).
- The "All Activity" / "My Activity" swipeable-carousel structure on the Activity page.

## Open items — not yet designed

Cinematic Cut deliberately covers 3 of the app's screens to establish the direction, not the whole app. Still open, following the same visual system:

- Episode/season-level logging UI (`Episode`, the `episode_id`/`season_number` split on `LogEntry`) — Phase 4 of the data model.
- A dedicated friends list / friend-request screen (`Friendship` status: pending/accepted/declined) — Phase 5.
- A full comment thread view (nested replies via `Comment.parent_comment_id`) — currently only a 2-comment preview exists.
- All 6 reaction types (`thumbs_up, heart, laugh, wow, sad, fire`) — only heart/thumbs-up/flame have icons so far.
- A notifications screen (`Notification`, 6 types) — Phase 7.
- Add/rate flow (creating a `LogEntry`: status, rating, review, spoiler toggle, tag friends) — implied by the Shelf/Detail screens but not built as its own screen. This is also where the half-star entry widget would live.
- Auth/invite screens (`Invitation`) — Phase 1, not covered at all.

## Iconography

Simple stroke icons (`stroke-width: 1.6-1.7`, `currentColor`, no fill except small solid accents) built from basic shapes — rects, lines, circles — rather than complex path data: film-strip, TV, game controller, book, bell, heart, comment, plus, grid (shelf), search, activity (pulse), profile. Kept deliberately plain so they read as "ticket iconography" rather than a generic icon-font pull.

## Translating to the real build

This mockup is plain HTML/CSS (canvas-tool export), not Vuetify. When Phase 8 ([09-roadmap.md](09-roadmap.md)) builds the real frontend:

- Colors become a custom Vuetify theme using the token table above — see "Theming architecture" in [02-tech-stack.md](02-tech-stack.md) for the single-source-of-truth mechanism.
- The postmark rating badge, visa-stamp rewatch rings, ticket-perforation divider, status tag, spoiler mask, reaction pills, avatar stack, and half-star rating are all **bespoke components** — none are stock Vuetify components, they're custom components styled with Sass on top of Vuetify's base (cards/buttons/inputs) per [02-tech-stack.md](02-tech-stack.md).
- The raised center nav button is a custom-positioned button over/within Vuetify's `v-bottom-navigation`, not a stock variant of it.
- The three Google Fonts need adding to the font-loading strategy (see the Typography open question above) alongside Vuetify's default type config.
