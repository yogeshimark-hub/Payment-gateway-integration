<?php

namespace App\Services\Stripe;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Backend half of Combo 1b — custom one-time payment.
 *
 * Creates the Order row first (so we own a UUID) then creates the Stripe
 * PaymentIntent with that UUID in metadata. Both writes are atomic.
 *
 * Note: the same service powers Combo 2b (Stripe Elements) — only the
 * frontend differs.
 */
class PaymentIntentService
{
    public function __construct(private PaymentGatewayInterface $gateway) {}

    /**
     * @return array{order: Order, client_secret: string}
     */
    public function create(
        User $user,
        int $amountCents,
        string $currency = 'USD',
        OrderType $type = OrderType::PaymentIntent,
        array $metadata = [],
    ): array {
        return DB::transaction(function () use ($user, $amountCents, $currency, $type, $metadata) {
            $order = Order::create([
                'user_id'      => $user->id,
                'type'         => $type,
                'status'       => OrderStatus::Pending,
                'amount_cents' => $amountCents,
                'currency'     => strtoupper($currency),
                'metadata'     => $metadata,
            ]);

            $intent = $this->gateway->createPaymentIntent(
                $amountCents,
                $currency,
                array_merge($metadata, [
                    'order_uuid' => $order->uuid,
                    'user_id'    => (string) $user->id,
                    'order_type' => $type->value,
                ]),
            );

            $order->update(['stripe_payment_intent_id' => $intent->id]);

            return [
                'order'         => $order->fresh(),
                'client_secret' => $intent->client_secret,
            ];
        });
    }
}
