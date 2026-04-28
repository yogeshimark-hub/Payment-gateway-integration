<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'stripe_payment_intent_id',
        'status',
        'amount_cents',
        'currency',
        'payment_method_type',
        'last_four',
        'failure_code',
        'failure_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status'       => PaymentStatus::class,
            'amount_cents' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function formattedAmount(): Attribute
    {
        return Attribute::get(
            fn () => number_format($this->amount_cents / 100, 2) . ' ' . strtoupper($this->currency)
        );
    }
}
