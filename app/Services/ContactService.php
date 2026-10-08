<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ContactRepository;
use App\Models\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ContactService
{
    public function __construct(private readonly ContactRepository $contacts) {}

    /** @param array<string, mixed> $filters */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->contacts->paginate($filters);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Contact
    {
        return $this->contacts->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Contact $contact, array $data): Contact
    {
        return $this->contacts->update($contact, $data);
    }

    public function delete(Contact $contact): void
    {
        $this->contacts->delete($contact);
    }

    /**
     * Contact count per stage for the list tabs.
     *
     * @return array{total: int, by_stage: array<string, int>}
     */
    public function summary(): array
    {
        $counts = $this->contacts->countByStage();

        return [
            'total' => (int) $counts->sum(),
            'by_stage' => $counts
                ->reject(fn ($count, $stageId) => $stageId === '' || $stageId === null) // contacts without a stage
                ->map(fn ($count) => (int) $count)
                ->all(),
        ];
    }

    /** @return Collection<int, array{id: int, name: string}> */
    public function options(): Collection
    {
        return $this->contacts->options()
            ->map(fn (Contact $contact) => ['id' => $contact->id, 'name' => $contact->full_name])
            ->values();
    }
}
