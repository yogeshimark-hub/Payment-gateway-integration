@php
    $needsSync = $plan->needsStripeSync();
    $isRecurring = $plan->isRecurring();
@endphp

<div class="col-md-6 col-lg-4">
    <div class="card h-100 shadow-sm">
        <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-start">
                <h5 class="mb-0">{{ $plan->name }}</h5>
                <span class="badge {{ $plan->billing_type->badgeClass() }}">{{ $plan->billing_type->label() }}</span>
            </div>

            <p class="display-6 mt-2 mb-0">{{ $plan->formatted_amount }}</p>
            @if ($isRecurring)
                <p class="text-muted small">
                    / {{ $plan->interval_count > 1 ? $plan->interval_count . ' ' : '' }}{{ $plan->interval->value }}{{ $plan->interval_count > 1 ? 's' : '' }}
                </p>
            @else
                <p class="text-muted small">one-time</p>
            @endif

            @if (is_array($plan->features) && count($plan->features))
                <ul class="list-unstyled small mt-2">
                    @foreach ($plan->features as $feature)
                        <li>· {{ $feature }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-auto">
                @if ($needsSync)
                    <div class="alert alert-warning small mb-2">
                        Not synced to Stripe yet. An admin needs to sync this plan before it can be purchased.
                    </div>
                    <button class="btn btn-secondary w-100" disabled>Unavailable</button>
                @else
                    <p class="small text-muted mb-2">
                        <strong>Demo</strong> · choose a payment method:
                    </p>

                    @if ($isRecurring)
                        {{-- Cashier is the only path that supports true subscriptions tied to the unified webhook. --}}
                        <form action="{{ route('subscriptions.subscribe') }}" method="POST">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                            <button type="submit" class="btn btn-primary w-100">
                                <span class="badge bg-light text-primary me-1">1a</span> Subscribe via Cashier
                            </button>
                        </form>
                    @else
                        <div class="d-grid gap-2">
                            <form action="{{ route('plans.pay.intent', $plan) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <span class="badge bg-primary me-1">1b</span> Pay with Payment Intents
                                </button>
                            </form>
                            <form action="{{ route('plans.pay.checkout', $plan) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <span class="badge bg-primary me-1">2a</span> Pay with Stripe Checkout
                                </button>
                            </form>
                            <a href="{{ route('plans.pay.elements', $plan) }}" class="btn btn-outline-primary w-100">
                                <span class="badge bg-primary me-1">2b</span> Pay with Stripe Elements
                            </a>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
