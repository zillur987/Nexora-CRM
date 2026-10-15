<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\UpdateSiteSettingsRequest;
use App\Services\Cms\SiteSettingService;
use Illuminate\Http\JsonResponse;

class SiteSettingController extends Controller
{
    public function __construct(private readonly SiteSettingService $service) {}

    public function show(string $group): JsonResponse
    {
        return response()->json(['data' => $this->service->get($group)]);
    }

    public function update(UpdateSiteSettingsRequest $request, string $group): JsonResponse
    {
        // validated() keeps the UploadedFile instances; the service stores them.
        return response()->json(['data' => $this->service->update($group, $request->validated())]);
    }
}
