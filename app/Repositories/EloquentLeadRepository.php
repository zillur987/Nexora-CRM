<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\LeadRepository;
use App\Models\Lead;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentLeadRepository implements LeadRepository
{
    private const OWNER_COLUMNS = 'owner:id,name';

    public function paginate(array $filters): LengthAwarePaginator
    {
        return Lead::query()
            ->with(self::OWNER_COLUMNS)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('source', $source))
            ->when($filters['assigned_to'] ?? null, function ($q, string $owner) {
                $owner === 'unassigned'
                    ? $q->whereNull('assigned_to')
                    : $q->where('assigned_to', (int) $owner);
            })
            ->when($filters['search'] ?? null, function ($q, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('company', 'like', $like));
            })
            ->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'desc')
            ->orderBy('id') // deterministic order for ties, keeps pagination stable
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();
    }

    public function findForUpdate(string $id): Lead
    {
        return Lead::query()->lockForUpdate()->findOrFail($id);
    }

    public function create(array $data): Lead
    {
        return Lead::create($data)->load(self::OWNER_COLUMNS);
    }

    public function update(Lead $lead, array $data): Lead
    {
        $lead->update($data);

        return $lead->load(self::OWNER_COLUMNS);
    }

    public function delete(Lead $lead): void
    {
        $lead->delete(); // soft delete
    }

    public function countByStatus(): Collection
    {
        return Lead::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
    }
}
