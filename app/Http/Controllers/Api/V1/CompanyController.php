<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\CompanyOptionsRequest;
use App\Http\Requests\Companies\ListCompaniesRequest;
use App\Http\Requests\Companies\StoreCompanyRequest;
use App\Http\Requests\Companies\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CompanyController extends Controller
{
    public function __construct(private readonly CompanyService $service) {}

    public function index(ListCompaniesRequest $request): AnonymousResourceCollection
    {
        return CompanyResource::collection($this->service->list($request->validated()));
    }

    /** Company count per type, for the list tabs. */
    public function summary(): JsonResponse
    {
        return response()->json(['data' => $this->service->summary()]);
    }

    /** Lightweight id/name list for the "parent company" dropdown. */
    public function options(CompanyOptionsRequest $request): JsonResponse
    {
        $exclude = $request->validated('exclude');

        return response()->json(['data' => $this->service->options($exclude ? (int) $exclude : null)]);
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        return CompanyResource::make($this->service->create($request->validated()))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Company $company): CompanyResource
    {
        return CompanyResource::make(
            $company->load(['owner:id,name', 'industry:id,name', 'type:id,name', 'parent:id,name', 'children:id,parent_id,name'])
                ->loadCount('children'),
        );
    }

    public function update(UpdateCompanyRequest $request, Company $company): CompanyResource
    {
        return CompanyResource::make($this->service->update($company, $request->validated()));
    }

    public function destroy(Company $company): Response
    {
        $this->service->delete($company);

        return response()->noContent();
    }
}
