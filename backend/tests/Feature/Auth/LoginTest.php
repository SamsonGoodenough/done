<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonPath('data.email', $user->email);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
            'deactivated_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertUnprocessable()->assertJsonFragment([
            'email' => ['This account has been deactivated.'],
        ]);
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/logout');

        $response->assertNoContent();
        // Explicit guard: the auth:sanctum middleware on this route calls
        // auth()->shouldUse('sanctum'), which changes the default guard for
        // the rest of the request (and, in tests, beyond it), so the 'web'
        // guard is what login/logout actually operate on, so assert that.
        $this->assertGuest('web');
    }

    public function test_too_many_failed_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong']);

        $response->assertUnprocessable();
        $this->assertStringContainsString('Too many login attempts', $response->json('message'));

        RateLimiter::clear(mb_strtolower($user->email).'|127.0.0.1');
    }
}
