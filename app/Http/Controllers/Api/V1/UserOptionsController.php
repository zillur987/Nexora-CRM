<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/** Lightweight id/name list of users for "assigned to" dropdowns. */
class UserOptionsController extends Controller
{

    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => User::query()->orderBy('name')->get(['id', 'name'])]);
    }
}
