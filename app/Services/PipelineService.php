<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\PipelineRepository;
use App\Models\DealStage;
use App\Models\Pipeline;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PipelineService
{
    public function __construct(private readonly PipelineRepository $pipelines) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->pipelines->paginate($filters);
    }

    public function create(array $data): Pipeline
    {
        return $this->pipelines->create($data);
    }

    public function update(Pipeline $pipeline, array $data): Pipeline
    {
        return $this->pipelines->update($pipeline, $data);
    }

    public function delete(Pipeline $pipeline): void
    {
        $this->pipelines->delete($pipeline);
    }

    public function createStage(Pipeline $pipeline, array $data): DealStage
    {
        $data['pipeline_id'] = $pipeline->id;
        $data['sort_order'] = $data['sort_order'] ?? (((int) $pipeline->stages()->max('sort_order')) + 10);
        $data['is_active'] = $data['is_active'] ?? true;

        if ($pipeline->stages()->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->exists()) {
            throw ValidationException::withMessages(['name' => 'A stage with this name already exists in this pipeline.']);
        }

        return DealStage::query()->create($data);
    }

    public function updateStage(Pipeline $pipeline, DealStage $stage, array $data): DealStage
    {
        abort_unless((int) $stage->pipeline_id === (int) $pipeline->id, 404);
        $stage->fill($data)->save();

        return $stage->fresh();
    }

    public function deleteStage(Pipeline $pipeline, DealStage $stage): void
    {
        abort_unless((int) $stage->pipeline_id === (int) $pipeline->id, 404);
        if ($stage->deals()->exists()) {
            throw ValidationException::withMessages(['stage' => 'This stage is in use by deals. Move those deals before deleting it.']);
        }
        $stage->delete();
    }
}
