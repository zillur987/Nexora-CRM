<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Deal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface DealRepository
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator;

    /** Loads the row with a pessimistic lock; must be called inside a transaction. */
    public function findForUpdate(string $id): Deal;

    /** @param array<string, mixed> $data */
    public function create(array $data): Deal;

    /** @param array<string, mixed> $data */
    public function update(Deal $deal, array $data): Deal;

    public function delete(Deal $deal): void;

    /** @return Collection<int, Deal> */
    public function pipelineSummary(): Collection;
}
