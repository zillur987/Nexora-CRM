<?php // app/Http/Controllers/Api/V1/PipelineController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DealResource;
use App\Http\Resources\PipelineResource;
use App\Models\Deal;
use App\Models\Pipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PipelineController extends Controller
{
    private const DEALS_PER_STAGE = 50;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Deal::class);

        return PipelineResource::collection(
            Pipeline::orderByDesc('is_default')->orderBy('name')->get()
        );
    }

    /**
     * Kanban payload: stages + first N deals per stage + true totals per stage.
     */
    public function board(Request $request, Pipeline $pipeline): JsonResponse
    {
        $this->authorize('viewAny', Deal::class);

        $user = $request->user();
        $stages = $pipeline->stages()->get();

        $totals = Deal::query()
            ->visibleTo($user)
            ->where('pipeline_id', $pipeline->id)
            ->selectRaw('stage_id, COUNT(*) as deals_count, SUM(amount) as total_amount, SUM(amount * probability / 100) as weighted_amount')
            ->groupBy('stage_id')
            ->get()
            ->keyBy('stage_id');

        $data = $stages->map(function ($stage) use ($user, $pipeline, $totals) {
            $deals = Deal::query()
                ->visibleTo($user)
                ->where('stage_id', $stage->id)
                ->with(['contact:id,first_name,last_name', 'company:id,name', 'owner:id,name'])
                ->orderBy('position')
                ->limit(self::DEALS_PER_STAGE)
                ->get();

            $t = $totals->get($stage->id);

            return [
                'id' => $stage->id,
                'name' => $stage->name,
                'type' => $stage->type->value,
                'probability' => $stage->probability,
                'color' => $stage->color,
                'deals_count' => (int) ($t->deals_count ?? 0),
                'total_amount' => (int) ($t->total_amount ?? 0),
                'weighted_amount' => (int) round($t->weighted_amount ?? 0),
                'deals' => DealResource::collection($deals)->resolve(),
            ];
        });

        return response()->json([
            'pipeline' => new PipelineResource($pipeline),
            'stages' => $data,
        ]);
    }
}