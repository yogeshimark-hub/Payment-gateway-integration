<?php

namespace App\Services\Stripe;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Price;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session;

/**
 * Backend half of Combo 2a — hosted Stripe Checkout.
 *
 * Creates an Order + OrderItem snapshot, then asks Stripe for a hosted
 * Checkout Session. The user is redirected to ${session.url}.
 *
 * Uses inline `price_data` instead of Stripe Price IDs — no need to
 * pre-create Stripe Products. Trade-off: line items aren't reportable
 * by product in the Stripe Dashboard. Acceptable for a reference app.
 */
class CheckoutService
{
    public function __construct(private PaymentGatewayInterface $gateway) {}

    public function start(User $user, Product $product, Price $price): Session
    {
        return DB::transaction(function () use ($user, $product, $price) {
            $order = Order::create([
                'user_id'      => $user->id,
                'type'         => OrderType::Checkout,
                'status'       => OrderStatus::Pending,
                'amount_cents' => $price->amount_cents,
                'currency'     => strtoupper($price->currency),
                'metadata'     => [
                    'product_slug' => $product->slug,
                    'price_id'     => $price->id,
                ],
            ]);

            $order->items()->create([
                'product_id'        => $product->id,
                'name'              => $product->name,
                'unit_amount_cents' => $price->amount_cents,
                'quantity'          => 1,
                'subtotal_cents'    => $price->amount_cents,
            ]);

            $session = $this->gateway->createCheckoutSession([
                'mode' => 'payment',
                'line_items' => [[
                    'price_data' => [
                        'currency'     => strtolower($price->currency),
                        'product_data' => [
                            'name'        => $product->name,
                            'description' => $product->description ?: null,
                        ],
                        'unit_amount' => $price->amount_cents,
                    ],
                    'quantity' => 1,
                ]],
                'success_url'    => route('payments.checkout.success', $order->uuid),
                'cancel_url'     => route('payments.checkout.cancel'),
                'customer_email' => $user->email,
                'metadata' => [
                    'order_uuid' => $order->uuid,
                    'user_id'    => (string) $user->id,
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'order_uuid' => $order->uuid,
                        'user_id'    => (string) $user->id,
                    ],
                ],
            ]);

            $order->update(['stripe_checkout_session_id' => $session->id]);

            return $session;
        });
    }

    /**
     * Plan-aware variant — same hosted Checkout flow, but the line item is
     * derived from a Plan (no Product/Price tables involved). Used for
     * one-time plans coming from the unified /plans page.
     *
     * Recurring plans go through Cashier (SubscriptionService), not here.
     */
    public function startForPlan(User $user, Plan $plan): Session
    {
        return DB::transaction(function () use ($user, $plan) {
            $order = Order::create([
                'user_id'      => $user->id,
                'type'         => OrderType::Checkout,
                'status'       => OrderStatus::Pending,
                'amount_cents' => $plan->amount_cents,
                'currency'     => strtoupper($plan->currency),
                'metadata'     => [
                    'plan_id'   => $plan->id,
                    'plan_slug' => $plan->slug,
                ],
            ]);

            $session = $this->gateway->createCheckoutSession([
                'mode' => 'payment',
                'line_items' => [[
                    'price_data' => [
                        'currency'     => strtolower($plan->currency),
                        'product_data' => ['name' => $plan->name],
                        'unit_amount'  => $plan->amount_cents,
                    ],
                    'quantity' => 1,
                ]],
                'success_url'    => route('payments.checkout.success', $order->uuid),
                'cancel_url'     => route('payments.checkout.cancel'),
                'customer_email' => $user->email,
                'metadata' => [
                    'order_uuid' => $order->uuid,
                    'user_id'    => (string) $user->id,
                    'plan_id'    => (string) $plan->id,
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'order_uuid' => $order->uuid,
                        'user_id'    => (string) $user->id,
                        'plan_id'    => (string) $plan->id,
                    ],
                ],
            ]);

            $order->update(['stripe_checkout_session_id' => $session->id]);

            return $session;
        });
    }
}
