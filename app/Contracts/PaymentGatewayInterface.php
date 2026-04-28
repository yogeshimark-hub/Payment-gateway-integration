<?php

namespace App\Contracts;

use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\PaymentIntent;

/**
 * Abstraction over a payment gateway.
 *
 * Concrete implementation today: StripePaymentGateway.
 * Tomorrow you can write RazorpayPaymentGateway, PayPalPaymentGateway, etc.,
 * and swap the binding in PaymentServiceProvider — no controller code changes.
 */
interface PaymentGatewayInterface
{
    /**
     * Create a one-time PaymentIntent (used by Combos 1b + 2b).
     *
     * @param  int     $amountCents  amount in smallest currency unit
     * @param  string  $currency     ISO 4217 (e.g. 'usd')
     * @param  array   $metadata     attached to the intent — e.g. ['order_uuid' => ...]
     */
    public function createPaymentIntent(int $amountCents, string $currency, array $metadata = []): PaymentIntent;

    public function retrievePaymentIntent(string $id): PaymentIntent;

    /**
     * Create a hosted Stripe Checkout Session (Combo 2a).
     */
    public function createCheckoutSession(array $params): Session;

    /**
     * Verify the Stripe-Signature header and return the parsed Event.
     * Throws Stripe\Exception\SignatureVerificationException if invalid.
     */
    public function constructWebhookEvent(string $payload, string $signatureHeader): Event;
}
