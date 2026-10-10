<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pipelines\StorePipelineStageRequest;
use App\Http\Requests\Pipelines\UpdatePipelineStageRequest;
use App\Http\Resources\PipelineResource;
use App\Models\DealStage;
use App\Models\Pipeline;
use App\Services\PipelineService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PipelineStageController extends Controller
{
    public function __construct(private readonly PipelineService $service) {}

    public function index(Pipeline $pipeline)
    {
        return response()->json(['data' => $pipeline->stages()->withCount('deals')->get()->map(fn ($stage) => [
            'id' => $stage->id, 'pipeline_id' => $stage->pipeline_id, 'name' => $stage->name,
            'probability' => (int) $stage->probability, 'outcome' => $stage->outcome,
            'sort_order' => (int) $stage->sort_order, 'is_active' => (bool) ($stage->is_active ?? true),
            'deals_count' => (int) $stage->deals_count,
        ])]);
    }

    public function store(StorePipelineStageRequest $request, Pipeline $pipeline)
    {
        $stage = $this->service->createStage($pipeline, $request->validated());
        return response()->json(['data' => $stage], 201);
    }

    public function update(UpdatePipelineStageRequest $request, Pipeline $pipeline, DealStage $stage)
    {
        return response()->json(['data' => $this->service->updateStage($pipeline, $stage, $request->validated())]);
    }

    public function destroy(Pipeline $pipeline, DealStage $stage): Response
    {
        $this->service->deleteStage($pipeline, $stage);
        return response()->noContent();
    }
}
