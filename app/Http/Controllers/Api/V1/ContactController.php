<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\ListContactsRequest;
use App\Http\Requests\Contacts\StoreContactRequest;
use App\Http\Requests\Contacts\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ContactController extends Controller
{
    public function __construct(private readonly ContactService $service) {}

    public function index(ListContactsRequest $request): AnonymousResourceCollection
    {
        return ContactResource::collection($this->service->list($request->validated()));
    }

    /** Lightweight id/name list for <select> dropdowns. */
    public function options(): JsonResponse
    {
        return response()->json(['data' => $this->service->options()
            ->map(fn (Contact $c) => ['id' => $c->id, 'name' => $c->full_name, 'company' => $c->company])
            ->values()]);
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        return ContactResource::make($this->service->create($request->validated()))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Contact $contact): ContactResource
    {
        return ContactResource::make($contact->load('deals'));
    }

    public function update(UpdateContactRequest $request, Contact $contact): ContactResource
    {
        return ContactResource::make($this->service->update($contact, $request->validated()));
    }

    public function destroy(Contact $contact): Response
    {
        $this->service->delete($contact);

        return response()->noContent();
    }
}
