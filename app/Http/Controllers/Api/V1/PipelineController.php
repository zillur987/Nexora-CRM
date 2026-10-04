<?php

declare(strict_types=1);
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pipelines\ListPipelinesRequest;
use App\Http\Requests\Pipelines\StorePipelineRequest;
use App\Http\Requests\Pipelines\UpdatePipelineRequest;
use App\Http\Resources\PipelineResource;
use App\Models\Pipeline;
use App\Services\PipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
class PipelineController extends Controller
{
    public function __construct(private readonly PipelineService $service) {}
    public function index(ListPipelinesRequest $request): AnonymousResourceCollection { return PipelineResource::collection($this->service->list($request->validated())); }
    public function store(StorePipelineRequest $request): JsonResponse { return PipelineResource::make($this->service->create($request->validated()))->response()->setStatusCode(201); }
    public function show(Pipeline $pipeline): PipelineResource { return PipelineResource::make($this->service->show($pipeline)); }
    public function update(UpdatePipelineRequest $request, Pipeline $pipeline): PipelineResource { return PipelineResource::make($this->service->update($pipeline,$request->validated())); }
    public function destroy(Pipeline $pipeline): Response { $this->service->delete($pipeline); return response()->noContent(); }
    public function board(Pipeline $pipeline): JsonResponse { return response()->json(['data'=>$this->service->board($pipeline)]); }
}
