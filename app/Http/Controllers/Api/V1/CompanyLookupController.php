<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CompanyLookup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\StoreCompanyLookupRequest;
use App\Services\CompanyLookupService;
use Illuminate\Http\JsonResponse;

/** Serves both dropdowns: /company-lookups/industries and /company-lookups/company-types. */
class CompanyLookupController extends Controller
{
    public function __construct(private readonly CompanyLookupService $lookups) {}

    public function index(CompanyLookup $lookup): JsonResponse
    {
        return response()->json(['data' => $this->lookups->list($lookup)]);
    }

    public function store(StoreCompanyLookupRequest $request, CompanyLookup $lookup): JsonResponse
    {
        $item = $this->lookups->findOrCreate($lookup, $request->validated('name'));

        return response()->json(['data' => $item->only(['id', 'name'])], 201);
    }
}
