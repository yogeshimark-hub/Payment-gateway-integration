<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 8b: admin can create plans before Stripe sync exists (Step 8c).
 * Allow stripe_price_id to be NULL on creation; the unique constraint stays
 * (NULL != NULL in SQL, so multiple null rows are fine).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('stripe_price_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // no-op — would fail if any null rows exist
    }
};
