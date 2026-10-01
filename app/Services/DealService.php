<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\DealRepository;
use App\Enums\DealStage;
use App\Exceptions\BusinessRuleException;
use App\Models\Deal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class DealService
{
    public function __construct(private readonly DealRepository $deals) {}

    /** @param array<string, mixed> $filters */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->deals->paginate($filters);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Deal
    {
        return $this->deals->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Deal $deal, array $data): Deal
    {
        if ($deal->stage->isClosed()) {
            throw new BusinessRuleException("A {$deal->stage->value} deal can no longer be edited.");
        }

        return $this->deals->update($deal, $data);
    }

    public function changeStage(Deal $deal, DealStage $next): Deal
    {
        // Lock the row so two concurrent requests can't both pass the transition check.
        return DB::transaction(function () use ($deal, $next) {
            $current = $this->deals->findForUpdate($deal->getKey());

            if (! $current->stage->canTransitionTo($next)) {
                throw new BusinessRuleException(
                    "Cannot move a deal from {$current->stage->value} to {$next->value}.",
                    [
                        'from' => $current->stage->value,
                        'to' => $next->value,
                        'allowed' => array_map(fn (DealStage $s) => $s->value, $current->stage->allowedTransitions()),
                    ],
                );
            }

            return $this->deals->update($current, [
                'stage' => $next,
                'closed_at' => $next->isClosed() ? now() : null,
            ]);
        });
    }

    public function delete(Deal $deal): void
    {
        if ($deal->stage === DealStage::Won) {
            throw new BusinessRuleException('A won deal cannot be deleted.');
        }

        $this->deals->delete($deal);
    }

    /** @return list<array{stage: string, currency: string, count: int, total_amount: string}> */
    public function pipeline(): array
    {
        return $this->deals->pipelineSummary()
            ->map(fn (Deal $row) => [
                'stage' => $row->stage->value,
                'currency' => $row->currency,
                'count' => (int) $row->getAttribute('deals_count'),
                'total_amount' => number_format((float) $row->getAttribute('total_amount'), 2, '.', ''),
            ])
            ->values()
            ->all();
    }
}
