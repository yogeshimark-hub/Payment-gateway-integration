<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Product;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        return view('dashboard', [
            'plans'        => Plan::active()->get(),
            'products'     => Product::active()->with('activePrices')->get(),
            'orders'       => $user->orders()->latest()->take(5)->get(),
            'subscription' => $user->subscription('default'),
        ]);
    }
}
