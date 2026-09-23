# Open Questions & Assumptions

Things assumed during planning that are worth explicitly confirming (or revisiting) before/during Phase 1, since they weren't asked directly.

## Resolved

- ~~Per-user visibility control~~ — resolved: profiles are public by default with a per-user private setting, and visibility is gated by an actual friends graph (request/accept), not an instance-wide toggle. See [03-features.md](03-features.md) "Friends & profile visibility."
- ~~TV partial-completion granularity~~ — resolved: replaced entirely by logging granularity rather than a separate progress checklist. A log entry can target the whole show, a single season, or a single episode (`log_entries.episode_id` / `season_number`), with rating/review optional at every level — there's no separate per-episode watched-checkbox table. See [03-features.md](03-features.md) "Episode & season logging" and [04-data-model.md](04-data-model.md).
- ~~Auto-complete on all episodes watched~~ — moot: without a full watched/unwatched checklist there's no "last episode watched" event to trigger off. Moving a show's shelf status to Completed stays a manual action regardless of how many episodes/seasons have been logged.
- ~~User removal~~ — resolved: deactivation is soft (`users.deactivated_at`), blocks login only, all historical content stays intact and attributed as normal. See [06-roles-permissions.md](06-roles-permissions.md).
- ~~Avatar source~~ — resolved: user-uploaded photo with an auto-generated (initials/identicon) fallback, stored on a local volume. See [08-architecture-deployment.md](08-architecture-deployment.md).
- ~~PWA/offline~~ — resolved: installable PWA (add-to-homescreen, cached static assets), no offline data entry in v1.
- ~~Dark mode~~ — resolved: light + dark both in v1.

## Open questions

1. **Review editing/deletion window.** Can a user edit or delete a log entry/review after posting, indefinitely? Assumed yes with no restriction for v1 (it's a personal tracker, not a public review site), but now that reviews can carry comments/reactions from others, worth confirming there's no need for an edit history or a "this review was edited" indicator.
2. **Activity feed no-friends fallback — confirm the intended behavior.** Current read: if you have zero friends, your feed shows *your own* most recent reviews rather than being empty. An alternative reading is a global/discovery feed showing recent reviews from *any* member (to help a new user find people to friend). Worth confirming which was meant before Phase 5.
3. **Comment/reaction moderation scope.** A review's author can delete/hide comments on their own review; only an Admin can moderate instance-wide. Is that enough, or do Managers also need moderation rights (e.g. for a review under a manually-added media item they maintain)?
4. **Notification volume.** Six notification types are planned (tagged, friend request/accept, comment, reply, reaction). Reactions in particular can be noisy in an active group — worth a per-type notification preference toggle, or is "all six, always on" fine for v1's scale?
5. **Invite email sending.** Self-hosted mail delivery needs either a transactional email provider (e.g. Postmark, SES, Mailgun — reliable, avoids spam-folder issues) or the VPS's own SMTP (simpler but more likely to be flagged as spam). Not decided yet — affects deployment config and possibly a small recurring cost.
6. **Domain/hosting for the VPS.** Not discussed — needed before deployment (TLS, `SESSION_DOMAIN` for Sanctum, etc.).
7. **Data import.** Is there existing data to migrate in (e.g. exported Letterboxd/Goodreads/Backloggd history)? Not in v1 scope per the roadmap, but worth flagging early if it's wanted, since import format support is easier to design for up front than bolt on later.
8. **Does being tagged (Tag Friends) surface on the tagged member's own profile/activity?** E.g. does Jane's activity feed/profile show "Sam logged Movie X, with Jane" even though Jane didn't log it herself? Assumed **no** for v1 — a tag shows up only on the tagging member's own log entry (visible to whoever could already see that entry) — since surfacing it on the tagged member's profile adds a second display path and a "is this really my activity" question. Straightforward fast-follow if wanted.
9. **Offline logging.** The PWA caches static assets for fast loads but doesn't support creating log entries while offline in v1 (per the PWA decision). Worth a fast-follow if the group frequently logs things in places with poor signal (e.g. a movie theater)?
10. **Avatar upload constraints.** File size/type limits and whether uploads need any resizing/moderation aren't specified yet — low-stakes for a small trusted group, but worth a sane default (e.g. max 2MB, JPEG/PNG/WebP, resized server-side to a fixed square) before Phase 8 builds the upload UI.
11. **Light/dark mode vs. the "Cinematic Cut" design direction.** The "Dark mode" item above was resolved as light + dark both in v1, and that's what [02-tech-stack.md](02-tech-stack.md)'s theming section still assumes. The current design direction ([11-design-system.md](11-design-system.md)) is dark-first and cinematic, with its `--bg`/`--paper` split reading as two surface types within one screen (dark hero vs. paper reading section) rather than a user-togglable theme. Does the dark surface *become* the app's dark theme (with a light theme designed separately from the same tokens), or does going dark-first mean revisiting the light+dark commitment? Needs deciding before Phase 8 wires up Vuetify's theme config.
12. **Font hosting.** [11-design-system.md](11-design-system.md) calls for three Google Fonts (Bodoni Moda, Archivo, IBM Plex Mono), loaded from Google's CDN in the prototype. Self-hosting fits the app's self-hosted/offline-PWA posture better (no third-party request at load time, still works if Google Fonts is unreachable) but means bundling the font files. Not decided.
13. **Activity page structure post-Cinematic-Cut.** Passport & Postmark split Activity into "All Activity" (everyone's feed) and "My Activity" (your own entries, swipeable carousel). The Cinematic Cut prototype only shows a single chronological feed. Does the All/My split still apply on top of the new visual treatment, or was it dropped in favour of one feed? Needs confirming before Phase 8 builds the Activity screen.
14. **Rating entry vs. display.** Passport & Postmark's half-star row (five stars, percentage-clipped fill) was the rating *entry* control; Cinematic Cut only shows the postmark badge as a rating *display*. Is the half-star widget still how a rating gets entered in the add/rate flow (which neither prototype has actually designed yet), or does Cinematic Cut imply a different entry control?
15. **Favourite treatment in Cinematic Cut.** Passport & Postmark's favourite heart-chain background stamp doesn't appear on any Cinematic Cut shelf-card artboard. Confirm whether it still applies to the redesigned cards or needs a Cinematic Cut-native treatment.

## Assumptions made (flag if wrong)

- Single instance for one group — not a multi-tenant product serving several separate groups.
- No rich text/markdown in reviews or comments for v1 — plain text only.
- Posters/cover images are hotlinked from external APIs, not downloaded and self-hosted.
- First Admin is bootstrapped via a CLI command / gated seeder, not a UI flow (since there's no signup page to expose it through).
- Member search/discovery is unrestricted (any member can search for any other member by name) even when the result is a private profile — only the private profile's *content* is gated, not whether they can be found at all.
- Reactions are on reviews only in v1, not on individual comments within a thread.
