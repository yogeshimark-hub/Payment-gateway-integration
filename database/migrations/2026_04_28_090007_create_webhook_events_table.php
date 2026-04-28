<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency log for Stripe webhooks.
 * Every incoming Stripe event ID is stored here BEFORE side effects run.
 * Stripe retries on non-2xx — this table guarantees no double-processing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();      // IDEMPOTENCY KEY
            $table->string('type')->index();                  // 'payment_intent.succeeded' etc.
            $table->json('payload');                          // full event for audit/replay
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['type', 'processed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
