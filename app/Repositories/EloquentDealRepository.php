<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\DealRepository;
use App\Models\Deal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentDealRepository implements DealRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Deal::query()
            ->with(Deal::RELATIONS)
            ->when($filters['deal_stage_id'] ?? null, fn ($q, $id) => $q->where('deal_stage_id', $id))
            ->when($filters['deal_type_id'] ?? null, fn ($q, $id) => $q->where('deal_type_id', $id))
            ->when($filters['lead_source_id'] ?? null, fn ($q, $id) => $q->where('lead_source_id', $id))
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->when($filters['contact_id'] ?? null, fn ($q, $id) => $q->where('contact_id', $id))
            ->when($filters['priority'] ?? null, fn ($q, $priority) => $q->where('priority', $priority))
            ->when($filters['outcome'] ?? null, fn ($q, $outcome) => $q->whereHas('stage', fn ($s) => $s->where('outcome', $outcome)))
            ->when($filters['close_from'] ?? null, fn ($q, $date) => $q->whereDate('expected_close_date', '>=', $date))
            ->when($filters['close_to'] ?? null, fn ($q, $date) => $q->whereDate('expected_close_date', '<=', $date))
            ->when($filters['owner_id'] ?? null, function ($q, string $owner) {
                $owner === 'unassigned'
                    ? $q->whereNull('owner_id')
                    : $q->where('owner_id', (int) $owner);
            })
            ->when($filters['search'] ?? null, function ($q, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w
                    ->where('name', 'like', $like)
                    ->orWhere('next_step', 'like', $like)
                    ->orWhereHas('company', fn ($c) => $c->where('name', 'like', $like))
                    ->orWhereHas('contact', fn ($c) => $c->whereRaw("CONCAT_WS(' ', first_name, last_name) like ?", [$like])));
            })
            ->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'desc')
            ->orderBy('id') // deterministic order for ties, keeps pagination stable
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();
    }

    public function create(array $data): Deal
    {
        return $this->hydrate(Deal::create($data));
    }

    public function update(Deal $deal, array $data): Deal
    {
        $deal->update($data);

        return $this->hydrate($deal);
    }

    public function delete(Deal $deal): void
    {
        $deal->delete(); // soft delete
    }

    public function countByStage(): Collection
    {
        return Deal::query()
            ->selectRaw('deal_stage_id, COUNT(*) as aggregate')
            ->groupBy('deal_stage_id')
            ->pluck('aggregate', 'deal_stage_id');
    }

    public function totalsByCurrency(): Collection
    {
        return Deal::query()
            ->join('deal_stages', 'deal_stages.id', '=', 'deals.deal_stage_id')
            ->selectRaw('COALESCE(deals.currency, ?) AS currency', [Deal::DEFAULT_CURRENCY])
            ->selectRaw("SUM(CASE WHEN deal_stages.outcome = 'open' THEN COALESCE(deals.amount, 0) ELSE 0 END) AS open_amount")
            ->selectRaw("SUM(CASE WHEN deal_stages.outcome = 'open' THEN COALESCE(deals.amount, 0) * COALESCE(deals.probability, 0) / 100 ELSE 0 END) AS weighted_amount")
            ->selectRaw("SUM(CASE WHEN deal_stages.outcome = 'won' THEN COALESCE(deals.amount, 0) ELSE 0 END) AS won_amount")
            ->selectRaw("SUM(CASE WHEN deal_stages.outcome = 'open' THEN 1 ELSE 0 END) AS open_count")
            ->groupBy('currency')
            ->orderByDesc('open_amount')
            ->get();
    }

    private function hydrate(Deal $deal): Deal
    {
        return $deal->load(Deal::RELATIONS);
    }
}
