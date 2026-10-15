<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\Cms\PublicSiteService;
use Illuminate\Http\JsonResponse;

/** Unauthenticated, read-only, cached. Consumed by the public Vue website. */
class SiteContentController extends Controller
{
    public function __construct(private readonly PublicSiteService $service) {}

    public function header(): JsonResponse
    {
        return $this->respond($this->service->header());
    }

    public function banner(): JsonResponse
    {
        return $this->respond($this->service->banner());
    }

    private function respond(array $payload): JsonResponse
    {
        return response()->json(['data' => $payload])->header('Cache-Control', 'public, max-age=60');
    }
}
