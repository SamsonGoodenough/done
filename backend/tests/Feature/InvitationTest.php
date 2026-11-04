<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    private function manager(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Manager->value);

        return $user;
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Member->value);

        return $user;
    }

    public function test_admin_can_send_an_invitation(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin())->postJson('/api/invitations', [
            'email' => 'invitee@example.com',
            'role' => 'manager',
        ]);

        $response->assertCreated()->assertJsonPath('data.email', 'invitee@example.com');
        $this->assertDatabaseHas('invitations', ['email' => 'invitee@example.com']);
        Mail::assertQueued(InvitationMail::class);
    }

    public function test_manager_can_invite_a_member(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->manager())->postJson('/api/invitations', [
            'email' => 'invitee@example.com',
            'role' => 'member',
        ]);

        $response->assertCreated();
    }

    public function test_manager_cannot_invite_a_manager_or_admin(): void
    {
        $response = $this->actingAs($this->manager())->postJson('/api/invitations', [
            'email' => 'invitee@example.com',
            'role' => 'admin',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertDatabaseMissing('invitations', ['email' => 'invitee@example.com']);
    }

    public function test_member_cannot_send_invitations(): void
    {
        $response = $this->actingAs($this->member())->postJson('/api/invitations', [
            'email' => 'invitee@example.com',
            'role' => 'member',
        ]);

        $response->assertForbidden();
    }

    public function test_cannot_invite_an_email_that_already_belongs_to_a_member(): void
    {
        $existing = User::factory()->create();

        $response = $this->actingAs($this->admin())->postJson('/api/invitations', [
            'email' => $existing->email,
            'role' => 'member',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_cannot_invite_an_email_with_an_existing_pending_invitation(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/invitations', [
            'email' => 'invitee@example.com',
            'role' => 'member',
        ])->assertCreated();

        $response = $this->actingAs($admin)->postJson('/api/invitations', [
            'email' => 'invitee@example.com',
            'role' => 'member',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_public_can_view_a_pending_invitation_by_token(): void
    {
        [$invitation, $rawToken] = $this->createInvitation();

        $response = $this->getJson("/api/invitations/{$rawToken}");

        $response->assertOk()->assertJsonPath('data.email', $invitation->email);
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->getJson('/api/invitations/not-a-real-token')->assertNotFound();
    }

    public function test_expired_invitation_returns_410(): void
    {
        [, $rawToken] = $this->createInvitation(expiresAt: now()->subDay());

        $this->getJson("/api/invitations/{$rawToken}")->assertStatus(410);
    }

    public function test_already_accepted_invitation_returns_410(): void
    {
        [, $rawToken] = $this->createInvitation(acceptedAt: now());

        $this->getJson("/api/invitations/{$rawToken}")->assertStatus(410);
    }

    public function test_accepting_an_invitation_creates_a_user_with_the_assigned_role_and_logs_in(): void
    {
        [$invitation, $rawToken] = $this->createInvitation(role: UserRole::Manager);

        $response = $this->postJson("/api/invitations/{$rawToken}/accept", [
            'name' => 'New Person',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertCreated()->assertJsonPath('data.roles', ['manager']);

        $user = User::where('email', $invitation->email)->firstOrFail();
        $this->assertTrue($user->hasRole(UserRole::Manager->value));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_cannot_accept_an_expired_invitation(): void
    {
        [, $rawToken] = $this->createInvitation(expiresAt: now()->subDay());

        $this->postJson("/api/invitations/{$rawToken}/accept", [
            'name' => 'New Person',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(410);

        $this->assertGuest();
    }

    public function test_accept_rolls_back_cleanly_if_the_invited_role_does_not_exist(): void
    {
        [$invitation, $rawToken] = $this->createInvitation(role: UserRole::Manager);

        // Simulate a corrupted/incomplete role setup after the invitation
        // was already sent: assignRole('manager') should throw
        // RoleDoesNotExist inside accept()'s transaction, and the User row
        // created just before it should roll back rather than leaving an
        // orphaned, roleless user with the email now permanently consumed.
        \Spatie\Permission\Models\Role::where('name', UserRole::Manager->value)->delete();

        $this->postJson("/api/invitations/{$rawToken}/accept", [
            'name' => 'New Person',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(500);

        $this->assertDatabaseMissing('users', ['email' => $invitation->email]);
        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertGuest();
    }

    public function test_sender_can_revoke_their_own_invitation(): void
    {
        $manager = $this->manager();
        [$invitation] = $this->createInvitation(invitedBy: $manager);

        $response = $this->actingAs($manager)->deleteJson("/api/invitations/{$invitation->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted($invitation);
    }

    public function test_admin_can_revoke_anyones_invitation(): void
    {
        [$invitation] = $this->createInvitation(invitedBy: $this->manager());

        $response = $this->actingAs($this->admin())->deleteJson("/api/invitations/{$invitation->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted($invitation);
    }

    public function test_unrelated_user_cannot_revoke_an_invitation(): void
    {
        [$invitation] = $this->createInvitation(invitedBy: $this->manager());

        $response = $this->actingAs($this->manager())->deleteJson("/api/invitations/{$invitation->id}");

        $response->assertForbidden();
    }

    public function test_resend_regenerates_the_token_and_expiry(): void
    {
        Mail::fake();
        $admin = $this->admin();
        [$invitation, $originalRawToken] = $this->createInvitation(invitedBy: $admin, expiresAt: now()->addDay());

        $response = $this->actingAs($admin)->postJson("/api/invitations/{$invitation->id}/resend");

        $response->assertOk();
        $invitation->refresh();
        $this->assertNotEquals(Invitation::hashToken($originalRawToken), $invitation->token);
        $this->assertTrue($invitation->expires_at->isAfter(now()->addDays(6)));
        Mail::assertQueued(InvitationMail::class);
    }

    public function test_can_resend_an_expired_but_unaccepted_invitation(): void
    {
        Mail::fake();
        $admin = $this->admin();
        [$invitation, $originalRawToken] = $this->createInvitation(invitedBy: $admin, expiresAt: now()->subDay());

        $response = $this->actingAs($admin)->postJson("/api/invitations/{$invitation->id}/resend");

        $response->assertOk();
        $invitation->refresh();
        $this->assertNotEquals(Invitation::hashToken($originalRawToken), $invitation->token);
        $this->assertTrue($invitation->expires_at->isFuture());
        Mail::assertQueued(InvitationMail::class);
    }

    public function test_cannot_resend_an_already_accepted_invitation(): void
    {
        $admin = $this->admin();
        [$invitation] = $this->createInvitation(invitedBy: $admin, acceptedAt: now());

        $response = $this->actingAs($admin)->postJson("/api/invitations/{$invitation->id}/resend");

        $response->assertStatus(410);
    }

    /**
     * @return array{0: Invitation, 1: string} the invitation and its raw (pre-hash) token
     */
    private function createInvitation(
        ?User $invitedBy = null,
        UserRole $role = UserRole::Member,
        ?\Illuminate\Support\Carbon $expiresAt = null,
        ?\Illuminate\Support\Carbon $acceptedAt = null,
    ): array {
        $rawToken = Invitation::generateRawToken();

        $invitation = Invitation::create([
            'email' => 'invitee@example.com',
            'token' => Invitation::hashToken($rawToken),
            'role' => $role,
            'invited_by' => ($invitedBy ?? $this->admin())->id,
            'expires_at' => $expiresAt ?? now()->addDays(7),
            'accepted_at' => $acceptedAt,
        ]);

        return [$invitation, $rawToken];
    }
}
