<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Pipeline;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PipelineRepository
{
    /** @param array<string,mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator;
    public function findWithStages(string $id): Pipeline;
    /** @param array<string,mixed> $data */
    public function create(array $data): Pipeline;
    /** @param array<string,mixed> $data */
    public function update(Pipeline $pipeline, array $data): Pipeline;
    public function delete(Pipeline $pipeline): void;
}
