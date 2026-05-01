<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Practice\BoundGreetingService;
use App\Services\Practice\SimpleGreetingService;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // $this->app->bind(BoundGreetingService::class, function ($app) {
        //       return new BoundGreetingService();
        //   });
        $this->app->bind('Wishing', function() { return new BoundGreetingService();});
        $this->app->bind('Wis', function() { return new SimpleGreetingService();});
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
