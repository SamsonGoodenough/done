<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_admin_when_no_users_exist(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->artisan('app:create-admin', [
            '--name' => 'First Admin',
            '--email' => 'admin@example.com',
            '--password' => 'password',
        ])->assertSuccessful();

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue($admin->hasRole(UserRole::Admin->value));
    }

    public function test_refuses_to_run_when_a_user_already_exists(): void
    {
        $this->seed(RolePermissionSeeder::class);
        User::factory()->create();

        $this->artisan('app:create-admin', [
            '--name' => 'Second Admin',
            '--email' => 'second@example.com',
            '--password' => 'password',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
    }

    public function test_fails_cleanly_and_leaves_no_orphaned_user_when_roles_are_not_seeded(): void
    {
        // Deliberately skip seeding RolePermissionSeeder, matching an operator
        // who ran `migrate` but not the seed step: assignRole() should throw
        // RoleDoesNotExist, and the transaction should roll back the User
        // row created just before it rather than leaving a roleless admin
        // behind that the exists() guard would then hide forever.
        $this->artisan('app:create-admin', [
            '--name' => 'First Admin',
            '--email' => 'admin@example.com',
            '--password' => 'password',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'admin@example.com']);
    }
}
