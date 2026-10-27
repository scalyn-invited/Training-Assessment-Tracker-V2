<?php

namespace App\Providers;

use App\Http\Middleware\TrainingSession;
use App\Services\Branding;
use App\Services\Tenancy\ClientContext;
use Illuminate\Support\Facades\View;
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

        View::composer(['layouts.app', 'appearance'], function ($view) {
            $view->with('branding', app(Branding::class)->forUser(request()->routeIs('clients.*', 'login', 'login.store') ? null : auth()->user()));
        });
    }
}
