<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Cache\LockTimeoutException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
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

        // Closes the gap between the exists() check above and the create
        // below: two near-simultaneous invocations could otherwise both
        // pass that check and both create an Admin. Cache::lock() rather
        // than a DB-specific advisory lock, so this works the same way on
        // Postgres and on the sqlite connection the test suite uses.
        $lock = Cache::lock('app:create-admin', 10);

        try {
            $admin = $lock->block(5, function () use ($name, $email, $password) {
                return DB::transaction(function () use ($name, $email, $password) {
                    if (User::query()->exists()) {
                        return null;
                    }

                    $admin = User::create([
                        'name' => $name,
                        'email' => $email,
                        'password' => $password,
                    ]);
                    $admin->assignRole(UserRole::Admin->value);

                    return $admin;
                });
            });
        } catch (LockTimeoutException) {
            $this->error('Another app:create-admin run is already in progress. Try again shortly.');

            return self::FAILURE;
        } catch (RoleDoesNotExist) {
            $this->error("The 'admin' role doesn't exist yet. Run "
                .'`php artisan db:seed --class=RolePermissionSeeder` first, then retry this command.');

            return self::FAILURE;
        }

        if ($admin === null) {
            $this->error('Refusing to run: another process already bootstrapped the first admin while this one was running.');

            return self::FAILURE;
        }

        $this->info("Admin account created: {$admin->email}");

        return self::SUCCESS;
    }
}
