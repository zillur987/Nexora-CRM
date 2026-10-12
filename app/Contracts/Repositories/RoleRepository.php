<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RoleRepository
{
    public function paginate(array $filters): LengthAwarePaginator;
    public function create(array $data, array $permissionIds): Role;
    public function update(Role $role, array $data, ?array $permissionIds): Role;
    public function delete(Role $role): void;
}
