<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Pipeline;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PipelineRepository
{
    public function paginate(array $filters): LengthAwarePaginator;
    public function create(array $data): Pipeline;
    public function update(Pipeline $pipeline, array $data): Pipeline;
    public function delete(Pipeline $pipeline): void;
}
