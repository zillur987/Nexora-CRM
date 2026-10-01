<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactResource;
use App\Http\Resources\DealResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(): JsonResponse
    {
        $stats = $this->dashboard->stats();

        return response()->json(['data' => [
            'contacts' => $stats['contacts'],
            'leads' => $stats['leads'],
            'open_deals' => $stats['open_deals'],
            'won_revenue' => $stats['won_revenue'],
            'pipeline' => $stats['pipeline'],
            'recent_deals' => DealResource::collection($stats['recent_deals'])->resolve(),
            'recent_contacts' => ContactResource::collection($stats['recent_contacts'])->resolve(),
        ]]);
    }
}
