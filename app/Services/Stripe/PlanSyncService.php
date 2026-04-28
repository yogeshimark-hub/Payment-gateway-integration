<?php

namespace App\Services\Stripe;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Plan;

/**
 * Pushes admin plan changes into Stripe.
 *
 * Stripe constraint: Prices are immutable. Changing amount, currency, or
 * recurring interval means archiving the old Price and creating a new one.
 * Products are mutable (name, metadata, active flag).
 *
 * All methods are idempotent: safe to retry after a partial failure.
 */
class PlanSyncService
{
    public function __construct(private PaymentGatewayInterface $gateway) {}

    /**
     * Create a Stripe Product + Price for a brand-new plan.
     * If the plan already has a product/price, fills in whichever is missing.
     */
    public function syncOnCreate(Plan $plan): void
    {
        if (! $plan->stripe_product_id) {
            $product = $this->gateway->client()->products->create([
                'name'     => $plan->name,
                'active'   => $plan->is_active,
                'metadata' => ['plan_id' => $plan->id, 'plan_slug' => $plan->slug],
            ]);
            $plan->stripe_product_id = $product->id;
        }

        if (! $plan->stripe_price_id) {
            $price = $this->gateway->client()->prices->create($this->priceParams($plan));
            $plan->stripe_price_id = $price->id;
        }

        $plan->save();
    }

    /**
     * Reconcile Stripe with a changed plan.
     *
     * @param array $original  the plan's attributes BEFORE the DB update
     */
    public function syncOnUpdate(Plan $plan, array $original): void
    {
        // First-ever sync (legacy plan with no Stripe IDs) — treat as create.
        if (! $plan->stripe_product_id || ! $plan->stripe_price_id) {
            $this->syncOnCreate($plan);
            return;
        }

        // Product is always safe to update (name + active flag are mutable).
        $this->gateway->client()->products->update($plan->stripe_product_id, [
            'name'   => $plan->name,
            'active' => (bool) $plan->is_active,
        ]);

        // Did anything price-affecting change? Then we must rotate the price.
        if ($this->priceFieldsChanged($plan, $original)) {
            // Archive the old price (existing subscribers stay on it).
            $this->gateway->client()->prices->update($plan->stripe_price_id, ['active' => false]);

            // Create the new price.
            $newPrice = $this->gateway->client()->prices->create($this->priceParams($plan));
            $plan->stripe_price_id = $newPrice->id;
            $plan->save();
        }
    }

    /**
     * Mirror plan.is_active to Stripe Product + Price.
     */
    public function syncOnToggle(Plan $plan): void
    {
        if (! $plan->stripe_product_id || ! $plan->stripe_price_id) {
            return;
        }

        $active = (bool) $plan->is_active;
        $this->gateway->client()->products->update($plan->stripe_product_id, ['active' => $active]);
        $this->gateway->client()->prices->update($plan->stripe_price_id, ['active' => $active]);
    }

    /**
     * Archive product + price on Stripe. (Stripe doesn't allow real deletes
     * once a price has been used or even just created.)
     */
    public function syncOnDelete(Plan $plan): void
    {
        if ($plan->stripe_price_id) {
            $this->gateway->client()->prices->update($plan->stripe_price_id, ['active' => false]);
        }
        if ($plan->stripe_product_id) {
            $this->gateway->client()->products->update($plan->stripe_product_id, ['active' => false]);
        }
    }

    private function priceParams(Plan $plan): array
    {
        $params = [
            'product'     => $plan->stripe_product_id,
            'unit_amount' => $plan->amount_cents,
            'currency'    => strtolower($plan->currency),
            'active'      => (bool) $plan->is_active,
            'metadata'    => ['plan_id' => $plan->id, 'plan_slug' => $plan->slug],
        ];

        if ($plan->isRecurring()) {
            $params['recurring'] = [
                'interval'       => $plan->interval->value,
                'interval_count' => max(1, (int) $plan->interval_count),
            ];
        }

        return $params;
    }

    private function priceFieldsChanged(Plan $plan, array $original): bool
    {
        $originalInterval = $original['interval'] ?? null;
        if ($originalInterval instanceof \BackedEnum) {
            $originalInterval = $originalInterval->value;
        }

        $originalBillingType = $original['billing_type'] ?? null;
        if ($originalBillingType instanceof \BackedEnum) {
            $originalBillingType = $originalBillingType->value;
        }

        return (int) ($original['amount_cents'] ?? 0)   !== (int) $plan->amount_cents
            || strtoupper((string) ($original['currency'] ?? '')) !== strtoupper((string) $plan->currency)
            || (string) $originalBillingType            !== (string) $plan->billing_type->value
            || (string) $originalInterval              !== (string) ($plan->interval?->value ?? '')
            || (int) ($original['interval_count'] ?? 1) !== (int) $plan->interval_count;
    }
}
