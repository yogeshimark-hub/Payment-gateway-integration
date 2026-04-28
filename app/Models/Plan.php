<?php

namespace App\Models;

use App\Enums\BillingInterval;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'stripe_price_id',
        'amount_cents',
        'currency',
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
            'interval'       => BillingInterval::class,
        ];
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
