<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\QuoteRepository;
use App\Enums\QuoteStatus;
use App\Models\Quote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentQuoteRepository implements QuoteRepository
{
    private const SORTS = ['created_at', 'quote_number', 'total', 'issue_date', 'valid_until'];

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Quote::query()->with(Quote::RELATIONS);

        $has = fn (string $key): bool => isset($filters[$key]) && $filters[$key] !== '';

        if ($has('search')) {
            $term = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $query->where(fn (Builder $q) => $q
                ->where('quote_number', 'like', $term)
                ->orWhere('subject', 'like', $term)
                ->orWhereHas('company', fn (Builder $c) => $c->where('name', 'like', $term)));
        }

        $query
            ->when($has('status'), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($has('company_id'), fn (Builder $q) => $q->where('company_id', $filters['company_id']))
            ->when($has('contact_id'), fn (Builder $q) => $q->where('contact_id', $filters['contact_id']))
            ->when($has('deal_id'), fn (Builder $q) => $q->where('deal_id', $filters['deal_id']))
            ->when($has('issue_from'), fn (Builder $q) => $q->whereDate('issue_date', '>=', $filters['issue_from']))
            ->when($has('issue_to'), fn (Builder $q) => $q->whereDate('issue_date', '<=', $filters['issue_to']))
            ->when($has('owner_id'), fn (Builder $q) => $filters['owner_id'] === 'unassigned'
                ? $q->whereNull('owner_id')
                : $q->where('owner_id', (int) $filters['owner_id']));

        $sortBy = in_array($filters['sort_by'] ?? null, self::SORTS, true) ? $filters['sort_by'] : 'created_at';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($sortBy, $sortDir)
            ->orderByDesc('id') // stable order when many rows share a value
            ->paginate(min(max((int) ($filters['per_page'] ?? 20), 1), 100))
            ->withQueryString();
    }

    public function create(array $data, array $items): Quote
    {
        $quote = Quote::query()->create($data);
        $quote->items()->createMany($items);

        return $this->fresh($quote);
    }

    public function update(Quote $quote, array $data, ?array $items): Quote
    {
        $quote->fill($data)->save();

        if ($items !== null) {
            $quote->items()->delete();
            $quote->items()->createMany($items);
        }

        return $this->fresh($quote);
    }

    public function delete(Quote $quote): void
    {
        $quote->delete();
    }

    public function lastNumber(string $prefix): ?string
    {
        return Quote::withTrashed()
            ->where('quote_number', 'like', $prefix.'%')
            ->orderByDesc('quote_number')
            ->lockForUpdate()
            ->value('quote_number');
    }

    public function countByStatus(): Collection
    {
        return Quote::query()->select('status', DB::raw('COUNT(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status');
    }

    public function totalsByCurrency(): Collection
    {
        $open = [QuoteStatus::Draft->value, QuoteStatus::Sent->value];
        $in = implode(',', array_map(fn (string $s) => "'{$s}'", $open)); // constants from the enum, never user input

        return Quote::query()
            ->selectRaw("currency,
                SUM(CASE WHEN status IN ({$in}) THEN total ELSE 0 END) as open_amount,
                SUM(CASE WHEN status IN ({$in}) THEN 1 ELSE 0 END) as open_count,
                SUM(CASE WHEN status = ? THEN total ELSE 0 END) as accepted_amount", [QuoteStatus::Accepted->value])
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();
    }

    private function fresh(Quote $quote): Quote
    {
        return $quote->fresh([...Quote::RELATIONS, 'items']);
    }
}
