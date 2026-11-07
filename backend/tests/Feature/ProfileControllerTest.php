<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_visibility_requires_authentication(): void
    {
        $this->patchJson('/api/profile/visibility', ['profile_visibility' => 'private'])->assertUnauthorized();
    }

    public function test_user_can_switch_their_own_profile_to_private(): void
    {
        $user = User::factory()->create(['profile_visibility' => 'public']);

        $this->actingAs($user)
            ->patchJson('/api/profile/visibility', ['profile_visibility' => 'private'])
            ->assertOk()->assertJsonPath('data.profile_visibility', 'private');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'profile_visibility' => 'private']);
    }

    public function test_rejects_an_invalid_visibility_value(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/profile/visibility', ['profile_visibility' => 'friends-only'])
            ->assertUnprocessable()->assertJsonValidationErrors('profile_visibility');
    }
}
