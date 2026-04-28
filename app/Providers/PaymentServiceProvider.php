<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Services\Stripe\StripePaymentGateway;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayInterface::class, function () {
            return new StripePaymentGateway(
                secretKey:     (string) config('services.stripe.secret'),
                webhookSecret: (string) config('services.stripe.webhook_secret'),
            );
        });
    }

    public function boot(): void
    {
        // We register our own /stripe/webhook route in routes/web.php pointing
        // to our extended WebhookController. Disable Cashier's auto-route so
        // there's no conflict.
        Cashier::ignoreRoutes();
    }
}
