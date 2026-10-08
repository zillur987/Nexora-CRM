<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\CompanyRepository;
use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentCompanyRepository implements CompanyRepository
{
    private const RELATIONS = ['owner:id,name', 'industry:id,name', 'type:id,name', 'parent:id,name'];

    private const OPTIONS_LIMIT = 500;

    private const MAX_DEPTH = 50;

    public function paginate(array $filters): LengthAwarePaginator
    {
        return Company::query()
            ->with(self::RELATIONS)
            ->withCount('children')
            ->when($filters['company_type_id'] ?? null, fn ($q, $id) => $q->where('company_type_id', $id))
            ->when($filters['industry_id'] ?? null, fn ($q, $id) => $q->where('industry_id', $id))
            ->when($filters['size'] ?? null, fn ($q, $size) => $q->where('size', $size))
            ->when($filters['country'] ?? null, fn ($q, $country) => $q->where('billing_country', $country))
            ->when($filters['owner_id'] ?? null, function ($q, string $owner) {
                $owner === 'unassigned'
                    ? $q->whereNull('owner_id')
                    : $q->where('owner_id', (int) $owner);
            })
            ->when($filters['search'] ?? null, function ($q, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('website', 'like', $like)
                    ->orWhere('billing_city', 'like', $like));
            })
            ->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'desc')
            ->orderBy('id') // deterministic order for ties, keeps pagination stable
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();
    }

    public function create(array $data): Company
    {
        return $this->hydrate(Company::create($data));
    }

    public function update(Company $company, array $data): Company
    {
        $company->update($data);

        return $this->hydrate($company);
    }

    public function delete(Company $company): void
    {
        $company->delete(); // soft delete
    }

    public function countChildren(Company $company): int
    {
        return $company->children()->count();
    }

    public function ancestorIds(int $companyId): array
    {
        $ids = [];
        $current = $companyId;

        // The depth cap is a guard against corrupt data that already contains a cycle.
        while (count($ids) < self::MAX_DEPTH) {
            $parentId = Company::query()->whereKey($current)->value('parent_id');

            if ($parentId === null) {
                break;
            }

            $ids[] = $current = (int) $parentId;
        }

        return $ids;
    }

    public function countByType(): Collection
    {
        return Company::query()
            ->selectRaw('company_type_id, COUNT(*) as aggregate')
            ->groupBy('company_type_id')
            ->pluck('aggregate', 'company_type_id');
    }

    public function options(?int $excludeId = null): Collection
    {
        return Company::query()
            ->when($excludeId, fn ($q, int $id) => $q->whereKeyNot($id))
            ->orderBy('name')
            ->limit(self::OPTIONS_LIMIT)
            ->get(['id', 'name']);
    }

    private function hydrate(Company $company): Company
    {
        return $company->load(self::RELATIONS)->loadCount('children');
    }
}
