<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\CompanyLookupController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ContactLookupController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DealController;
use App\Http\Controllers\Api\V1\DealLookupController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\PipelineController;
use App\Http\Controllers\Api\V1\PipelineStageController;
use App\Http\Controllers\Api\V1\UserOptionsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('dashboard', DashboardController::class);

        Route::get('users/options', UserOptionsController::class);
        Route::get('leads/summary', [LeadController::class, 'summary']);
        Route::post('leads/import', [LeadController::class, 'import'])->middleware('throttle:10,1');
        Route::patch('leads/{lead}/status', [LeadController::class, 'changeStatus']);
        Route::post('leads/{lead}/convert', [LeadController::class, 'convert']);
        Route::apiResource('leads', LeadController::class);

        Route::get('pipelines/{pipeline}/board', [PipelineController::class, 'board']);
        Route::post('pipelines/{pipeline}/stages/reorder', [PipelineStageController::class, 'reorder']);
        Route::post('pipelines/{pipeline}/stages', [PipelineStageController::class, 'store']);
        Route::patch('pipelines/{pipeline}/stages/{stage}', [PipelineStageController::class, 'update']);
        Route::delete('pipelines/{pipeline}/stages/{stage}', [PipelineStageController::class, 'destroy']);
        Route::apiResource('pipelines', PipelineController::class);

         // Companies (static routes first, then the {company} resource routes).
        Route::get('companies/summary', [CompanyController::class, 'summary']);
        Route::get('companies/options', [CompanyController::class, 'options']);
        Route::apiResource('companies', CompanyController::class);
        Route::get('company-lookups/{lookup}', [CompanyLookupController::class, 'index']);
        Route::post('company-lookups/{lookup}', [CompanyLookupController::class, 'store'])->middleware('throttle:30,1');

        // Contacts
        Route::get('contacts/summary', [ContactController::class, 'summary']);
        Route::get('contacts/options', [ContactController::class, 'options']);
        Route::apiResource('contacts', ContactController::class);
        Route::get('contact-lookups/{lookup}', [ContactLookupController::class, 'index']);
        Route::post('contact-lookups/{lookup}', [ContactLookupController::class, 'store'])->middleware('throttle:30,1');

        // Deals
        Route::get('deals/summary', [DealController::class, 'summary']);
        Route::apiResource('deals', DealController::class);
        Route::get('deal-lookups/{lookup}', [DealLookupController::class, 'index']);
        Route::post('deal-lookups/{lookup}', [DealLookupController::class, 'store'])->middleware('throttle:30,1');
    });
});
