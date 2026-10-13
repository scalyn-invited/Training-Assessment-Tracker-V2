<?php

namespace App\Providers;

use App\Http\Middleware\TrainingSession;
use App\Services\Branding;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::addPersistentMiddleware([TrainingSession::class]);
        View::composer(['layouts.app', 'appearance'], function ($view) {
            $view->with('branding', app(Branding::class)->forUser(auth()->user()));
        });
    }
}
