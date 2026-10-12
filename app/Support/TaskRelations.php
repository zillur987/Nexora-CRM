<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/** Resolves the linked CRM models (company, contact, opportunity, quote) from config/tasks.php. */
final class TaskRelations
{
    /** @return array<int,string> keys whose model class actually exists in this project */
    public static function available(): array
    {
        return array_values(array_filter(array_keys(config('tasks.relations', [])), fn (string $k) => self::modelClass($k) !== null));
    }

    public static function modelClass(string $key): ?string
    {
        $class = config("tasks.relations.$key.model");
        return is_string($class) && class_exists($class) ? $class : null;
    }

    public static function table(string $key): ?string
    {
        $class = self::modelClass($key);
        return $class ? (new $class)->getTable() : null;
    }

    /** @return array<int,string> */
    public static function columns(string $key): array
    {
        return array_values((array) config("tasks.relations.$key.columns", ['name']));
    }

    public static function label(string $key, ?Model $model): ?string
    {
        if (! $model) return null;

        $parts = collect(self::columns($key))
            ->map(fn (string $c) => trim((string) $model->getAttribute($c)))
            ->filter()
            ->all();

        return $parts ? implode(' ', $parts) : '#'.$model->getKey();
    }

    /** @return array<int,array{id:int|string,name:string}> */
    public static function options(string $key): array
    {
        $class = self::modelClass($key);
        if (! $class) return [];

        $columns = self::columns($key);
        $keyName = (new $class)->getKeyName();

        return $class::query()
            ->select(array_values(array_unique([$keyName, ...$columns])))
            ->orderBy($columns[0])
            ->limit((int) config('tasks.lookup_limit', 500))
            ->get()
            ->map(fn (Model $m) => ['id' => $m->getKey(), 'name' => (string) self::label($key, $m)])
            ->all();
    }

    /** @return array<int,string> validation rule(s) that the id exists; empty when the model is missing */
    public static function existsRule(string $key): array
    {
        $table = self::table($key);
        return $table ? ["exists:$table,id"] : [];
    }

    public static function userModel(): string
    {
        return config('auth.providers.users.model');
    }

    public static function userTable(): string
    {
        $class = self::userModel();
        return (new $class)->getTable();
    }
}
