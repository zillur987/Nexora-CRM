<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Lead;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface LeadRepository
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator;

    /** Loads the row with a pessimistic lock; must be called inside a transaction. */
    public function findForUpdate(string $id): Lead;

    /** @param array<string, mixed> $data */
    public function create(array $data): Lead;

    /** @param array<string, mixed> $data */
    public function update(Lead $lead, array $data): Lead;

    public function delete(Lead $lead): void;

    /** @return Collection<string, int|string> status value => number of leads */
    public function countByStatus(): Collection;
}
