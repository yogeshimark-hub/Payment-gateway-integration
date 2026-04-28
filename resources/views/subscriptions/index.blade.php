@extends('layouts.app')

@section('title', 'Subscribe')

@section('content')
<div class="row mb-4">
    <div class="col">
        <h2>Choose a plan</h2>
        <p class="text-muted">
            <span class="badge bg-primary">Combo 1a · Cashier</span>
            Subscriptions managed by Laravel Cashier. Card data is collected on a Stripe-hosted page.
        </p>
    </div>
</div>

@if ($currentSubscription && $currentSubscription->valid())
    <div class="alert alert-info d-flex justify-content-between align-items-center">
        <span>
            You already have an active subscription on the <strong>{{ $currentSubscription->stripe_price ?? '—' }}</strong> price.
        </span>
        <a href="{{ route('subscriptions.manage') }}" class="btn btn-sm btn-primary">Manage subscription</a>
    </div>
@endif

<div class="row g-3">
    @forelse ($plans as $plan)
        <div class="col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5>{{ $plan->name }}</h5>
                    <p class="display-6 mb-0">{{ $plan->formatted_amount }}</p>
                    <p class="text-muted">/ {{ $plan->interval->value }}</p>

                    @if (is_array($plan->features))
                        <ul class="list-unstyled small">
                            @foreach ($plan->features as $feature)
                                <li>· {{ $feature }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if (str_contains($plan->stripe_price_id, 'REPLACE_ME'))
                        <div class="alert alert-warning small mb-2">
                            stripe_price_id placeholder. Update <code>plans.stripe_price_id</code> with a real Stripe Price ID first.
                        </div>
                    @endif

                    <form action="{{ route('subscriptions.subscribe') }}" method="POST">
                        @csrf
                        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                        <button type="submit" class="btn btn-primary w-100"
                            @if (str_contains($plan->stripe_price_id, 'REPLACE_ME')) disabled @endif>
                            Subscribe with Stripe
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="col"><div class="alert alert-warning">No plans available. Run <code>php artisan db:seed</code>.</div></div>
    @endforelse
</div>
@endsection
