# Features (v1)

## Media types

Four types, each with common fields plus type-specific metadata pulled from an external source (see [07-external-integrations.md](07-external-integrations.md)):

- **Movies** — title, release date, runtime, director, poster, overview
- **TV Shows** — title, first air date, status (ongoing/ended), seasons/episode counts, poster, overview
- **Games** — title, release date, platforms, developer/publisher, cover art, overview
- **Books** — title, author(s), publish date, page count, cover, overview

## Adding an item to your library

1. User searches by title. The search hits the relevant external API (TMDB / games API / books API) and shows matching results with poster + year for disambiguation.
2. User picks a result → the item is created in the shared `media_items` catalog if it doesn't already exist locally (dedup by external source + external ID), then added to the user's shelf.
3. **Manual entry fallback:** if nothing matches (obscure/regional title, self-published book, etc.), a **Manager or Admin** can create a media item by hand — title, type, and whatever metadata they have, flagged as manually-added rather than API-sourced. Members cannot create manual entries; they can request one out-of-band or wait until a Manager adds it, then log it themselves.

## Shelf status (backlog/watchlist)

Every item on a user's shelf has a status, independent of any diary entries:

- **Planned** — want to watch/play/read
- **In progress** — currently watching/playing/reading
- **Completed** — finished at least once
- **Dropped** — started, not finishing
- **On hold** — paused partway through

Status is set/changed manually by the user (moving something from Planned → In progress → Completed is a normal flow, but the app doesn't force that order — e.g. logging a completed movie you saw last week creates the shelf entry as Completed directly).

## Episode & season logging (TV Shows)

TV shows can be logged at three levels of granularity, not just whole-show:

- Season/episode data (numbers, titles, air dates) is pulled from TMDB when a show is added to the catalog and stored locally (see [04-data-model.md](04-data-model.md) `episodes` table) — this is what populates the season/episode picker when creating a log entry.
- A log entry can target the **whole show**, a **single season**, or a **single episode** — same fields either way (date, rating, review, spoiler flag, favourite, Tag Friends, rewatch). Rating and review are optional at every level, so logging an episode with no rating is how a user notes "I watched this" without a separate progress-tracking system.
- There's no whole-show completion checklist or "watched X of Y" progress bar in v1 — a show's page just shows its diary history (whole-show, season, and episode entries together, most recent first), and moving the shelf status to Completed stays a manual action independent of how many episodes or seasons have been individually logged.
- Movies, games, and books don't get this sub-item logging — season/episode granularity is TV-specific.

## Diary-style logging

Every time a user finishes (or re-finishes) something, they create a **log entry** — not just a status change. Log entry fields:

- **Date** — when it was watched/played/read (or finished, for multi-day items like books/games)
- **Rating** — 0 to 5 stars, in 0.5 increments (11 possible values: 0, 0.5, 1, ... 5). A rating is optional — a user can log without rating.
- **Review** — free text, optional
- **Contains spoilers** — boolean flag on the review. If set, the review body is hidden behind a "Show spoilers" tap/click in any UI where other users can see it; the user's own view always shows it in full.
- **Favourite** — boolean. Marking favourite applies to the *item* (shows up in the user's favourites list) but is set from the log entry UI at the point of logging, and editable afterward from the item or the entry.
- **Rewatch/replay/reread** — a log entry is automatically flagged as a rewatch if the user already has a prior *completed* log entry for that item; this can be overridden manually (e.g. importing history where the first watch wasn't logged).

A single item can have many log entries per user over time (first watch, a rewatch two years later, etc.) — each with its own date, rating, and review. The item's page shows the full history; the user's overall "current" rating for display purposes is the most recent entry's rating.

### Tag Friends

Any log entry can be tagged with other members who shared the experience — the label adapts to the media type: **"Watched with"** for movies/TV, **"Played with"** for games, **"Read with"** for books. UI is a small row of tagged members' avatars (pfp icons) plus a trailing "+" button that opens a picker to add more.

- Tagged members are drawn from the app's existing members only (not free-text guest names) — you can only tag someone who's actually been invited to the instance.
- Tagging is one-way and needs no confirmation from the tagged member (this is a small trusted group, not a public tag-approval system).
- A log entry can have any number of tagged members, including none.
- Tagging is informational, not an access grant — it doesn't change who can see the entry; normal friends/visibility rules (see "Friends & profile visibility" below) still apply.
- Being tagged can optionally surface on the tagged member's own profile/activity (e.g. "seen with Sam"), see [10-open-questions.md](10-open-questions.md) for whether that's in v1 or a fast-follow.

## Reviews & spoilers

- Reviews are plain text (v1) — no rich formatting needed initially, revisit if requested.
- Spoiler flag is per log entry (per review), not per item — the same movie might have one spoiler-free short reaction and one detailed spoiler-heavy rewatch review from the same user.
- Spoiler-flagged reviews are blurred/collapsed by default anywhere they're visible to someone other than the author, with an explicit reveal action.

## Favourites

- A lightweight, quick way to flag standout items — separate from rating (something can be 5 stars without being a "favourite," e.g. a comfort rewatch might be favourited at 3 stars).
- A "Favourites" view per user, filterable by media type.

## Friends & profile visibility

The app is still invite-only with no public/anonymous access at all — but *within* the group, visibility is now controlled by a friends graph rather than being all-members-see-all-members:

- **Adding a friend** is a request/accept flow: one member sends a friend request to another, who accepts or declines. Once accepted, the two are mutual friends.
- **Profile visibility setting** — every user has a profile visibility of **public** (default) or **private**, changeable in their own settings:
  - **Public profile:** any member can view the profile's shelf, diary entries, and reviews without being friends.
  - **Private profile:** still shows up in member search/discovery (name, avatar, and that they exist), but their shelf/diary/reviews are only visible to accepted friends. Non-friends see a "this profile is private — add as friend to see more" state.
- **Discoverability:** any member can search for and view any other member's profile page (subject to the public/private rule above) — search itself isn't gated by friendship, only the content behind a private profile is.
- **Avatars:** users can upload their own profile picture; anyone who hasn't gets an auto-generated placeholder (initials/identicon) so avatars are never blank in Tag Friends, comments, or friend lists.
- **Main activity feed logic:** shows recent reviews from the viewer's friends, most recent first. If the viewer has no friends yet, it falls back to showing the viewer's own most recent reviews instead of an empty feed (see [10-open-questions.md](10-open-questions.md) for confirming this is the intended fallback vs. a global discovery feed).
- **Future idea (not v1):** a "you and X have N reviews in common" match suggestion to nudge friend requests, based on overlapping logged items — tracked as a stretch goal in [09-roadmap.md](09-roadmap.md).

## Comments

Reviews (log entries with a review body) can have threaded comments, Reddit-style:

- Any member who can see a review (per the friends/visibility rules above) can comment on it.
- Comments can reply to other comments, nested arbitrarily deep.
- A comment on a spoiler-flagged review is itself hidden behind the same spoiler reveal as the review, since a comment thread will likely discuss the spoiler.
- Deleting a comment that has replies keeps the thread intact by soft-deleting it (shown as "[deleted]") rather than removing it and orphaning its children — see [04-data-model.md](04-data-model.md).

## Reactions

A fixed, small set of emoji reactions can be left on a review (not on individual comments in v1) — e.g. 👍 ❤️ 😂 😮 😢 🔥. Shown as small counts under the review, similar to Slack/Facebook reactions. A user can leave more than one distinct reaction on the same review (e.g. both ❤️ and 😮) but not the same emoji twice.

## Notifications

In-app only for v1 (a bell icon with a badge count, no email or push) — see [04-data-model.md](04-data-model.md) `notifications` table. Events that generate a notification:

- Someone tags you (Tag Friends) in a log entry
- Someone sends you a friend request, and when they accept yours
- Someone comments on your review, or replies to your comment
- Someone reacts to your review

Notifications link to the relevant review/comment/profile and can be marked read individually or all-at-once.

## Search & filtering

- Search across the local catalog (already-added items) by title/type.
- Filter a shelf or the activity feed by media type, status, favourite, or rating range.

## Mobile-first UI notes

- Primary interaction is expected to be: finish something → open app → log it. This flow should be as few taps as possible on a phone — prioritized enough that it gets the single most prominent slot in the bottom nav (below).
- Star rating control needs a touch-friendly half-star interaction (not just hover-based, which doesn't exist on touch).
- **Bottom navigation is 5 slots: Shelf, Search, a raised center "+", Activity, Profile.** The "+" is the fast-log entry point — search/select an item and log a completed watch/play/read (rating, review, spoiler flag, favourite, Tag Friends) in one flow, without routing through the Search tab first. Search remains a separate destination for browsing the catalog / adding something to your backlog without necessarily logging a completion. Rather than a desktop-style sidebar, per mobile-first requirement. A notifications bell (with unread badge) lives in the top app bar rather than taking a bottom-nav slot, since it's a secondary, occasional action. See [11-design-system.md](11-design-system.md) for the visual treatment.
- **Installable as a PWA** — add-to-homescreen, runs full-screen without browser chrome, static assets cached by a service worker for fast repeat loads. No offline data entry in v1 (logging still requires a connection); see [10-open-questions.md](10-open-questions.md) if that's wanted later.
- **Light + dark theme**, both supported from v1 rather than retrofitted later.
