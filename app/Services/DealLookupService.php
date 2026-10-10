<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DealLookup;
use App\Models\DealStage;
use App\Models\DealType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Reads and extends the Deal Stage / Deal Type dropdown values. (Lead sources reuse ContactLookupService.) */
final class DealLookupService
{
    /** @return Collection<int, Model> stages in pipeline order, types alphabetically */
    public function list(DealLookup $lookup): Collection
    {
        $query = $this->model($lookup)::query();

        $query = match ($lookup) {
            DealLookup::Stages => $query->orderBy('sort_order')->orderBy('id'),
            DealLookup::Types => $query->orderBy('name'),
        };

        return $query->get($lookup->columns());
    }

    /**
     * Idempotent: adding a value that already exists (any casing) returns the existing row.
     * A new stage is appended to the end of the pipeline as an open stage.
     */
    public function findOrCreate(DealLookup $lookup, string $name): Model
    {
        $defaults = $lookup === DealLookup::Stages
            ? ['sort_order' => ((int) DealStage::query()->max('sort_order')) + 1]
            : [];

        return $this->model($lookup)::query()->firstOrCreate(['name' => $name], $defaults);
    }

    public function stage(int|string $id): ?DealStage
    {
        return DealStage::query()->find($id);
    }

    /** @return array<string, mixed> */
    public function payload(DealLookup $lookup, Model $item): array
    {
        return $item->fresh()?->only($lookup->columns()) ?? $item->only($lookup->columns());
    }

    /** @return class-string<Model> */
    private function model(DealLookup $lookup): string
    {
        return match ($lookup) {
            DealLookup::Stages => DealStage::class,
            DealLookup::Types => DealType::class,
        };
    }
}
