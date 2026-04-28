<?php

namespace App\Enums;

/**
 * Identifies WHICH Stripe integration created the order.
 * Combo 1a (Cashier subscriptions) does not use orders — it uses subscriptions.
 */
enum OrderType: string
{
    case PaymentIntent = 'payment_intent';   // Combo 1b — custom one-time
    case Checkout      = 'checkout';         // Combo 2a — hosted Stripe Checkout
    case Elements      = 'elements';         // Combo 2b — custom branded UI
}
