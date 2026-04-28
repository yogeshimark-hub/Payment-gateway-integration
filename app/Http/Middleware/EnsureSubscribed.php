<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate routes behind an active Cashier subscription.
 *
 * Usage:
 *   Route::middleware(['auth', 'subscribed'])->...        // checks 'default' subscription
 *   Route::middleware(['auth', 'subscribed:pro'])->...    // checks 'pro' subscription type
 */
class EnsureSubscribed
{
    public function handle(Request $request, Closure $next, string $name = 'default'): Response
    {
        $user = $request->user();

        if (! $user || ! $user->subscribed($name)) {
            return redirect()->route('dashboard')
                ->with('error', 'An active subscription is required to access this page.');
        }

        return $next($request);
    }
}
