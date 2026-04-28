<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // 'recurring' | 'one_time' — added with default so existing rows are recurring
            $table->string('billing_type')->default('recurring')->after('currency');
            $table->string('stripe_product_id')->nullable()->after('stripe_price_id');

            // interval was string NOT NULL; one-time plans have no interval, so allow null
            $table->string('interval')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['billing_type', 'stripe_product_id']);
            // Note: not reverting interval to NOT NULL — would fail if any one-time rows exist.
        });
    }
};
