<?php

namespace App\Http\Controllers;

use App\Enums\OrderType;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Services\Stripe\PaymentIntentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Combo 2b — Stripe Elements (custom branded UI).
 *
 * Reuses PaymentIntentService end-to-end. Only the frontend differs:
 * Combo 1b mounts Stripe's Payment Element (multi-method),
 * Combo 2b mounts the classic Card Element styled to match our Bootstrap forms.
 */
class ElementsController extends Controller
{
    public function __construct(private PaymentIntentService $paymentIntents) {}

    public function index(): View
    {
        return view('payments.elements', [
            'products' => Product::active()->whereHas('activePrices')->with('activePrices')->get(),
        ]);
    }

    /**
     * Show the branded card form for a specific product.
     * Creates the PaymentIntent up-front so the Card Element can mount on page load.
     */
    public function show(Product $product): View
    {
        abort_unless($product->is_active, 404);
        $price = $product->activePrices()->firstOrFail();

        $result = $this->paymentIntents->create(
            user:        request()->user(),
            amountCents: $price->amount_cents,
            currency:    $price->currency,
            type:        OrderType::Elements,
            metadata:    ['product_slug' => $product->slug, 'price_id' => $price->id],
        );

        return view('payments.elements-form', [
            'product'      => $product,
            'price'        => $price,
            'order'        => $result['order'],
            'clientSecret' => $result['client_secret'],
        ]);
    }

    public function success(Order $order): View
    {
        abort_unless($order->user_id === auth()->id(), 403);

        return view('payments.elements-success', compact('order'));
    }

    /**
     * Plan-aware entrypoint from the unified /plans page. Same as show()
     * but takes a Plan instead of a Product.
     */
    public function showForPlan(Plan $plan): View|RedirectResponse
    {
        if (! $plan->is_active || $plan->isRecurring() || $plan->needsStripeSync()) {
            return redirect()->route('pricing.index')
                ->with('error', "Plan '{$plan->name}' is not available via Stripe Elements.");
        }

        $result = $this->paymentIntents->createForPlan(
            user: auth()->user(),
            plan: $plan,
            type: OrderType::Elements,
        );

        return view('payments.elements-plan', [
            'plan'         => $plan,
            'order'        => $result['order'],
            'clientSecret' => $result['client_secret'],
        ]);
    }
}
