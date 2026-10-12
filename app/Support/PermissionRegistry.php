<?php

declare(strict_types=1);

namespace App\Support;

/** Reads config/permissions.php and expands it into concrete permission rows. */
final class PermissionRegistry
{
    /** @return array<int, array{name:string,module:string,action:string,label:string}> */
    public static function all(): array
    {
        $labels = config('permissions.actions', []);
        $rows = [];

        foreach (config('permissions.modules', []) as $module => $def) {
            foreach ($def['actions'] as $action) {
                $rows[] = [
                    'name' => "{$module}.{$action}",
                    'module' => $module,
                    'action' => $action,
                    'label' => $labels[$action] ?? ucfirst($action),
                ];
            }
        }

        return $rows;
    }

    /** @return array<int,string> */
    public static function names(): array
    {
        return array_column(self::all(), 'name');
    }

    /**
     * Expands patterns ('*', 'leads.*', 'products.view') minus exclusions into permission names.
     *
     * @param array<int,string> $patterns
     * @param array<int,string> $except
     * @return array<int,string>
     */
    public static function expand(array $patterns, array $except = []): array
    {
        $match = static fn (string $name, array $list): bool => collect($list)->contains(
            fn (string $p) => $p === '*' || $p === $name || (str_ends_with($p, '.*') && str_starts_with($name, substr($p, 0, -1)))
        );

        return array_values(array_filter(
            self::names(),
            fn (string $name) => $match($name, $patterns) && ! $match($name, $except),
        ));
    }
}
