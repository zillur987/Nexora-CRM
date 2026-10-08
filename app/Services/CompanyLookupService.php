<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CompanyLookup;
use App\Models\CompanyType;
use App\Models\Industry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Reads and extends the Industry / Company Type dropdown values. */
final class CompanyLookupService
{
    /** @return Collection<int, Model> */
    public function list(CompanyLookup $lookup): Collection
    {
        return $this->model($lookup)::query()->orderBy('name')->get(['id', 'name']);
    }

    /** Idempotent: adding a value that already exists (any casing) returns the existing row. */
    public function findOrCreate(CompanyLookup $lookup, string $name): Model
    {
        return $this->model($lookup)::query()->firstOrCreate(['name' => $name]);
    }

    /** @return class-string<Model> */
    private function model(CompanyLookup $lookup): string
    {
        return match ($lookup) {
            CompanyLookup::Industries => Industry::class,
            CompanyLookup::Types => CompanyType::class,
        };
    }
}
