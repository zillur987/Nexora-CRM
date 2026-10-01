<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Deals\ChangeDealStageRequest;
use App\Http\Requests\Deals\ListDealsRequest;
use App\Http\Requests\Deals\StoreDealRequest;
use App\Http\Requests\Deals\UpdateDealRequest;
use App\Http\Resources\DealResource;
use App\Models\Deal;
use App\Services\DealService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DealController extends Controller
{
    public function __construct(private readonly DealService $service) {}

    public function index(ListDealsRequest $request): AnonymousResourceCollection
    {
        return DealResource::collection($this->service->list($request->validated()));
    }

    public function pipeline(): JsonResponse
    {
        return response()->json(['data' => $this->service->pipeline()]);
    }

    public function store(StoreDealRequest $request): JsonResponse
    {
        return DealResource::make($this->service->create($request->validated()))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Deal $deal): DealResource
    {
        return DealResource::make($deal->load('contact'));
    }

    public function update(UpdateDealRequest $request, Deal $deal): DealResource
    {
        return DealResource::make($this->service->update($deal, $request->validated()));
    }

    public function changeStage(ChangeDealStageRequest $request, Deal $deal): DealResource
    {
        return DealResource::make($this->service->changeStage($deal, $request->stage()));
    }

    public function destroy(Deal $deal): Response
    {
        $this->service->delete($deal);

        return response()->noContent();
    }
}
