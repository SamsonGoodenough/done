<?php

namespace Database\Seeders;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed the Admin/Manager/Member roles and the permissions wired up so
     * far. Safe to run more than once (findOrCreate/syncPermissions).
     */
    public function run(): void
    {
        $roles = collect(UserRole::cases())
            ->mapWithKeys(fn (UserRole $role) => [$role->value => Role::findOrCreate($role->value)]);

        foreach (UserPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        $roles[UserRole::Admin->value]->syncPermissions(
            array_map(fn (UserPermission $permission) => $permission->value, UserPermission::cases())
        );

        $roles[UserRole::Manager->value]->syncPermissions([
            UserPermission::SendInvitations->value,
            UserPermission::CreateMediaItem->value,
        ]);

        $roles[UserRole::Member->value]->syncPermissions([]);
    }
}
