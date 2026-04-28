<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Idempotency log for Stripe webhooks.
 * One row = one Stripe event we have seen.
 * Unique constraint on stripe_event_id is the idempotency guarantee.
 */
class WebhookEvent extends Model
{
    protected $fillable = [
        'stripe_event_id',
        'type',
        'payload',
        'processed_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function isProcessed(): bool
    {
        return ! is_null($this->processed_at);
    }

    public function markProcessed(): void
    {
        $this->update(['processed_at' => now(), 'error' => null]);
    }

    public function markFailed(string $error): void
    {
        $this->update(['error' => $error]);
    }
}
