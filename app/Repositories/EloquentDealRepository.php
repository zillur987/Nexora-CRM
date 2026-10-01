<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\DealRepository;
use App\Models\Deal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentDealRepository implements DealRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Deal::query()
            ->with('contact')
            ->when($filters['stage'] ?? null, fn ($q, $stage) => $q->where('stage', $stage))
            ->when($filters['contact_id'] ?? null, fn ($q, $id) => $q->where('contact_id', $id))
            ->when($filters['search'] ?? null, function ($q, string $term) {
                $q->where('title', 'like', '%'.addcslashes($term, '%_\\').'%');
            })
            ->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'desc')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();
    }

    public function findForUpdate(string $id): Deal
    {
        return Deal::query()->lockForUpdate()->findOrFail($id);
    }

    public function create(array $data): Deal
    {
        return Deal::create($data)->load('contact');
    }

    public function update(Deal $deal, array $data): Deal
    {
        $deal->update($data);

        return $deal->load('contact');
    }

    public function delete(Deal $deal): void
    {
        $deal->delete();
    }

    public function pipelineSummary(): Collection
    {
        return Deal::query()
            ->select('stage', 'currency', DB::raw('COUNT(*) as deals_count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('stage', 'currency')
            ->get();
    }
}
