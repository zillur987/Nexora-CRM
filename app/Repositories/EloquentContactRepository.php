<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ContactRepository;
use App\Enums\DealStage;
use App\Models\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentContactRepository implements ContactRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Contact::query()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, function ($q, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('company', 'like', $like));
            })
            ->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'desc')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();
    }

    public function create(array $data): Contact
    {
        return Contact::create($data);
    }

    public function update(Contact $contact, array $data): Contact
    {
        $contact->update($data);

        return $contact;
    }

    public function delete(Contact $contact): void
    {
        $contact->delete(); // soft delete
    }

    public function hasOpenDeals(Contact $contact): bool
    {
        return $contact->deals()->whereIn('stage', DealStage::openValues())->exists();
    }

    public function options(): Collection
    {
        return Contact::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'company']);
    }
}
