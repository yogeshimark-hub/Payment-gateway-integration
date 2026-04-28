<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Stripe webhook never has CSRF — Stripe signs with STRIPE_WEBHOOK_SECRET instead.
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);

        $middleware->alias([
            'paid'       => \App\Http\Middleware\EnsurePaymentCompleted::class,
            'subscribed' => \App\Http\Middleware\EnsureSubscribed::class,
            'admin'      => \App\Http\Middleware\EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
