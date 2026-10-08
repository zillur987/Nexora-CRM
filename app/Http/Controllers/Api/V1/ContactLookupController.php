<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContactLookup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\StoreContactLookupRequest;
use App\Services\ContactLookupService;
use Illuminate\Http\JsonResponse;

/** Serves both dropdowns: /contact-lookups/contact-sources and /contact-lookups/contact-stages. */
class ContactLookupController extends Controller
{
    public function __construct(private readonly ContactLookupService $lookups) {}

    public function index(ContactLookup $lookup): JsonResponse
    {
        return response()->json(['data' => $this->lookups->list($lookup)]);
    }

    public function store(StoreContactLookupRequest $request, ContactLookup $lookup): JsonResponse
    {
        $item = $this->lookups->findOrCreate($lookup, $request->validated('name'));

        return response()->json(['data' => $item->only(['id', 'name'])], 201);
    }
}
