<?php

namespace Database\Seeders;

use App\Models\Price;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $course = Product::updateOrCreate(
            ['slug' => 'laravel-stripe-mastery'],
            [
                'name'        => 'Laravel + Stripe Mastery',
                'description' => 'Complete course on integrating Stripe with Laravel — Cashier, Payment Intents, Checkout, Elements.',
                'type'        => 'course',
                'is_active'   => true,
            ],
        );

        Price::updateOrCreate(
            ['product_id' => $course->id, 'nickname' => 'Standard'],
            ['amount_cents' => 4900, 'currency' => 'USD', 'is_active' => true],
        );

        $ebook = Product::updateOrCreate(
            ['slug' => 'webhook-handbook'],
            [
                'name'        => 'The Stripe Webhook Handbook',
                'description' => 'Everything about idempotency, signature verification, and reliable DB sync.',
                'type'        => 'ebook',
                'is_active'   => true,
            ],
        );

        Price::updateOrCreate(
            ['product_id' => $ebook->id, 'nickname' => 'Standard'],
            ['amount_cents' => 1900, 'currency' => 'USD', 'is_active' => true],
        );

        $donation = Product::updateOrCreate(
            ['slug' => 'support-the-project'],
            [
                'name'        => 'Support the Project',
                'description' => 'Pay-what-you-want donation to support this open-source reference app.',
                'type'        => 'donation',
                'is_active'   => true,
            ],
        );
    }
}
