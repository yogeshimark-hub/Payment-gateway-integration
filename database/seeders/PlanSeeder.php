<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'            => 'Starter Monthly',
                'slug'            => 'starter-monthly',
                'stripe_price_id' => 'price_starter_monthly_REPLACE_ME',
                'amount_cents'    => 999,
                'currency'        => 'USD',
                'interval'        => 'month',
                'interval_count'  => 1,
                'features'        => ['5 projects', 'Email support', '1 GB storage'],
                'is_active'       => true,
            ],
            [
                'name'            => 'Pro Monthly',
                'slug'            => 'pro-monthly',
                'stripe_price_id' => 'price_pro_monthly_REPLACE_ME',
                'amount_cents'    => 2999,
                'currency'        => 'USD',
                'interval'        => 'month',
                'interval_count'  => 1,
                'features'        => ['Unlimited projects', 'Priority support', '100 GB storage', 'Advanced analytics'],
                'is_active'       => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
