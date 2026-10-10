<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\DealRepository;
use App\Enums\DealStageOutcome;
use App\Models\Deal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class DealService
{
    public function __construct(
        private readonly DealRepository $deals,
        private readonly DealLookupService $lookups,
    ) {}

    /** @param array<string, mixed> $filters */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->deals->paginate($filters);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Deal
    {
        return $this->deals->create($this->applyStageOutcome($data));
    }

    /** @param array<string, mixed> $data */
    public function update(Deal $deal, array $data): Deal
    {
        return $this->deals->update($deal, $this->applyStageOutcome($data, $deal));
    }

    public function delete(Deal $deal): void
    {
        $this->deals->delete($deal);
    }

    /**
     * Deal count per stage for the list tabs, plus pipeline value per currency for the KPI strip.
     *
     * @return array{total: int, by_stage: array<string, int>, totals: list<array<string, mixed>>}
     */
    public function summary(): array
    {
        $counts = $this->deals->countByStage();

        return [
            'total' => (int) $counts->sum(),
            'by_stage' => $counts
                ->reject(fn ($count, $stageId) => $stageId === '' || $stageId === null) // deals without a stage
                ->map(fn ($count) => (int) $count)
                ->all(),
            'totals' => $this->deals->totalsByCurrency()
                ->map(fn ($row) => [
                    'currency' => $row->currency,
                    'open_amount' => (float) $row->open_amount,
                    'weighted_amount' => round((float) $row->weighted_amount, 2),
                    'won_amount' => (float) $row->won_amount,
                    'open_count' => (int) $row->open_count,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Keeps a deal consistent with its pipeline stage whenever the stage changes:
     *  - won:  probability 100, close date stamped (today unless given), lost reason cleared
     *  - lost: probability 0, close date stamped (today unless given)
     *  - open: close date and lost reason cleared; probability falls back to the stage default
     *
     * Nothing is touched when the stage is not part of the change, so editing other fields of a
     * closed deal never rewrites its history.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function applyStageOutcome(array $data, ?Deal $deal = null): array
    {
        $stageId = $data['deal_stage_id'] ?? null;

        if ($stageId === null || ($deal !== null && (int) $stageId === (int) $deal->deal_stage_id)) {
            return $data;
        }

        $stage = $this->lookups->stage($stageId);

        if ($stage === null) {
            return $data;
        }

        $closedOn = $data['actual_close_date'] ?? $deal?->actual_close_date?->toDateString() ?? today()->toDateString();

        return match ($stage->outcome) {
            DealStageOutcome::Won => [...$data, 'probability' => 100, 'actual_close_date' => $closedOn, 'lost_reason' => null],
            DealStageOutcome::Lost => [...$data, 'probability' => 0, 'actual_close_date' => $closedOn],
            DealStageOutcome::Open => [
                ...$data,
                'probability' => $data['probability'] ?? $stage->probability,
                'actual_close_date' => null,
                'lost_reason' => null,
            ],
        };
    }
}
