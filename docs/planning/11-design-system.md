# Design System — "Stub & Stamp"

Chosen visual direction after reviewing three mocked-up options (ticket-stub archive, cinema marquee/arcade, naturalist field log). This doc records the design language so it survives past the mockup — colors, type, layout motifs, iconography, and the resulting nav decision — for translation into the real Vuetify build in [02-tech-stack.md](02-tech-stack.md).

Of three follow-up personality variations on this base style (Pegboard & Pin, Card Catalog, Passport & Postmark), **Passport & Postmark won** and is the one carried forward below and into the reference mockup.

## Reference mockup

The living, up-to-date HTML mockup is saved at [`mockups/passport-and-postmark.html`](mockups/passport-and-postmark.html) — open it directly in a browser. It's the single source of truth for the visual details below (colors are live-adjustable via the pickers at the top of the page); treat this doc's prose as a summary of what's in that file, not a replacement for it. It covers: postmark-style ratings, the favourite background stamp (a heart-chain postmark clipped into a card's top-right corner), the "All Activity" / "My Activity" page structure, and the swipeable desktop/mobile carousels.

## Concept

A ticket-booklet reading of your library: a completed item is a torn stub you keep, not just a list row. Ratings, spoiler warnings, and completion status get an ink-stamp treatment (rotated corner stamps, dashed "tear" lines, monospace ticket-printer type). Built for a small circle who already know each other's taste and just want the receipts — plain, a little nostalgic, not trying to be a social network.

## Color

Two independent knobs, both user-adjustable at build time via Vuetify theme tokens (not hardcoded) so future re-tuning doesn't mean re-deriving these:

| Token | Role | Light | Dark |
|---|---|---|---|
| `primary` | Stamp red — primary actions, active states, filled star portion, the "+" add button | `#B23A2E` | `#B23A2E` (holds up fine on dark; revisit if contrast testing says otherwise) |
| `accent` | Gold — secondary highlights, avatar fallback color 2 | `#B8892F` | `#B8892F` |
| `paper` | App background | `#F7F3E7` | `#221D17` |
| `paper-2` | Card / surface background (stub cards, detail card) | `#EDE6CF` | `#171310` |
| `ink` | Primary text, icons, borders-when-solid | `#1F2A44` | `#EFE7D6` |
| `line` | Hairlines, dashed "tear" dividers, chip borders | `#C7BA98` | `#4A4032` |

Light `paper`/`paper-2` were deliberately pulled close to white (from an initial `#EAE1CB`/`#DCCFA9` kraft tone) per direct feedback — keeps the kraft-paper warmth without reading as tan/beige at a glance.

`primary` and `accent` are the two colors exposed as live pickers in the mockup and should map directly to Vuetify's `theme.colors.primary` / `theme.colors.secondary` (or a custom `accent` key) — everything else (`paper`, `paper-2`, `ink`, `line`) is theme-structural and swaps as a pair between light/dark, not user-tunable.

All six tokens above are meant to live in exactly one editable place in the real build, not be copy-pasted across components — see "Theming architecture" in [02-tech-stack.md](02-tech-stack.md) for the mechanism (a single palette file feeding both Vuetify's theme config and the bespoke components via CSS custom properties).

## Typography

| Role | Face | Usage |
|---|---|---|
| Display / labels / nav / stamps | `ui-monospace, "SF Mono", "Courier New", monospace` | Section headings, nav labels, chip text, stamp corner badge, ticket metadata — the "ticket printer" voice |
| Body | System sans (`-apple-system, "Segoe UI", system-ui, sans-serif`) | Review text, feed copy — needs to stay readable at length, so it stays plain |
| Numerals | Same monospace, `font-variant-numeric: tabular-nums` | Ratings, counts, dates — anything columnar |

No webfonts — system stacks only, both for the mockup's CSP constraints and because it avoids a font-loading dependency in the real app too. If a licensed monospace face is wanted later for more character (e.g. an actual ticket/dot-matrix face), swap it in at the token level; the design doesn't depend on this exact system font.

## Layout motifs

- **Ticket-stub cards** — shelf items render as a stub: poster block, a dashed "tear" line with punched semicircle notches on each end, then title/meta below the tear.
- **Stamp corner** — the currently-open item detail card gets a rotated (~9°) outlined badge in the corner ("Admit One"), stamp-red.
- **Dashed dividers** — `1.5px dashed var(--line)` separates sub-sections within a card (rating → tag-friends → review → footer) instead of solid rules, keeping the ticket-paper feel consistent throughout, not just on the stub cards.
- **Avatar stack** — Tag Friends / "Watched with" renders as overlapping circular avatars (negative margin overlap) ending in a dashed-outline "+" add slot.
- **Half-star rating** — a two-layer text trick: an outline row of five `★` in `line` color, with a second identical row in `primary`/`accent` color clipped to `width: calc(rating / 5 * 100%)` via `overflow: hidden`. Cheap to implement in any framework, no icon font or SVG-per-star needed, and half-star precision falls out for free from the percentage width.
- **Reduced motion respected** — no motion in this direction beyond default UI transitions; `prefers-reduced-motion: reduce` disables what little there is.

## Favourite background stamp

A favourited item gets a large postmark rendered *behind* the card's content, not a small badge competing for space: two concentric dashed/solid rings, centered just past the card's top-right corner so only the bottom-left arc bleeds into view (clipped by the card's own rounded edge), at low opacity (16% light / 22% dark). A chain of 5 small heart icons is strung along the ring gap, each rotated to point radially outward (tip away from the ring's center), tapering slightly larger toward the middle of the chain for a hand-set rather than mechanical feel. Non-favourited items simply omit the whole layer — see the mockup's dedicated with/without comparison section.

## Activity page structure

Two sections replace a single shelf/feed view:

- **All Activity** — a chronological stream of small stub cards (the same card used for the shelf), each carrying a small circular avatar badge overlapping the poster's bottom-left corner so attribution (yours or a friend's) reads at a glance without enlarging the card. 4 columns on desktop, 2 on mobile — same card, same CSS, just a different grid.
- **My Activity** — your own log entries only, shown one full "boarding pass" detail card at a time in a horizontally swipeable carousel (native scroll-snap, so touch/trackpad drag works with no custom drag handling needed). Desktop and mobile differ deliberately here: **mobile** centers the active card with a low-opacity/scaled-down peek of the next card on either side (classic gallery feel); **desktop** left-aligns instead (first card flush with the section heading) and only fades/shrinks a card as it approaches the *right* edge of the viewport — nothing dims just for being off-center, since on a wide screen "centered" reads as arbitrary rather than intentional.

## Iconography

Simple stroke icons (`stroke-width: 1.6-1.7`, `currentColor`, no fill except small solid accents like the bell dot) built from basic shapes — rects, lines, circles — rather than complex path data: film-strip, TV, game controller, book, bell, heart, comment, plus, grid (shelf), search, activity (pulse), profile. Kept deliberately plain so they read as "ticket iconography" rather than a generic icon-font pull.

## Navigation — 5-slot bar with a raised center "+"

Original mockups used a flat 4-item bar (Shelf / Search / Activity / Profile). Revised per direct feedback: **fast, low-friction review logging is the app's core loop** ("finish something → log it," from [03-features.md](03-features.md)'s mobile-first notes), so the add action needed to be the most prominent single element in the bar, not buried inside the Search tab.

- **New layout:** Shelf · Search · **[+]** · Activity · Profile — five slots, the center one a raised circular button (primary-red fill, paper-colored ring, drop shadow) that visually breaks the bar's top edge.
- **Behavior:** tapping "+" opens the fast-log flow directly — search/select an item and log a completed watch/play/read with rating, review, spoiler flag, favourite, and Tag Friends in one flow — rather than routing through the Search tab first.
- **Search tab still exists** for the separate job of browsing the catalog / adding something to your backlog (Planned status) without necessarily logging a completion yet.
- No text label under the "+" (icon-only, like Instagram/TikTok's center action) — its size and elevation carry the meaning; a label would fight the "this is the one to reach for" prominence it's meant to have.

This is a UX decision, not just a visual one — see updated note in [03-features.md](03-features.md).

## Translating to the real build

This mockup is plain HTML/CSS/JS for fast iteration, not Vuetify. When Phase 8 ([09-roadmap.md](09-roadmap.md)) builds the real frontend:

- Colors become a custom Vuetify theme (light + dark variants) using the token table above.
- The ticket-stub card, stamp-corner badge, dashed-divider card, avatar stack, and half-star rating are **bespoke components** — none of these are stock Vuetify components, they're custom components styled with Sass on top of Vuetify's base (cards/buttons/inputs) per [02-tech-stack.md](02-tech-stack.md).
- The raised center nav button is a custom-positioned button over/within Vuetify's `v-bottom-navigation`, not a stock variant of it.
