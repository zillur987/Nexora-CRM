<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Repositories\RoleRepository;
use App\Repositories\EloquentRoleRepository;
use App\Support\PermissionRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class RbacServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RoleRepository::class, EloquentRoleRepository::class);
    }

    public function boot(): void
    {
        // Super admin passes every gate check ($user->can(), @can, authorize()).
        Gate::before(fn ($user) => method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin() ? true : null);

        // Every registry entry becomes a gate ability: $user->can('products.edit').
        foreach (PermissionRegistry::names() as $name) {
            Gate::define($name, fn ($user) => method_exists($user, 'hasPermission') && $user->hasPermission($name));
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\RbacSyncCommand::class,
                \App\Console\Commands\RbacAssignCommand::class,
            ]);
        }
    }
}
