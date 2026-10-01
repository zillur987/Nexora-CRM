<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ContactRepository;
use App\Exceptions\BusinessRuleException;
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
        if ($this->contacts->hasOpenDeals($contact)) {
            throw new BusinessRuleException('Cannot delete a contact that has open deals.');
        }

        $this->contacts->delete($contact);
    }

    /** @return Collection<int, Contact> */
    public function options(): Collection
    {
        return $this->contacts->options();
    }
}
