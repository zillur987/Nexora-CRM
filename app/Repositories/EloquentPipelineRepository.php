<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\PipelineRepository;
use App\Models\Pipeline;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentPipelineRepository implements PipelineRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Pipeline::query()
            ->with(['stages' => fn ($q) => $q->withCount('deals')])
            ->withCount('deals')
            ->when($filters['search'] ?? null, function ($q, string $term) {
                $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%');
            })
            ->when(array_key_exists('is_active', $filters), fn ($q) => $q->where('is_active', $filters['is_active']))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();
    }

    public function findWithStages(string $id): Pipeline
    {
        return Pipeline::query()
            ->with(['stages' => fn ($q) => $q->withCount('deals')])
            ->withCount('deals')
            ->findOrFail($id);
    }

    public function create(array $data): Pipeline
    {
        return Pipeline::create($data)->load('stages');
    }

    public function update(Pipeline $pipeline, array $data): Pipeline
    {
        $pipeline->update($data);
        return $pipeline->fresh('stages');
    }

    public function delete(Pipeline $pipeline): void
    {
        $pipeline->delete();
    }
}
