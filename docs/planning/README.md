# Done — Planning Docs

Index of the planning documents for **Done**, an invite-only, self-hosted tracker for movies, TV shows, games, and books.

| Doc | Contents |
|---|---|
| [01-overview.md](01-overview.md) | Problem statement, goals, non-goals, target users |
| [02-tech-stack.md](02-tech-stack.md) | Frontend/backend stack, key libraries, why each was chosen |
| [03-features.md](03-features.md) | Full feature spec for v1 |
| [04-data-model.md](04-data-model.md) | Entities, relationships, schema sketch |
| [05-database-choice.md](05-database-choice.md) | PostgreSQL vs MySQL vs SQLite, with pros/cons, and the recommendation |
| [06-roles-permissions.md](06-roles-permissions.md) | Admin / Manager / Member roles, permission matrix, invite flow |
| [07-external-integrations.md](07-external-integrations.md) | TMDB, games API, books API — choices and caching strategy |
| [08-architecture-deployment.md](08-architecture-deployment.md) | Docker Compose layout, nginx, auth mechanism, backups |
| [09-roadmap.md](09-roadmap.md) | Phased build plan |
| [10-open-questions.md](10-open-questions.md) | Decisions still open, assumptions made so far |
| [11-design-system.md](11-design-system.md) | Chosen visual style ("Stub & Stamp" → "Passport & Postmark"): colors, type, layout motifs, iconography, nav structure. Live reference mockup at [`mockups/passport-and-postmark.html`](mockups/passport-and-postmark.html). |

## Decisions locked in during planning

- **Data model:** private-per-user library with social visibility (not one shared collective list).
- **Media types (v1):** Movies, TV Shows, Games, Books.
- **Logging model:** diary-style — every watch/play/read is its own dated log entry, so rewatches/replays/rereads are just additional entries on the same item.
- **Episode progress:** no separate watched-checkbox system. TV shows are logged the same way as any other item, just optionally scoped to a season or a single episode (`log_entries.episode_id` / `season_number`) — rating/review stay optional at every level. Season/episode metadata is still synced from TMDB to populate the picker. See [03-features.md](03-features.md) and [04-data-model.md](04-data-model.md).
- **Tag Friends:** any log entry can tag other members who shared the experience — label adapts per type ("Watched with" / "Played with" / "Read with"), avatar row + "+" picker, members-only, no free-text guests.
- **Friends & visibility:** real friend request/accept graph. Profiles are public by default (viewable by any member) or private (content gated to accepted friends; still discoverable via search). Activity feed shows friends' reviews, falling back to your own if you have no friends yet.
- **Comments & reactions:** threaded (Reddit-style) comments on reviews, plus a fixed small set of emoji reactions on reviews.
- **Notifications:** in-app bell/badge only for v1 (no email/push) — tags, friend requests/accepts, comments, replies, reactions.
- **Avatars:** user-uploaded photo with an auto-generated (initials/identicon) fallback, stored on a local Docker volume.
- **User removal:** soft-deactivate only (blocks login) — historical content (reviews, comments, reactions, tags) is always retained, never deleted or reassigned.
- **PWA:** installable (add-to-homescreen, cached static assets), no offline data entry in v1.
- **Theming:** light + dark mode both supported from v1.
- **Visual style:** "Stub & Stamp," refined into the "Passport & Postmark" execution — a ticket-stub/archive aesthetic (stamp red + gold on near-white kraft paper, monospace ticket-printer type) with postmark ratings, a favourite heart-chain background stamp, and an "All Activity" / "My Activity" swipeable-carousel page structure. See [11-design-system.md](11-design-system.md) and the saved mockup at `docs/planning/mockups/passport-and-postmark.html`.
- **Theming architecture:** theme colors live in one canonical file that feeds both Vuetify's theme config and the custom/bespoke components (via Vuetify's runtime CSS custom properties) — one place to edit, no hand-duplicated color values. See "Theming architecture" in [02-tech-stack.md](02-tech-stack.md).
- **Bottom nav:** 5 slots — Shelf, Search, a raised center "+" (fast review logging), Activity, Profile. The "+" is deliberately the most prominent action in the bar.
- **Backlog:** included in v1 (planned / in-progress / completed / dropped / on-hold).
- **Metadata:** pulled from external APIs from v1 (not manual-only), with manual entry as a fallback.
- **Roles:** Admin, Manager, Member. Managers can manually add media that isn't found in the external databases.
- **Access:** invite-only. Admins/Managers send a tokenized email invite link — no public signup.
- **Deployment:** self-hosted, single VPS, Docker Compose, small scale (friends/family, well under 50 users).
- **Timestamps & soft deletes:** every table gets `created_at`/`updated_at` and soft deletes (`deleted_at`) by default. Exceptions: `users` (already has `deactivated_at` for the same purpose), the `log_entry_participants` pivot, and `reactions` (both plain create/delete, nothing to update or soft-delete). See [04-data-model.md](04-data-model.md).
