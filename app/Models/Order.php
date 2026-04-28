<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'status',
        'amount_cents',
        'currency',
        'stripe_payment_intent_id',
        'stripe_checkout_session_id',
        'metadata',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status'       => OrderStatus::class,
            'type'         => OrderType::class,
            'amount_cents' => 'integer',
            'metadata'     => 'array',
            'paid_at'      => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment()
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    public function markAsPaid(?string $paymentIntentId = null): void
    {
        $this->update([
            'status'                   => OrderStatus::Paid,
            'paid_at'                  => now(),
            'stripe_payment_intent_id' => $paymentIntentId ?? $this->stripe_payment_intent_id,
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => OrderStatus::Failed]);
    }

    protected function formattedAmount(): Attribute
    {
        return Attribute::get(
            fn () => number_format($this->amount_cents / 100, 2) . ' ' . strtoupper($this->currency)
        );
    }
}
