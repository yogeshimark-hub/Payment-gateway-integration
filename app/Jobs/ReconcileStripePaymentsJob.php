<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Stripe\StripePaymentGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Defence-in-depth: catch any Stripe events that the webhook missed.
 *
 * Lists Stripe payment intents from the last 24h and flags any that succeeded
 * in Stripe but are NOT marked paid in the DB. Logs warnings; intentionally
 * does NOT auto-fix — alerting + human review is safer for money operations.
 *
 * Scheduled daily in routes/console.php.
 */
class ReconcileStripePaymentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 1;

    public function handle(StripePaymentGateway $gateway): void
    {
        $since = now()->subDay()->timestamp;

        try {
            $intents = $gateway->client()->paymentIntents->all([
                'created' => ['gte' => $since],
                'limit'   => 100,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Reconcile] Failed to list Stripe PaymentIntents', ['error' => $e->getMessage()]);
            return;
        }

        $checked    = 0;
        $missing    = 0;
        $stale      = 0;

        foreach ($intents->data as $intent) {
            $checked++;

            if ($intent->status !== 'succeeded') {
                continue;
            }

            $order = Order::where('stripe_payment_intent_id', $intent->id)->first();

            if (! $order) {
                $missing++;
                Log::warning('[Reconcile] Stripe PI succeeded but no Order in DB', [
                    'payment_intent' => $intent->id,
                    'amount'         => $intent->amount,
                    'created'        => $intent->created,
                ]);
                continue;
            }

            if (! $order->isPaid()) {
                $stale++;
                Log::warning('[Reconcile] Stripe says paid but DB does not', [
                    'order_uuid'     => $order->uuid,
                    'payment_intent' => $intent->id,
                    'db_status'      => $order->status->value,
                ]);
            }
        }

        Log::info('[Reconcile] Stripe payments check complete', compact('checked', 'missing', 'stale'));
    }
}
