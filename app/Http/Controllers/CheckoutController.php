<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCheckoutSessionRequest;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Services\Stripe\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(private CheckoutService $service) {}

    public function index(): View
    {
        return view('payments.checkout', [
            'products' => Product::active()->whereHas('activePrices')->with('activePrices')->get(),
        ]);
    }

    public function start(CreateCheckoutSessionRequest $request): RedirectResponse
    {
        $product = Product::active()->findOrFail($request->integer('product_id'));
        $price   = $product->activePrices()->firstOrFail();

        $session = $this->service->start($request->user(), $product, $price);

        // 303 = correct HTTP redirect after a POST per RFC 7231
        return redirect()->away($session->url, 303);
    }

    public function success(Order $order): View
    {
        abort_unless($order->user_id === auth()->id(), 403);

        return view('payments.checkout-success', compact('order'));
    }

    public function cancel(): View
    {
        return view('payments.checkout-cancel');
    }

    /**
     * Plan-aware entrypoint from the unified /plans page.
     */
    public function startForPlan(Plan $plan): RedirectResponse
    {
        if (! $plan->is_active || $plan->isRecurring() || $plan->needsStripeSync()) {
            return redirect()->route('pricing.index')
                ->with('error', "Plan '{$plan->name}' is not available for one-time checkout.");
        }

        $session = $this->service->startForPlan(auth()->user(), $plan);

        return redirect()->away($session->url, 303);
    }
}
