<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotes\ListQuotesRequest;
use App\Http\Requests\Quotes\StoreQuoteRequest;
use App\Http\Requests\Quotes\UpdateQuoteRequest;
use App\Http\Requests\Quotes\UpdateQuoteStatusRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Services\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class QuoteController extends Controller
{
    public function __construct(private readonly QuoteService $service) {}

    public function index(ListQuotesRequest $request): AnonymousResourceCollection
    {
        return QuoteResource::collection($this->service->list($request->validated()));
    }

    /** Quote count per status (list tabs) and open / accepted value per currency (KPI strip). */
    public function summary(): JsonResponse
    {
        return response()->json(['data' => $this->service->summary()]);
    }

    public function store(StoreQuoteRequest $request): JsonResponse
    {
        return QuoteResource::make($this->service->create($request->validated()))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Quote $quote): QuoteResource
    {
        return QuoteResource::make($quote->load([...Quote::RELATIONS, 'items']));
    }

    public function update(UpdateQuoteRequest $request, Quote $quote): QuoteResource
    {
        return QuoteResource::make($this->service->update($quote, $request->validated()));
    }

    /** Quick status change from the detail page (sent / accepted / rejected / back to draft). */
    public function updateStatus(UpdateQuoteStatusRequest $request, Quote $quote): QuoteResource
    {
        return QuoteResource::make($this->service->changeStatus($quote, $request->validated('status')));
    }

    public function destroy(Quote $quote): Response
    {
        $this->service->delete($quote);

        return response()->noContent();
    }
}
