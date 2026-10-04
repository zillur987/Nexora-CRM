<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Support\Collection;

interface PipelineStageRepository
{
    public function findForPipeline(Pipeline $pipeline, string $stageId): PipelineStage;
    /** @param array<string,mixed> $data */
    public function create(Pipeline $pipeline, array $data): PipelineStage;
    /** @param array<string,mixed> $data */
    public function update(PipelineStage $stage, array $data): PipelineStage;
    public function delete(PipelineStage $stage): void;
    /** @param array<int,array{id:string,position:int}> $items */
    public function reorder(Pipeline $pipeline, array $items): void;
    /** @return Collection<int,PipelineStage> */
    public function boardStages(Pipeline $pipeline): Collection;
}
