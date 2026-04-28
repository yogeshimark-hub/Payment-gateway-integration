<?php

namespace App\Services\Stripe;

use App\Contracts\PaymentGatewayInterface;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripePaymentGateway implements PaymentGatewayInterface
{
    private ?StripeClient $stripe = null;

    public function __construct(
        private string $secretKey,
        private string $webhookSecret,
    ) {}

    public function createPaymentIntent(int $amountCents, string $currency, array $metadata = []): PaymentIntent
    {
        return $this->client()->paymentIntents->create([
            'amount'                    => $amountCents,
            'currency'                  => strtolower($currency),
            'automatic_payment_methods' => ['enabled' => true],
            'metadata'                  => $metadata,
        ]);
    }

    public function retrievePaymentIntent(string $id): PaymentIntent
    {
        return $this->client()->paymentIntents->retrieve($id);
    }

    public function createCheckoutSession(array $params): Session
    {
        return $this->client()->checkout->sessions->create($params);
    }

    public function constructWebhookEvent(string $payload, string $signatureHeader): Event
    {
        return Webhook::constructEvent($payload, $signatureHeader, $this->webhookSecret);
    }

    public function client(): StripeClient
    {
        if ($this->stripe === null) {
            if ($this->secretKey === '') {
                throw new \RuntimeException('STRIPE_SECRET is not set. Add it to your .env file.');
            }
            $this->stripe = new StripeClient($this->secretKey);
        }
        return $this->stripe;
    }
}
