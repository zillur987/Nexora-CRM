<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rbac\PermissionSynchronizer;
use Illuminate\Console\Command;

class RbacSyncCommand extends Command
{
    protected $signature = 'rbac:sync {--prune : Delete permissions that are no longer in config/permissions.php}';
    protected $description = 'Sync permissions from config/permissions.php and create any missing default roles';

    public function handle(PermissionSynchronizer $sync): int
    {
        $roles = $sync->seedDefaultRoles();
        $result = $sync->syncPermissions((bool) $this->option('prune'));

        $this->info("Permissions: {$result['created']} created, {$result['updated']} updated, {$result['pruned']} pruned. Default roles created: {$roles}.");
        return self::SUCCESS;
    }
}
