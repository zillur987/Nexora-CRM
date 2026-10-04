<?php

declare(strict_types=1);
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pipelines\ReorderPipelineStagesRequest;
use App\Http\Requests\Pipelines\StorePipelineStageRequest;
use App\Http\Requests\Pipelines\UpdatePipelineStageRequest;
use App\Http\Resources\PipelineStageResource;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Services\PipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
class PipelineStageController extends Controller
{
    public function __construct(private readonly PipelineService $service) {}
    public function store(StorePipelineStageRequest $request, Pipeline $pipeline): JsonResponse { return PipelineStageResource::make($this->service->createStage($pipeline,$request->validated()))->response()->setStatusCode(201); }
    public function update(UpdatePipelineStageRequest $request, Pipeline $pipeline, PipelineStage $stage): PipelineStageResource { $stage=$pipeline->stages()->whereKey($stage->id)->firstOrFail(); return PipelineStageResource::make($this->service->updateStage($stage,$request->validated())); }
    public function destroy(Pipeline $pipeline, PipelineStage $stage): Response { $stage=$pipeline->stages()->whereKey($stage->id)->firstOrFail(); $this->service->deleteStage($stage); return response()->noContent(); }
    public function reorder(ReorderPipelineStagesRequest $request, Pipeline $pipeline): Response { $this->service->reorderStages($pipeline,$request->validated('stages')); return response()->noContent(); }
}
