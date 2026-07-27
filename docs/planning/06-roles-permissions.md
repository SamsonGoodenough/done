# Roles & Permissions

## Roles

Three roles, implemented via `spatie/laravel-permission`:

- **Admin** — full control of the instance
- **Manager** — trusted user who can extend the shared catalog
- **Member** — standard user, the default role for most invitees

## Permission matrix

| Capability | Admin | Manager | Member |
|---|:---:|:---:|:---:|
| Log items, rate, review, favourite (own data) | ✅ | ✅ | ✅ |
| Maintain own shelf/backlog, episode progress | ✅ | ✅ | ✅ |
| Send/accept friend requests, comment, react, tag friends | ✅ | ✅ | ✅ |
| View another member's profile per friends/visibility rules | ✅ | ✅ | ✅ |
| Search & add existing catalog items from external APIs | ✅ | ✅ | ✅ |
| **Manually add a media item not found via API** | ✅ | ✅ | ❌ |
| Edit shared catalog metadata (fix a wrong title/poster) | ✅ | ✅ | ❌ |
| Send invitations | ✅ | ✅ | ❌ |
| Change a user's role | ✅ | ❌ | ❌ |
| Deactivate a user (soft — blocks login, keeps their content) | ✅ | ❌ | ❌ |
| App-wide settings (site name, external API keys, etc.) | ✅ | ❌ | ❌ |
| Delete another user's log entry/review/comment (moderation) | ✅ | ❌ | ❌ |
| Delete/hide a comment on your **own** review | ✅ | ✅ | ✅ |

Notes:
- Friend requests, comments, reactions, and Tag Friends are open to every role equally — these aren't permission-gated, they're gated by the friends/visibility rules in [03-features.md](03-features.md) (public vs. private profile, friends vs. non-friends), which apply the same regardless of role.
- Any member can moderate comments on their *own* review (delete/hide a reply they don't want on their content); only an Admin can moderate *any* member's content instance-wide.
- Managers get catalog-editing rights (add/fix media) but not user/instance management — the split matches the planning answer that Managers exist specifically to handle media the external APIs miss.
- Only Admins can change roles or deactivate users, to avoid a Manager granting themselves/others elevated access.
- Deactivating a user is soft (`users.deactivated_at`) — it blocks login but never deletes or reassigns their historical content, so other members' comment threads and Tag Friends history stay intact.

## Invitation flow

Access is invite-only — there is no public registration route.

1. An Admin or Manager enters an email address and picks a role for the invitee (Member by default; Admin/Manager roles restricted to what the inviter is allowed to grant — a Manager can only invite Members, only an Admin can invite another Admin or Manager).
2. The app creates an `invitations` row with a random token (stored hashed, e.g. SHA-256, so the raw token only ever exists in the emailed link) and an expiry (e.g. 7 days).
3. An email is sent (queued job, via Laravel Mail/SMTP) with a link containing the raw token: `https://done.example.com/invite/{token}`.
4. The invitee opens the link, sets their name and password, and their account is created with the assigned role. `accepted_at` is stamped; the token can't be reused.
5. Expired or already-accepted invitations show a clear error rather than silently failing.

Resending/revoking an invitation (before it's accepted) is available to whoever sent it, or any Admin.

## First Admin (bootstrapping)

Since there's no public signup, the very first user (the instance owner) needs to be created out-of-band — e.g. a Laravel Artisan command (`php artisan app:create-admin`) run once during initial deployment, or a seeded admin account whose password is changed on first login. This is a deployment-time step, detailed in [08-architecture-deployment.md](08-architecture-deployment.md).
