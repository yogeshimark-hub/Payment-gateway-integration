<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Practice\BoundGreetingService;
use App\Services\Practice\SimpleGreetingService;
use App\Contracts\HelpingInterface;
use App\Services\Practice\ServiceWithInterface;

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
        $this->app->bind('helping-facade', function() { return new ServiceWithInterface();});

        $this->app->bind(HelpingInterface::class, ServiceWithInterface::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
