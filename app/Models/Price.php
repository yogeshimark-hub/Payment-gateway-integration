<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Price extends Model
{
    protected $fillable = [
        'product_id',
        'stripe_price_id',
        'nickname',
        'amount_cents',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'is_active'    => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function formattedAmount(): Attribute
    {
        return Attribute::get(
            fn () => number_format($this->amount_cents / 100, 2) . ' ' . strtoupper($this->currency)
        );
    }
}
