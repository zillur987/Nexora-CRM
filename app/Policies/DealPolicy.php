<?php // app/Policies/DealPolicy.php

namespace App\Policies;

use App\Models\Deal;
use App\Models\User;

class DealPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('deals.view');
    }

    public function view(User $user, Deal $deal): bool
    {
        return $user->can('deals.view_all') || $deal->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('deals.create');
    }

    public function update(User $user, Deal $deal): bool
    {
        return $user->can('deals.update_all')
            || ($user->can('deals.update') && $deal->owner_id === $user->id);
    }

    public function delete(User $user, Deal $deal): bool
    {
        return $user->can('deals.delete');
    }
}