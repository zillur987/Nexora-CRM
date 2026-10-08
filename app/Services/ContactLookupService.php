<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContactLookup;
use App\Models\ContactSource;
use App\Models\ContactStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Reads and extends the Contact Source / Contact Stage dropdown values. (Industries reuse CompanyLookupService.) */
final class ContactLookupService
{
    /** @return Collection<int, Model> */
    public function list(ContactLookup $lookup): Collection
    {
        return $this->model($lookup)::query()->orderBy('name')->get(['id', 'name']);
    }

    /** Idempotent: adding a value that already exists (any casing) returns the existing row. */
    public function findOrCreate(ContactLookup $lookup, string $name): Model
    {
        return $this->model($lookup)::query()->firstOrCreate(['name' => $name]);
    }

    /** @return class-string<Model> */
    private function model(ContactLookup $lookup): string
    {
        return match ($lookup) {
            ContactLookup::Sources => ContactSource::class,
            ContactLookup::Stages => ContactStage::class,
        };
    }
}
