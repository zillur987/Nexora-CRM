<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\PipelineStageRepository;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentPipelineStageRepository implements PipelineStageRepository
{
    public function findForPipeline(Pipeline $pipeline, string $stageId): PipelineStage
    {
        return $pipeline->stages()->whereKey($stageId)->firstOrFail();
    }

    public function create(Pipeline $pipeline, array $data): PipelineStage
    {
        return $pipeline->stages()->create($data);
    }

    public function update(PipelineStage $stage, array $data): PipelineStage
    {
        $stage->update($data);
        return $stage->fresh();
    }

    public function delete(PipelineStage $stage): void
    {
        $stage->delete();
    }

    public function reorder(Pipeline $pipeline, array $items): void
    {
        DB::transaction(function () use ($pipeline, $items) {
            // Avoid transient unique(pipeline_id, position) conflicts while reordering.
            foreach ($items as $index => $item) {
                $pipeline->stages()->whereKey($item['id'])->update(['position' => -($index + 1)]);
            }

            foreach ($items as $item) {
                $pipeline->stages()->whereKey($item['id'])->update(['position' => $item['position']]);
            }
        });
    }

    public function boardStages(Pipeline $pipeline): Collection
    {
        return $pipeline->stages()
            ->with(['deals' => fn ($q) => $q->with('contact')->latest()])
            ->withCount('deals')
            ->orderBy('position')
            ->get();
    }
}
