<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'name',
        'unit_amount_cents',
        'quantity',
        'subtotal_cents',
    ];

    protected function casts(): array
    {
        return [
            'unit_amount_cents' => 'integer',
            'quantity'          => 'integer',
            'subtotal_cents'    => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function formattedSubtotal(): Attribute
    {
        return Attribute::get(fn () => number_format($this->subtotal_cents / 100, 2));
    }
}
