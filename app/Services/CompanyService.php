<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\CompanyRepository;
use App\Exceptions\BusinessRuleException;
use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class CompanyService
{
    public function __construct(private readonly CompanyRepository $companies) {}

    /** @param array<string, mixed> $filters */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->companies->paginate($filters);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Company
    {
        return $this->companies->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Company $company, array $data): Company
    {
        if (! empty($data['parent_id'])) {
            $this->assertValidParent($company, (int) $data['parent_id']);
        }

        return $this->companies->update($company, $data);
    }

    public function delete(Company $company): void
    {
        $subsidiaries = $this->companies->countChildren($company);

        if ($subsidiaries > 0) {
            throw new BusinessRuleException(
                "This company has {$subsidiaries} subsidiar".($subsidiaries === 1 ? 'y' : 'ies').'. Reassign or delete them first.',
                ['subsidiaries' => $subsidiaries],
            );
        }

        $this->companies->delete($company);
    }

    /**
     * Company count per type for the list tabs.
     *
     * @return array{total: int, by_type: array<string, int>}
     */
    public function summary(): array
    {
        $counts = $this->companies->countByType();

        return [
            'total' => (int) $counts->sum(),
            'by_type' => $counts
                ->reject(fn ($count, $typeId) => $typeId === '' || $typeId === null) // companies without a type
                ->map(fn ($count) => (int) $count)
                ->all(),
        ];
    }

    /** @return Collection<int, Company> */
    public function options(?int $excludeId = null): Collection
    {
        return $this->companies->options($excludeId);
    }

    /** A company can't be its own parent, nor sit below one of its own subsidiaries. */
    private function assertValidParent(Company $company, int $parentId): void
    {
        $lineage = [$parentId, ...$this->companies->ancestorIds($parentId)];

        if (in_array((int) $company->getKey(), $lineage, true)) {
            throw new BusinessRuleException('A company cannot be its own parent or a parent of its own ancestors.');
        }
    }
}
