<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\Stripe\SubscriptionService;
use Illuminate\View\View;

/**
 * Unified pricing page — lists every active plan and offers a payment-method
 * picker per card. The picker filters available methods by billing_type:
 *
 *   recurring  →  Cashier (Combo 1a)
 *   one_time   →  Payment Intents (1b) | Checkout (2a) | Elements (2b)
 */
class PricingController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function index(): View
    {
        $plans = Plan::active()->orderBy('billing_type')->orderBy('amount_cents')->get();

        return view('pricing.index', [
            'plans'              => $plans,
            'currentSubscription' => $this->subscriptions->currentSubscription(auth()->user()),
        ]);
    }

}
