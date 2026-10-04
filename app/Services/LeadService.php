<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\LeadRepository;
use App\Enums\LeadStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Lead;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class LeadService
{
    public function __construct(private readonly LeadRepository $leads) {}

    /** @param array<string, mixed> $filters */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->leads->paginate($filters);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Lead
    {
        // Status is never client-controlled on create; every lead starts as New.
        return $this->leads->create([...$data, 'status' => LeadStatus::New]);
    }

    /** @param array<string, mixed> $data */
    public function update(Lead $lead, array $data): Lead
    {
        if ($lead->status->isConverted()) {
            throw new BusinessRuleException('A converted lead can no longer be edited.');
        }

        return $this->leads->update($lead, $data);
    }

    public function changeStatus(Lead $lead, LeadStatus $next): Lead
    {
        // Lock the row so two concurrent requests can't both pass the transition check.
        return DB::transaction(function () use ($lead, $next) {
            $current = $this->leads->findForUpdate($lead->getKey());

            if (! $current->status->canTransitionTo($next)) {
                throw new BusinessRuleException(
                    "Cannot move a lead from {$current->status->value} to {$next->value}.",
                    [
                        'from' => $current->status->value,
                        'to' => $next->value,
                        'allowed' => array_map(fn (LeadStatus $s) => $s->value, $current->status->allowedTransitions()),
                    ],
                );
            }

            return $this->leads->update($current, [
                'status' => $next,
                'last_contacted_at' => $next === LeadStatus::Contacted ? now() : $current->last_contacted_at,
            ]);
        });
    }

    public function delete(Lead $lead): void
    {
        if ($lead->status->isConverted()) {
            throw new BusinessRuleException('A converted lead cannot be deleted.');
        }

        $this->leads->delete($lead);
    }

    /**
     * Lead count per status (zero-filled, in lifecycle order) for the list tabs / dashboard.
     *
     * @return array<string, int>
     */
    public function summary(): array
    {
        $counts = $this->leads->countByStatus();
        $summary = [];

        foreach (LeadStatus::cases() as $status) {
            $summary[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $summary;
    }
}
