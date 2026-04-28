<?php

namespace App\Http\Middleware;

use App\Enums\OrderStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate routes behind a completed one-time purchase.
 *
 * Usage:
 *   Route::middleware(['auth', 'paid'])->...           // any paid order
 *   Route::middleware(['auth', 'paid:checkout'])->...  // only checkout-type orders
 */
class EnsurePaymentCompleted
{
    public function handle(Request $request, Closure $next, ?string $orderType = null): Response
    {
        $user = $request->user();

        $hasPaid = $user
            ?->orders()
            ->where('status', OrderStatus::Paid)
            ->when($orderType, fn ($q) => $q->where('type', $orderType))
            ->exists();

        if (! $hasPaid) {
            return redirect()->route('dashboard')
                ->with('error', 'Please complete a payment to access this page.');
        }

        return $next($request);
    }
}
