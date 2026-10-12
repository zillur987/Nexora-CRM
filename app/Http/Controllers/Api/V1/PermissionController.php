<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permission;

class PermissionController extends Controller
{
    /**
     * The permission matrix: modules in config order, each with its permissions.
     * Shape: { data: [{ module, label, icon, permissions: [{ id, name, action, label }] }] }
     */
    public function index()
    {
        $byModule = Permission::query()->get(['id', 'name', 'module', 'action', 'label'])->groupBy('module');
        $actionOrder = array_keys(config('permissions.actions', []));

        $groups = collect(config('permissions.modules', []))
            ->map(function (array $def, string $module) use ($byModule, $actionOrder) {
                $rows = ($byModule[$module] ?? collect())
                    ->sortBy(fn ($p) => array_search($p->action, $actionOrder, true) === false ? 99 : array_search($p->action, $actionOrder, true))
                    ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'action' => $p->action, 'label' => $p->label])
                    ->values();

                return ['module' => $module, 'label' => $def['label'], 'icon' => $def['icon'] ?? null, 'permissions' => $rows];
            })
            ->filter(fn ($g) => $g['permissions']->isNotEmpty())
            ->values();

        return response()->json(['data' => $groups]);
    }
}
