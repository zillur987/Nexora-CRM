<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CompanyRepository
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator;

    /** @param array<string, mixed> $data */
    public function create(array $data): Company;

    /** @param array<string, mixed> $data */
    public function update(Company $company, array $data): Company;

    public function delete(Company $company): void;

    public function countChildren(Company $company): int;

    /** Ids of every ancestor of the given company, nearest first. @return list<int> */
    public function ancestorIds(int $companyId): array;

    /** @return Collection<string|int, int|string> company_type_id => number of companies */
    public function countByType(): Collection;

    /** id + name pairs for <select> dropdowns. @return Collection<int, Company> */
    public function options(?int $excludeId = null): Collection;
}
