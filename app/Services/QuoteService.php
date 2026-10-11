<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\QuoteRepository;
use App\Enums\QuoteStatus;
use App\Models\Quote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class QuoteService
{
    public function __construct(
        private readonly QuoteRepository $quotes,
        private readonly QuoteCalculator $calculator,
    ) {}

    /** @param array<string, mixed> $filters */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->quotes->paginate($filters);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Quote
    {
        return DB::transaction(function () use ($data) {
            ['items' => $items, 'totals' => $totals] = $this->calculator->calculate($data['items']);
            unset($data['items']);

            $data['status'] = $data['status'] ?? QuoteStatus::Draft->value;
            $data['quote_number'] = $this->nextNumber((string) $data['issue_date']);

            return $this->quotes->create([...$data, ...$totals, ...$this->stamps(null, $data['status'])], $items);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Quote $quote, array $data): Quote
    {
        return DB::transaction(function () use ($quote, $data) {
            $items = null;
            $totals = [];

            if (array_key_exists('items', $data)) {
                ['items' => $items, 'totals' => $totals] = $this->calculator->calculate($data['items']);
            }
            unset($data['items'], $data['quote_number']); // the number is issued once and never changes

            $stamps = isset($data['status']) ? $this->stamps($quote, $data['status']) : [];

            return $this->quotes->update($quote, [...$data, ...$totals, ...$stamps], $items);
        });
    }

    public function changeStatus(Quote $quote, string $status): Quote
    {
        return $this->quotes->update($quote, ['status' => $status, ...$this->stamps($quote, $status)], null);
    }

    public function delete(Quote $quote): void
    {
        $this->quotes->delete($quote);
    }

    /**
     * Quote count per status for the list tabs, plus open / accepted value per currency for the KPI strip.
     *
     * @return array{total: int, by_status: array<string, int>, acceptance_rate: float|null, totals: list<array<string, mixed>>}
     */
    public function summary(): array
    {
        $counts = $this->quotes->countByStatus()->map(fn ($count) => (int) $count);

        $accepted = (int) ($counts[QuoteStatus::Accepted->value] ?? 0);
        $decided = $accepted + (int) ($counts[QuoteStatus::Rejected->value] ?? 0);

        return [
            'total' => (int) $counts->sum(),
            'by_status' => $counts->all(),
            'acceptance_rate' => $decided > 0 ? round($accepted / $decided * 100, 1) : null,
            'totals' => $this->quotes->totalsByCurrency()
                ->map(fn ($row) => [
                    'currency' => $row->currency,
                    'open_amount' => (float) $row->open_amount,
                    'open_count' => (int) $row->open_count,
                    'accepted_amount' => (float) $row->accepted_amount,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Status timestamps. Nothing is touched when the status did not change, so editing other
     * fields of an accepted quote never rewrites its history.
     *
     * @return array<string, mixed>
     */
    private function stamps(?Quote $quote, string $status): array
    {
        if ($quote !== null && $quote->status->value === $status) {
            return [];
        }

        $now = now();

        return match (QuoteStatus::from($status)) {
            QuoteStatus::Draft => ['sent_at' => null, 'accepted_at' => null, 'rejected_at' => null],
            QuoteStatus::Sent => ['sent_at' => $quote?->sent_at ?? $now, 'accepted_at' => null, 'rejected_at' => null],
            QuoteStatus::Accepted => ['sent_at' => $quote?->sent_at ?? $now, 'accepted_at' => $now, 'rejected_at' => null],
            QuoteStatus::Rejected => ['sent_at' => $quote?->sent_at ?? $now, 'accepted_at' => null, 'rejected_at' => $now],
        };
    }

    /** Q-2026-0001, Q-2026-0002 ... restarting each year. Runs inside the create transaction (the lookup locks). */
    private function nextNumber(string $issueDate): string
    {
        $prefix = 'Q-'.substr($issueDate, 0, 4).'-';
        $last = $this->quotes->lastNumber($prefix);
        $next = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
