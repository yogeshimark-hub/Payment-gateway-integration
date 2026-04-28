<?php

use App\Jobs\ReconcileStripePaymentsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily safety net: list recent Stripe PaymentIntents and flag any not in DB.
// Webhook is the primary sync; this catches the rare missed event.
Schedule::job(new ReconcileStripePaymentsJob)
    ->dailyAt('02:00')
    ->name('stripe-reconcile')
    ->withoutOverlapping();
