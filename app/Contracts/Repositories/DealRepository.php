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

    /** @param array<string, mixed> $data */
    public function create(array $data): Deal;

    /** @param array<string, mixed> $data */
    public function update(Deal $deal, array $data): Deal;

    public function delete(Deal $deal): void;

    /** @return Collection<string|int, int|string> deal_stage_id => number of deals */
    public function countByStage(): Collection;

    /**
     * Pipeline value per currency (amounts are never summed across currencies).
     *
     * @return Collection<int, object{currency: string, open_amount: string|float, weighted_amount: string|float, won_amount: string|float, open_count: string|int}>
     */
    public function totalsByCurrency(): Collection;
}
