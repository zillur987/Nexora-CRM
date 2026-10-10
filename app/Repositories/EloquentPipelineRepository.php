<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\PipelineRepository;
use App\Models\Pipeline;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EloquentPipelineRepository implements PipelineRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Pipeline::query()
            ->with(['stages' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->withCount(['deals', 'stages'])
            ->when(isset($filters['search']) && $filters['search'] !== '', function ($q) use ($filters): void {
                $term = '%'.addcslashes($filters['search'], '%_\\\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', fn ($q) => $q->where('is_active', (bool) $filters['is_active']))
            ->orderByDesc('is_default')->orderBy('sort_order')->orderBy('name')
            ->paginate(min(max((int) ($filters['per_page'] ?? 20), 1), 100))
            ->withQueryString();
    }

    public function create(array $data): Pipeline
    {
        return $this->persist(new Pipeline(), $data);
    }

    public function update(Pipeline $pipeline, array $data): Pipeline
    {
        return $this->persist($pipeline, $data);
    }

    public function delete(Pipeline $pipeline): void
    {
        if ($pipeline->deals()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'pipeline' => 'This pipeline still has deals. Move or remove those deals before deleting it.',
            ]);
        }
        if ($pipeline->is_default) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'pipeline' => 'Choose another default pipeline before deleting this one.',
            ]);
        }
        $pipeline->delete();
    }

    private function persist(Pipeline $pipeline, array $data): Pipeline
    {
        return DB::transaction(function () use ($pipeline, $data): Pipeline {
            $makeDefault = (bool) ($data['is_default'] ?? $pipeline->is_default);
            if ($makeDefault) {
                Pipeline::query()->where('is_default', true)->whereKeyNot($pipeline->id)->update(['is_default' => false]);
            }
            $pipeline->fill($data);
            $pipeline->save();

            return $pipeline->fresh(['stages' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
                ->loadCount(['deals', 'stages']);
        });
    }
}
