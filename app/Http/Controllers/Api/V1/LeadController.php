<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\ChangeLeadStatusRequest;
use App\Http\Requests\Leads\ConvertLeadRequest;
use App\Http\Requests\Leads\ImportLeadsRequest;
use App\Http\Requests\Leads\ListLeadsRequest;
use App\Http\Requests\Leads\StoreLeadRequest;
use App\Http\Requests\Leads\UpdateLeadRequest;
use App\Http\Resources\ContactResource;
use App\Http\Resources\DealResource;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Services\LeadConversionService;
use App\Services\LeadImportService;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class LeadController extends Controller
{
    public function __construct(
        private readonly LeadService $service,
        private readonly LeadConversionService $conversion,
        private readonly LeadImportService $importer,
    ) {}

    public function index(ListLeadsRequest $request): AnonymousResourceCollection
    {
        return LeadResource::collection($this->service->list($request->validated()));
    }

    /** Count of leads per status. */
    public function summary(): JsonResponse
    {
        return response()->json(['data' => $this->service->summary()]);
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        return LeadResource::make($this->service->create($request->validated()))
            ->response()
            ->setStatusCode(201);
    }

     /** Bulk-create leads from an uploaded CSV. Responds with { created, skipped, errors[] }. */
    public function import(ImportLeadsRequest $request): JsonResponse
    {
        $result = $this->importer->import($request->file('file')->getRealPath());

        return response()->json(['data' => $result->toArray()]);
    }
    
    public function show(Lead $lead): LeadResource
    {
        return LeadResource::make($lead->load('owner'));
    }

    public function update(UpdateLeadRequest $request, Lead $lead): LeadResource
    {
        return LeadResource::make($this->service->update($lead, $request->validated()));
    }

    public function changeStatus(ChangeLeadStatusRequest $request, Lead $lead): LeadResource
    {
        return LeadResource::make($this->service->changeStatus($lead, $request->status()));
    }

    public function convert(ConvertLeadRequest $request, Lead $lead): JsonResponse
    {
        $result = $this->conversion->convert($lead, $request->data());

        return response()->json(['data' => [
            'lead' => LeadResource::make($result->lead)->resolve(),
            'contact' => ContactResource::make($result->contact)->resolve(),
            'deal' => $result->deal ? DealResource::make($result->deal)->resolve() : null,
            'contact_created' => $result->contactCreated,
        ]]);
    }

    public function destroy(Lead $lead): Response
    {
        $this->service->delete($lead);

        return response()->noContent();
    }
}
