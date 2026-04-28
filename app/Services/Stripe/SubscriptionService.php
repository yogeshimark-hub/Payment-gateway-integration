<?php

namespace App\Services\Stripe;

use App\Models\Plan;
use App\Models\User;
use Laravel\Cashier\Checkout;
use Laravel\Cashier\Subscription;

/**
 * Wraps Cashier's Billable trait so controllers don't speak Stripe directly.
 *
 * Cashier already handles all DB sync (subscriptions, subscription_items)
 * via webhook handlers in its parent WebhookController — which we extended
 * in Step 2. We only need to expose the *initiation* + *management* methods.
 */
class SubscriptionService
{
    public const DEFAULT_NAME = 'default';

    /**
     * Start a Stripe-hosted subscription checkout for the given plan.
     *
     * Returns a Cashier Checkout object — the controller redirects to ->url.
     */
    public function checkout(User $user, Plan $plan, string $name = self::DEFAULT_NAME): Checkout
    {
        return $user
            ->newSubscription($name, $plan->stripe_price_id)
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => route('subscriptions.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => route('subscriptions.cancel'),
                'metadata'    => [
                    'plan_id'   => $plan->id,
                    'plan_slug' => $plan->slug,
                ],
                'subscription_data' => [
                    'metadata' => [
                        'plan_id'   => $plan->id,
                        'plan_slug' => $plan->slug,
                    ],
                ],
            ]);
    }

    public function currentSubscription(User $user, string $name = self::DEFAULT_NAME): ?Subscription
    {
        return $user->subscription($name);
    }

    public function isSubscribed(User $user, string $name = self::DEFAULT_NAME): bool
    {
        return $user->subscribed($name);
    }

    /**
     * Cancel at the end of the current period — user keeps access until grace ends.
     */
    public function cancelAtPeriodEnd(User $user, string $name = self::DEFAULT_NAME): void
    {
        $sub = $user->subscription($name);
        if ($sub && ! $sub->canceled()) {
            $sub->cancel();
        }
    }

    public function resume(User $user, string $name = self::DEFAULT_NAME): void
    {
        $sub = $user->subscription($name);
        if ($sub?->onGracePeriod()) {
            $sub->resume();
        }
    }
}
