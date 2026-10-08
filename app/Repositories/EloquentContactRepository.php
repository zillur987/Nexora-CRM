<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ContactRepository;
use App\Models\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentContactRepository implements ContactRepository
{
    private const RELATIONS = ['owner:id,name', 'company:id,name', 'industry:id,name', 'source:id,name', 'stage:id,name'];

    private const OPTIONS_LIMIT = 500;

    public function paginate(array $filters): LengthAwarePaginator
    {
        return Contact::query()
            ->with(self::RELATIONS)
            ->when($filters['contact_stage_id'] ?? null, fn ($q, $id) => $q->where('contact_stage_id', $id))
            ->when($filters['contact_source_id'] ?? null, fn ($q, $id) => $q->where('contact_source_id', $id))
            ->when($filters['industry_id'] ?? null, fn ($q, $id) => $q->where('industry_id', $id))
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->when($filters['country'] ?? null, fn ($q, $country) => $q->where('present_country', $country))
            ->when($filters['owner_id'] ?? null, function ($q, string $owner) {
                $owner === 'unassigned'
                    ? $q->whereNull('owner_id')
                    : $q->where('owner_id', (int) $owner);
            })
            ->when($filters['search'] ?? null, function ($q, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) like ?", [$like])
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('job_title', 'like', $like)
                    ->orWhereHas('company', fn ($c) => $c->where('name', 'like', $like)));
            })
            ->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'desc')
            ->orderBy('id') // deterministic order for ties, keeps pagination stable
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();
    }

    public function create(array $data): Contact
    {
        return $this->hydrate(Contact::create($data));
    }

    public function update(Contact $contact, array $data): Contact
    {
        $contact->update($data);

        return $this->hydrate($contact);
    }

    public function delete(Contact $contact): void
    {
        $contact->delete(); // soft delete
    }

    public function countByStage(): Collection
    {
        return Contact::query()
            ->selectRaw('contact_stage_id, COUNT(*) as aggregate')
            ->groupBy('contact_stage_id')
            ->pluck('aggregate', 'contact_stage_id');
    }

    public function options(): Collection
    {
        return Contact::query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(self::OPTIONS_LIMIT)
            ->get(['id', 'first_name', 'last_name']);
    }

    private function hydrate(Contact $contact): Contact
    {
        return $contact->load(self::RELATIONS);
    }
}
