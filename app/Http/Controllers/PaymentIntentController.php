<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePaymentIntentRequest;
use App\Models\Order;
use App\Models\Plan;
use App\Services\Stripe\PaymentIntentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentIntentController extends Controller
{
    public function __construct(private PaymentIntentService $service) {}

    public function show(): View
    {
        return view('payments.intent');
    }

    public function create(CreatePaymentIntentRequest $request): JsonResponse
    {
        $result = $this->service->create(
            user: $request->user(),
            amountCents: $request->amountCents(),
            currency: 'USD',
            metadata: ['note' => trim((string) $request->input('note', ''))],
        );

        return response()->json([
            'order_uuid'    => $result['order']->uuid,
            'client_secret' => $result['client_secret'],
            'return_url'    => route('payments.intent.success', $result['order']->uuid),
        ]);
    }

    public function success(Order $order): View
    {
        abort_unless($order->user_id === auth()->id(), 403);

        return view('payments.intent-success', compact('order'));
    }

    /**
     * Plan-aware single-step entrypoint from the unified /plans page.
     * Skips the amount-picker form: amount comes from the Plan.
     */
    public function startForPlan(Plan $plan): View|RedirectResponse
    {
        if (! $plan->is_active || $plan->isRecurring() || $plan->needsStripeSync()) {
            return redirect()->route('pricing.index')
                ->with('error', "Plan '{$plan->name}' is not available for one-time payment.");
        }

        $result = $this->service->createForPlan(auth()->user(), $plan);

        return view('payments.intent-plan', [
            'plan'         => $plan,
            'order'        => $result['order'],
            'clientSecret' => $result['client_secret'],
        ]);
    }
}
