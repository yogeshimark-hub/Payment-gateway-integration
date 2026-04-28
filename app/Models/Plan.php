<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\BillingType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'stripe_price_id',
        'stripe_product_id',
        'amount_cents',
        'currency',
        'billing_type',
        'interval',
        'interval_count',
        'features',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents'   => 'integer',
            'interval_count' => 'integer',
            'features'       => 'array',
            'is_active'      => 'boolean',
            'billing_type'   => BillingType::class,
            'interval'       => BillingInterval::class,
        ];
    }

    public function isRecurring(): bool
    {
        return $this->billing_type === BillingType::Recurring;
    }

    public function isOneTime(): bool
    {
        return $this->billing_type === BillingType::OneTime;
    }

    /**
     * True if the plan still needs to be created on Stripe (no IDs, or
     * placeholder IDs from the seeder that don't actually exist there).
     */
    public function needsStripeSync(): bool
    {
        $price = (string) $this->stripe_price_id;
        return $price === '' || str_contains($price, 'REPLACE_ME');
    }

    protected function formattedAmount(): Attribute
    {
        return Attribute::get(
            fn () => number_format($this->amount_cents / 100, 2) . ' ' . strtoupper($this->currency)
        );
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
