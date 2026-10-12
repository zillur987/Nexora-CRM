<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MyAccessController extends Controller
{
    /** The signed-in user's roles and permissions; the Vue app loads this once after login. */
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json(['data' => [
            'is_super_admin' => $user->isSuperAdmin(),
            'roles' => $user->loadedRoles()->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug])->values(),
            'permissions' => $user->permissionNames()->values(),
        ]]);
    }
}
