<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ContactRepository
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator;

    /** @param array<string, mixed> $data */
    public function create(array $data): Contact;

    /** @param array<string, mixed> $data */
    public function update(Contact $contact, array $data): Contact;

    public function delete(Contact $contact): void;

    /** @return Collection<string|int, int|string> contact_stage_id => number of contacts */
    public function countByStage(): Collection;

    /** id + first/last name for <select> dropdowns. @return Collection<int, Contact> */
    public function options(): Collection;
}
