@extends('layouts.app')

@section('title', 'Plans')

@section('content')
<div class="row mb-4">
    <div class="col">
        <h2>Plans</h2>
        <p class="text-muted mb-1">
            Pick a plan, then choose how you want to pay. This reference app exposes four Stripe integration patterns side-by-side.
        </p>
        <p class="small text-muted mb-0">
            <span class="badge bg-info">1a Cashier</span>
            <span class="badge bg-info">1b Payment Intents</span>
            <span class="badge bg-info">2a Checkout</span>
            <span class="badge bg-info">2b Elements</span>
        </p>
    </div>
</div>

@if ($currentSubscription && $currentSubscription->valid())
    <div class="alert alert-info d-flex justify-content-between align-items-center">
        <span>You already have an active subscription.</span>
        <a href="{{ route('subscriptions.manage') }}" class="btn btn-sm btn-primary">Manage subscription</a>
    </div>
@endif

@php
    $recurring = $plans->where('billing_type', \App\Enums\BillingType::Recurring);
    $oneTime   = $plans->where('billing_type', \App\Enums\BillingType::OneTime);
@endphp

@if ($recurring->isNotEmpty())
    <h4 class="mt-4 mb-3">Subscription plans</h4>
    <div class="row g-3">
        @foreach ($recurring as $plan)
            @include('pricing._plan_card', ['plan' => $plan])
        @endforeach
    </div>
@endif

@if ($oneTime->isNotEmpty())
    <h4 class="mt-5 mb-3">One-time purchases</h4>
    <div class="row g-3">
        @foreach ($oneTime as $plan)
            @include('pricing._plan_card', ['plan' => $plan])
        @endforeach
    </div>
@endif

@if ($plans->isEmpty())
    <div class="alert alert-warning">
        No plans available. An admin can add one at <a href="{{ route('admin.plans.index') }}">Admin · Plans</a>.
    </div>
@endif
@endsection
