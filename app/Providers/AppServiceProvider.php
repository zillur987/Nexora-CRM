<?php

namespace App\Providers;

use App\Contracts\Repositories\ContactRepository;
use App\Contracts\Repositories\DealRepository;
use App\Contracts\Repositories\LeadRepository;
use App\Contracts\Repositories\PipelineRepository;
use App\Contracts\Repositories\PipelineStageRepository;
use App\Models\Contact;
use App\Models\Deal;
use App\Policies\ContactPolicy;
use App\Policies\DealPolicy;
use App\Repositories\EloquentContactRepository;
use App\Repositories\EloquentDealRepository;
use App\Repositories\EloquentLeadRepository;
use App\Repositories\EloquentPipelineRepository;
use App\Repositories\EloquentPipelineStageRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Interface => implementation. Swap here (or in tests) without touching services. */
    public array $bindings = [
        ContactRepository::class => EloquentContactRepository::class,
        DealRepository::class => EloquentDealRepository::class,
        LeadRepository::class => EloquentLeadRepository::class,
        PipelineRepository::class => EloquentPipelineRepository::class,
        PipelineStageRepository::class => EloquentPipelineStageRepository::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(Deal::class, DealPolicy::class);

        Paginator::useBootstrapFive();

        // Fail loudly on N+1 queries and typos in attribute names during development.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
