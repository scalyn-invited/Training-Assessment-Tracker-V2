<?php

namespace App\Providers;

use App\Http\Middleware\TrainingSession;
use App\Services\Tenancy\ClientContext;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(ClientContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::addPersistentMiddleware([TrainingSession::class]);
    }
}
