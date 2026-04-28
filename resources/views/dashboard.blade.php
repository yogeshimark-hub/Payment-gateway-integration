@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="row mb-4">
    <div class="col">
        <h2>Dashboard</h2>
        <p class="text-muted">Pick a payment flow to test. All four use the same unified webhook for DB sync.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 border-primary">
            <div class="card-body">
                <span class="badge bg-primary mb-2">Combo 1a</span>
                <h5>Cashier Subscriptions</h5>
                <p class="text-muted small">Recurring billing with built-in helpers.</p>
                @if ($subscription && $subscription->valid())
                    <span class="badge bg-success mb-2">Active</span>
                    <a class="btn btn-primary btn-sm" href="{{ route('subscriptions.manage') }}">Manage</a>
                @else
                    <a class="btn btn-primary btn-sm" href="{{ route('subscriptions.index') }}">Choose plan</a>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 border-success">
            <div class="card-body">
                <span class="badge bg-success mb-2">Combo 1b</span>
                <h5>Payment Intents</h5>
                <p class="text-muted small">Custom one-time payment, full UX control.</p>
                <a class="btn btn-success btn-sm" href="{{ route('payments.intent.show') }}">Pay any amount</a>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 border-warning">
            <div class="card-body">
                <span class="badge bg-warning text-dark mb-2">Combo 2a</span>
                <h5>Stripe Checkout</h5>
                <p class="text-muted small">Hosted checkout — fastest to ship.</p>
                <a class="btn btn-warning btn-sm" href="{{ route('payments.checkout.index') }}">Browse products</a>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 border-info">
            <div class="card-body">
                <span class="badge bg-info text-dark mb-2">Combo 2b</span>
                <h5>Stripe Elements</h5>
                <p class="text-muted small">Custom card field, your domain & branding.</p>
                <a class="btn btn-info btn-sm text-white" href="{{ route('payments.elements.index') }}">Pay with Elements</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Available plans (subscriptions)</div>
            <ul class="list-group list-group-flush">
                @forelse ($plans as $plan)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $plan->name }}</span>
                        <span class="text-muted">{{ $plan->formatted_amount }} / {{ $plan->interval->value }}</span>
                    </li>
                @empty
                    <li class="list-group-item text-muted">No plans seeded.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Available products (one-time)</div>
            <ul class="list-group list-group-flush">
                @forelse ($products as $product)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $product->name }} <small class="text-muted">({{ $product->type->value }})</small></span>
                        <span class="text-muted">
                            {{ $product->activePrices->first()?->formatted_amount ?? 'pay-what-you-want' }}
                        </span>
                    </li>
                @empty
                    <li class="list-group-item text-muted">No products seeded.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col">
        <div class="card">
            <div class="card-header">Recent orders</div>
            <ul class="list-group list-group-flush">
                @forelse ($orders as $order)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                            <code>{{ substr($order->uuid, 0, 8) }}…</code>
                            {{ $order->type->value }} · {{ $order->formatted_amount }}
                        </span>
                        <span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                    </li>
                @empty
                    <li class="list-group-item text-muted">No orders yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
