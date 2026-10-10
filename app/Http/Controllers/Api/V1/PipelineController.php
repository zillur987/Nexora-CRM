<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pipelines\StorePipelineRequest;
use App\Http\Requests\Pipelines\UpdatePipelineRequest;
use App\Http\Resources\PipelineResource;
use App\Models\Pipeline;
use App\Services\PipelineService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PipelineController extends Controller
{
    public function __construct(private readonly PipelineService $service) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'in:0,1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return PipelineResource::collection($this->service->list($filters));
    }

    public function store(StorePipelineRequest $request)
    {
        return PipelineResource::make($this->service->create($request->validated()))
            ->response()->setStatusCode(201);
    }

    public function show(Pipeline $pipeline): PipelineResource
    {
        return PipelineResource::make($pipeline->load(['stages' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])->loadCount(['deals', 'stages']));
    }

    public function update(UpdatePipelineRequest $request, Pipeline $pipeline): PipelineResource
    {
        return PipelineResource::make($this->service->update($pipeline, $request->validated()));
    }

    public function destroy(Pipeline $pipeline): Response
    {
        $this->service->delete($pipeline);
        return response()->noContent();
    }
}
