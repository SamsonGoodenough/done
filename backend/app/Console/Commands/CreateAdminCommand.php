<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin {--name=} {--email=} {--password=}';

    protected $description = 'Bootstrap the first Admin account (only works when no users exist yet)';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->error('Refusing to run: this instance already has at least one user. '
                .'There is no public signup; invite further users from the app once an Admin is logged in.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?? $this->ask('Admin name');
        $email = $this->option('email') ?? $this->ask('Admin email');
        $password = $this->option('password') ?? $this->secret('Admin password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email'],
                'password' => ['required', Password::defaults()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        try {
            $admin = DB::transaction(function () use ($name, $email, $password) {
                $admin = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                ]);
                $admin->assignRole(UserRole::Admin->value);

                return $admin;
            });
        } catch (RoleDoesNotExist) {
            $this->error("The 'admin' role doesn't exist yet. Run "
                .'`php artisan db:seed --class=RolePermissionSeeder` first, then retry this command.');

            return self::FAILURE;
        }

        $this->info("Admin account created: {$admin->email}");

        return self::SUCCESS;
    }
}
