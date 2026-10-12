<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use Illuminate\Console\Command;

class RbacAssignCommand extends Command
{
    protected $signature = 'rbac:assign {email : User e-mail} {role=super-admin : Role slug}';
    protected $description = 'Give a user a role (use this to create your first Super Admin)';

    public function handle(): int
    {
        $user = config('auth.providers.users.model')::query()->where('email', $this->argument('email'))->first();
        $role = Role::query()->where('slug', $this->argument('role'))->first();

        if (! $user) return $this->fail('No user with that e-mail.');
        if (! $role) return $this->fail('No role with that slug. Run: php artisan rbac:sync');

        $user->roles()->syncWithoutDetaching([$role->id]);
        $this->info("{$user->email} is now {$role->name}.");
        return self::SUCCESS;
    }
}
