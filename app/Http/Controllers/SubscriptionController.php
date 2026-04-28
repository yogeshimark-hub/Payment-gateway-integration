<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubscribeRequest;
use App\Models\Plan;
use App\Services\Stripe\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function index(): View
    {
        return view('subscriptions.index', [
            'plans'              => Plan::active()->get(),
            'currentSubscription' => $this->subscriptions->currentSubscription($this->getUser()),
        ]);
    }

    public function subscribe(SubscribeRequest $request): RedirectResponse
    {
        $plan = Plan::active()->findOrFail($request->integer('plan_id'));

        // Guard against a pre-filled placeholder slipping through.
        if (str_contains($plan->stripe_price_id, 'REPLACE_ME')) {
            return redirect()->route('subscriptions.index')
                ->with('error', "Plan '{$plan->name}' is missing a real Stripe Price ID. See README.");
        }

        $checkout = $this->subscriptions->checkout($this->getUser(), $plan);

        return redirect($checkout->url);
    }

    /**
     * After the Stripe-hosted checkout completes, Stripe redirects here with
     * ?session_id=... . Webhooks are async so the subscription row may not
     * exist yet — the success view auto-refreshes until it does.
     */
    public function success(Request $request): View
    {
        return view('subscriptions.success', [
            'sessionId' => $request->query('session_id'),
            'subscription' => $this->subscriptions->currentSubscription($this->getUser()),
        ]);
    }

    public function cancel(): View
    {
        return view('subscriptions.cancel');
    }

    public function manage(): View
    {
        return view('subscriptions.manage', [
            'subscription' => $this->subscriptions->currentSubscription($this->getUser()),
        ]);
    }

    public function cancelCurrent(): RedirectResponse
    {
        $this->subscriptions->cancelAtPeriodEnd($this->getUser());
        return redirect()->route('subscriptions.manage')
            ->with('success', 'Subscription will end at the current billing period.');
    }

    public function resume(): RedirectResponse
    {
        $this->subscriptions->resume($this->getUser());
        return redirect()->route('subscriptions.manage')
            ->with('success', 'Subscription resumed.');
    }

    private function getUser(): \App\Models\User
    {
        return auth()->user();
    }
}
