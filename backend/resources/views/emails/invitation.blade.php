<!doctype html>
<html>
<body style="font-family: -apple-system, Segoe UI, sans-serif; color: #1F2A44; background: #F7F3E7; padding: 2rem;">
    <table role="presentation" width="100%" style="max-width: 480px; margin: 0 auto; background: #EDE6CF; border-radius: 8px; padding: 2rem;">
        <tr>
            <td>
                <h1 style="font-size: 1.25rem; margin: 0 0 1rem;">You're invited to Done</h1>
                <p style="margin: 0 0 1rem;">
                    {{ $invitation->inviter->name }} has invited you to join Done, a shared tracker for movies, TV shows, games, and books, as a <strong>{{ ucfirst($invitation->role->value) }}</strong>.
                </p>
                <p style="margin: 0 0 1.5rem;">
                    <a href="{{ $inviteUrl }}" style="display: inline-block; background: #B23A2E; color: #F7F3E7; text-decoration: none; padding: 0.6rem 1.2rem; border-radius: 4px;">Accept invitation</a>
                </p>
                <p style="margin: 0; font-size: 0.85rem; opacity: 0.75;">
                    This link expires on {{ $invitation->expires_at->toFormattedDateString() }}. If you weren't expecting this, you can ignore this email.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
