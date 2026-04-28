<?php

namespace App\Services\Stripe;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderFailed;
use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Maps Stripe event types to side effects on the database.
 *
 * Called by WebhookController AFTER:
 *   - signature verification has passed
 *   - the event has been recorded in webhook_events (idempotency)
 *
 * Each method is wrapped in DB::transaction so order + payment writes
 * are atomic and the dispatched domain event sees committed data.
 */
class WebhookEventHandler
{
    public function handlePaymentIntentSucceeded(array $payload): void
    {
        $intent = $payload['data']['object'];

        $order = Order::where('stripe_payment_intent_id', $intent['id'])->first();

        if (! $order) {
            Log::warning('Webhook: payment_intent.succeeded for unknown order', [
                'payment_intent' => $intent['id'],
            ]);
            return;
        }

        if ($order->isPaid()) {
            Log::info('Webhook: order already paid, skipping', ['order_uuid' => $order->uuid]);
            return;
        }

        DB::transaction(function () use ($order, $intent) {
            $order->markAsPaid($intent['id']);

            Payment::create([
                'order_id'                 => $order->id,
                'stripe_payment_intent_id' => $intent['id'],
                'status'                   => PaymentStatus::Succeeded,
                'amount_cents'             => $intent['amount_received'] ?? $intent['amount'],
                'currency'                 => strtoupper($intent['currency']),
                'payment_method_type'      => $intent['payment_method_types'][0] ?? null,
                'last_four'                => $this->extractLastFour($intent),
                'processed_at'             => now(),
            ]);
        });

        OrderPaid::dispatch($order->fresh());
    }

    public function handlePaymentIntentFailed(array $payload): void
    {
        $intent = $payload['data']['object'];

        $order = Order::where('stripe_payment_intent_id', $intent['id'])->first();

        if (! $order) {
            Log::warning('Webhook: payment_intent.payment_failed for unknown order', [
                'payment_intent' => $intent['id'],
            ]);
            return;
        }

        $error = $intent['last_payment_error'] ?? [];

        DB::transaction(function () use ($order, $intent, $error) {
            $order->markAsFailed();

            Payment::create([
                'order_id'                 => $order->id,
                'stripe_payment_intent_id' => $intent['id'],
                'status'                   => PaymentStatus::Failed,
                'amount_cents'             => $intent['amount'],
                'currency'                 => strtoupper($intent['currency']),
                'failure_code'             => $error['code'] ?? null,
                'failure_message'          => $error['message'] ?? null,
                'processed_at'             => now(),
            ]);
        });

        OrderFailed::dispatch($order->fresh(), $error['message'] ?? null);
    }

    public function handleCheckoutSessionCompleted(array $payload): void
    {
        $session = $payload['data']['object'];

        $order = Order::where('stripe_checkout_session_id', $session['id'])->first();

        if (! $order) {
            Log::warning('Webhook: checkout.session.completed for unknown order', [
                'session' => $session['id'],
            ]);
            return;
        }

        if ($order->isPaid()) {
            return;
        }

        // Checkout session itself does not carry payment_status='paid' for all flows;
        // require it explicitly so we don't mark unpaid sessions as paid.
        if (($session['payment_status'] ?? null) !== 'paid') {
            Log::info('Webhook: checkout session not paid yet', [
                'session'        => $session['id'],
                'payment_status' => $session['payment_status'] ?? 'unknown',
            ]);
            return;
        }

        DB::transaction(function () use ($order, $session) {
            $order->update([
                'stripe_payment_intent_id' => $session['payment_intent'] ?? $order->stripe_payment_intent_id,
            ]);
            $order->markAsPaid($session['payment_intent'] ?? null);

            Payment::create([
                'order_id'                 => $order->id,
                'stripe_payment_intent_id' => $session['payment_intent'] ?? '',
                'status'                   => PaymentStatus::Succeeded,
                'amount_cents'             => $session['amount_total'],
                'currency'                 => strtoupper($session['currency']),
                'payment_method_type'      => $session['payment_method_types'][0] ?? null,
                'processed_at'             => now(),
            ]);
        });

        OrderPaid::dispatch($order->fresh());
    }

    private function extractLastFour(array $intent): ?string
    {
        return $intent['charges']['data'][0]['payment_method_details']['card']['last4']
            ?? null;
    }
}
