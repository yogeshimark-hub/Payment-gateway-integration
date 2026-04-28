<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BillingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanRequest;
use App\Models\Plan;
use App\Services\Stripe\PlanSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Stripe\Exception\ApiErrorException;

class PlanController extends Controller
{
    public function __construct(private PlanSyncService $sync) {}

    public function index(): View
    {
        return view('admin.plans.index', [
            'plans' => Plan::orderByDesc('id')->get(),
        ]);
    }

    public function create(): View
    {
        $plan = new Plan([
            'billing_type'   => BillingType::Recurring,
            'currency'       => 'USD',
            'interval'       => 'month',
            'interval_count' => 1,
            'is_active'      => true,
        ]);

        return view('admin.plans.create', compact('plan'));
    }

    public function store(PlanRequest $request): RedirectResponse
    {
        try {
            $plan = DB::transaction(function () use ($request) {
                $plan = Plan::create($request->dataForPlan());
                $this->sync->syncOnCreate($plan);
                return $plan;
            });
        } catch (ApiErrorException $e) {
            return back()->withInput()->with('error', 'Stripe rejected the plan: ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.plans.index')
            ->with('success', "Plan '{$plan->name}' created and synced to Stripe.");
    }

    public function edit(Plan $plan): View
    {
        return view('admin.plans.edit', compact('plan'));
    }

    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $plan) {
                $original = $plan->getOriginal();
                $plan->update($request->dataForPlan());
                $this->sync->syncOnUpdate($plan, $original);
            });
        } catch (ApiErrorException $e) {
            return back()->withInput()->with('error', 'Stripe rejected the update: ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.plans.index')
            ->with('success', "Plan '{$plan->name}' updated and synced to Stripe.");
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $activeCount = $this->countActiveSubscriptions($plan);
        if ($activeCount > 0) {
            return redirect()
                ->route('admin.plans.index')
                ->with('error', "Cannot delete '{$plan->name}': {$activeCount} active subscription(s) reference this plan. Deactivate it instead.");
        }

        try {
            DB::transaction(function () use ($plan) {
                $this->sync->syncOnDelete($plan);
                $plan->delete();
            });
        } catch (ApiErrorException $e) {
            return redirect()
                ->route('admin.plans.index')
                ->with('error', 'Stripe error during delete: ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.plans.index')
            ->with('success', "Plan '{$plan->name}' deleted (archived in Stripe).");
    }

    public function sync(Plan $plan): RedirectResponse
    {
        try {
            DB::transaction(function () use ($plan) {
                // Strip placeholder IDs so syncOnCreate treats the plan as fresh.
                if (str_contains((string) $plan->stripe_price_id, 'REPLACE_ME')) {
                    $plan->stripe_price_id = null;
                }
                if (str_contains((string) $plan->stripe_product_id, 'REPLACE_ME')) {
                    $plan->stripe_product_id = null;
                }
                $this->sync->syncOnCreate($plan);
            });
        } catch (ApiErrorException $e) {
            return redirect()
                ->route('admin.plans.edit', $plan)
                ->with('error', 'Stripe sync failed: ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.plans.edit', $plan)
            ->with('success', "Plan '{$plan->name}' synced to Stripe.");
    }

    public function toggle(Plan $plan): RedirectResponse
    {
        try {
            DB::transaction(function () use ($plan) {
                $plan->update(['is_active' => ! $plan->is_active]);
                $this->sync->syncOnToggle($plan);
            });
        } catch (ApiErrorException $e) {
            return redirect()
                ->route('admin.plans.index')
                ->with('error', 'Stripe error: ' . $e->getMessage());
        }

        $verb = $plan->is_active ? 'activated' : 'deactivated';
        return redirect()
            ->route('admin.plans.index')
            ->with('success', "Plan '{$plan->name}' {$verb} (synced to Stripe).");
    }

    private function countActiveSubscriptions(Plan $plan): int
    {
        if (! $plan->stripe_price_id) {
            return 0;
        }

        return DB::table('subscription_items')
            ->join('subscriptions', 'subscriptions.id', '=', 'subscription_items.subscription_id')
            ->where('subscription_items.stripe_price', $plan->stripe_price_id)
            ->whereIn('subscriptions.stripe_status', ['active', 'trialing', 'past_due'])
            ->where(function ($q) {
                $q->whereNull('subscriptions.ends_at')
                  ->orWhere('subscriptions.ends_at', '>', now());
            })
            ->count();
    }
}
