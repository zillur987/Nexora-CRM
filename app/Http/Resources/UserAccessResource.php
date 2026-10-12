<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserAccessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $roles = $this->relationLoaded('roles') ? $this->roles : collect();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug])->values(),
            'role_ids' => $roles->pluck('id')->values(),
            'is_super_admin' => $roles->contains(fn ($r) => $r->slug === config('permissions.super_admin_role')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
