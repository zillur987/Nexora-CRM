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

        // ---------------------------------------------------------------------
        // Permission guards. Every route below needs the matching "<module>.<action>".
        // ---------------------------------------------------------------------

        // Guards a standard resource: view = index + show, create = store, edit = update, delete = destroy.
        // $alsoView lists other permissions that may read it too (e.g. deal forms read pipelines).
        $resource = function (string $uri, string $controller, string $module, string $alsoView = ''): void {
            $view = "permission:{$module}.view".($alsoView !== '' ? ",{$alsoView}" : '');

            Route::apiResource($uri, $controller)->only(['index', 'show'])->middleware($view);
            Route::apiResource($uri, $controller)->only(['store'])->middleware("permission:{$module}.create");
            Route::apiResource($uri, $controller)->only(['update'])->middleware("permission:{$module}.edit");
            Route::apiResource($uri, $controller)->only(['destroy'])->middleware("permission:{$module}.delete");
        };

        // Shared dropdown data (owners, companies, contacts...): anyone who works with CRM records may read it.
        $crmRead = 'permission:leads.view,contacts.view,companies.view,deals.view,quotes.view,tasks.view,products.view';

        Route::get('dashboard', DashboardController::class)->middleware('permission:dashboard.view');

        Route::get('users/options', UserOptionsController::class)->middleware($crmRead);

        // Leads
        Route::get('leads/summary', [LeadController::class, 'summary'])->middleware('permission:leads.view');
        Route::post('leads/import', [LeadController::class, 'import'])->middleware(['permission:leads.create', 'throttle:10,1']);
        Route::patch('leads/{lead}/status', [LeadController::class, 'changeStatus'])->middleware('permission:leads.edit');
        Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->middleware('permission:leads.edit');
        $resource('leads', LeadController::class, 'leads');

        // Companies (static routes first, then the {company} resource routes).
        Route::get('companies/summary', [CompanyController::class, 'summary'])->middleware('permission:companies.view');
        Route::get('companies/options', [CompanyController::class, 'options'])->middleware($crmRead);
        $resource('companies', CompanyController::class, 'companies');
        Route::get('company-lookups/{lookup}', [CompanyLookupController::class, 'index'])->middleware('permission:companies.view,companies.create,companies.edit');
        Route::post('company-lookups/{lookup}', [CompanyLookupController::class, 'store'])->middleware(['permission:companies.create,companies.edit', 'throttle:30,1']);

        // Contacts
        Route::get('contacts/summary', [ContactController::class, 'summary'])->middleware('permission:contacts.view');
        Route::get('contacts/options', [ContactController::class, 'options'])->middleware($crmRead);
        $resource('contacts', ContactController::class, 'contacts');
        Route::get('contact-lookups/{lookup}', [ContactLookupController::class, 'index'])->middleware('permission:contacts.view,contacts.create,contacts.edit');
        Route::post('contact-lookups/{lookup}', [ContactLookupController::class, 'store'])->middleware(['permission:contacts.create,contacts.edit', 'throttle:30,1']);

        // Deals
        Route::get('deals/summary', [DealController::class, 'summary'])->middleware('permission:deals.view');
        $resource('deals', DealController::class, 'deals');
        Route::get('deal-lookups/{lookup}', [DealLookupController::class, 'index'])->middleware('permission:deals.view,deals.create,deals.edit');
        Route::post('deal-lookups/{lookup}', [DealLookupController::class, 'store'])->middleware(['permission:deals.create,deals.edit', 'throttle:30,1']);

        // Pipelines (deal screens read them; changing a pipeline or its stages needs pipelines.*)
        $resource('pipelines', PipelineController::class, 'pipelines', 'deals.view,deals.create,deals.edit');
        Route::get('pipelines/{pipeline}/stages', [PipelineStageController::class, 'index'])->middleware('permission:pipelines.view,deals.view,deals.create,deals.edit');
        Route::post('pipelines/{pipeline}/stages', [PipelineStageController::class, 'store'])->middleware('permission:pipelines.edit');
        Route::patch('pipelines/{pipeline}/stages/{stage}', [PipelineStageController::class, 'update'])->middleware('permission:pipelines.edit');
        Route::delete('pipelines/{pipeline}/stages/{stage}', [PipelineStageController::class, 'destroy'])->middleware('permission:pipelines.edit');

        // Products (quote forms read them for line items)
        Route::get('products/lookups', [ProductController::class, 'lookups'])->middleware('permission:products.view,products.create,products.edit,quotes.create,quotes.edit')->name('products.lookups');
        $resource('products', ProductController::class, 'products', 'quotes.create,quotes.edit,deals.create,deals.edit');

        // Quotes
        Route::get('quotes/summary', [QuoteController::class, 'summary'])->middleware('permission:quotes.view')->name('quotes.summary');
        Route::post('quotes/{quote}/status', [QuoteController::class, 'updateStatus'])->middleware('permission:quotes.edit')->name('quotes.status');
        $resource('quotes', QuoteController::class, 'quotes');

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

        // Permission matrix (needed by the role form and the user access form)
        Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:roles.view,roles.create,roles.edit,users.view,users.edit')->name('permissions.index');

        // User access (assign roles to users)
        Route::get('access/users/summary', [UserAccessController::class, 'summary'])->middleware('permission:users.view')->name('access.users.summary');
        Route::get('access/users/lookups', [UserAccessController::class, 'lookups'])->middleware('permission:users.view')->name('access.users.lookups');
        Route::get('access/users', [UserAccessController::class, 'index'])->middleware('permission:users.view')->name('access.users.index');
        Route::get('access/users/{user}', [UserAccessController::class, 'show'])->middleware('permission:users.view')->name('access.users.show');
        Route::post('access/users', [UserAccessController::class, 'store'])->middleware('permission:users.create')->name('access.users.store');
        Route::put('access/users/{user}/roles', [UserAccessController::class, 'updateRoles'])->middleware('permission:users.edit')->name('access.users.roles');
        
    });

});
