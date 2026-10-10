<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DealLookup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deals\StoreDealLookupRequest;
use App\Services\DealLookupService;
use Illuminate\Http\JsonResponse;

/** Serves both dropdowns: /deal-lookups/deal-stages and /deal-lookups/deal-types. */
class DealLookupController extends Controller
{
    public function __construct(private readonly DealLookupService $lookups) {}

    public function index(DealLookup $lookup): JsonResponse
    {
        return response()->json(['data' => $this->lookups->list($lookup)]);
    }

    public function store(StoreDealLookupRequest $request, DealLookup $lookup): JsonResponse
    {
        $item = $this->lookups->findOrCreate($lookup, $request->validated('name'));

        return response()->json(['data' => $this->lookups->payload($lookup, $item)], 201);
    }
}
