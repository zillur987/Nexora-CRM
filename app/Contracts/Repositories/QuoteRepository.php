<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Quote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface QuoteRepository
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator;

    /**
     * @param array<string, mixed> $data
     * @param list<array<string, mixed>> $items
     */
    public function create(array $data, array $items): Quote;

    /**
     * @param array<string, mixed> $data
     * @param list<array<string, mixed>>|null $items null = leave the lines untouched
     */
    public function update(Quote $quote, array $data, ?array $items): Quote;

    public function delete(Quote $quote): void;

    /** The highest quote number issued for the given prefix (soft-deleted quotes included), locked for the transaction. */
    public function lastNumber(string $prefix): ?string;

    /** @return Collection<string, int> count keyed by status */
    public function countByStatus(): Collection;

    /** @return Collection<int, object> one row per currency: open_amount, open_count, accepted_amount */
    public function totalsByCurrency(): Collection;
}
