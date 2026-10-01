<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DealController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('dashboard', DashboardController::class);

        // Static routes must be declared before the {id} resource routes.
        Route::get('contacts/options', [ContactController::class, 'options']);
        Route::apiResource('contacts', ContactController::class);

        Route::get('deals/pipeline', [DealController::class, 'pipeline']);
        Route::patch('deals/{deal}/stage', [DealController::class, 'changeStage']);
        Route::apiResource('deals', DealController::class);
    });
});
