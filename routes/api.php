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
use App\Http\Controllers\Api\V1\MyAccessController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\PipelineController;
use App\Http\Controllers\Api\V1\PipelineStageController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\UserAccessController;
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

        Route::apiResource('pipelines', PipelineController::class);
        Route::get('pipelines/{pipeline}/stages', [PipelineStageController::class, 'index']);
        Route::post('pipelines/{pipeline}/stages', [PipelineStageController::class, 'store']);
        Route::patch('pipelines/{pipeline}/stages/{stage}', [PipelineStageController::class, 'update']);
        Route::delete('pipelines/{pipeline}/stages/{stage}', [PipelineStageController::class, 'destroy']);

        Route::get('products/lookups', [ProductController::class, 'lookups'])->name('products.lookups');
        Route::apiResource('products', ProductController::class);

        // Quotes
        Route::get('quotes/summary', [QuoteController::class, 'summary'])->name('quotes.summary');
        Route::post('quotes/{quote}/status', [QuoteController::class, 'updateStatus'])->name('quotes.status');
        Route::apiResource('quotes', QuoteController::class);

        Route::get('tasks/lookups', [TaskController::class, 'lookups'])->middleware('permission:tasks.view,tasks.create,tasks.edit')->name('tasks.lookups');
        Route::get('tasks/summary', [TaskController::class, 'summary'])->middleware('permission:tasks.view')->name('tasks.summary');
        Route::get('tasks', [TaskController::class, 'index'])->middleware('permission:tasks.view')->name('tasks.index');
        Route::post('tasks', [TaskController::class, 'store'])->middleware('permission:tasks.create')->name('tasks.store');
        Route::get('tasks/{task}', [TaskController::class, 'show'])->middleware('permission:tasks.view')->name('tasks.show');
        Route::patch('tasks/{task}', [TaskController::class, 'update'])->middleware('permission:tasks.edit')->name('tasks.update');
        Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->middleware('permission:tasks.delete')->name('tasks.destroy');

        // "+" beside Task Type / Priority / Status
        Route::post('task-options/{kind}', [TaskController::class, 'storeOption'])
            ->whereIn('kind', ['type', 'priority', 'status'])
            ->middleware('permission:tasks.create,tasks.edit')
            ->name('tasks.options.store');

        // Current user's access (no permission needed: the SPA needs it to know what to show).
        Route::get('auth/permissions', [MyAccessController::class, 'show'])->name('auth.permissions');

        // Roles
        Route::get('roles/summary', [RoleController::class, 'summary'])->middleware('permission:roles.view')->name('roles.summary');
        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
        Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.view')->name('roles.show');
        Route::patch('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit')->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');

        // Permission matrix (needed by the role form)
        Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:roles.view,roles.create,roles.edit')->name('permissions.index');

        // User access (assign roles to users)
        Route::get('access/users/summary', [UserAccessController::class, 'summary'])->middleware('permission:users.view')->name('access.users.summary');
        Route::get('access/users/lookups', [UserAccessController::class, 'lookups'])->middleware('permission:users.view')->name('access.users.lookups');
        Route::get('access/users', [UserAccessController::class, 'index'])->middleware('permission:users.view')->name('access.users.index');
        Route::get('access/users/{user}', [UserAccessController::class, 'show'])->middleware('permission:users.view')->name('access.users.show');
        Route::put('access/users/{user}/roles', [UserAccessController::class, 'updateRoles'])->middleware('permission:users.edit')->name('access.users.roles');
    });

});
