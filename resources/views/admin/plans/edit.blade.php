@extends('layouts.app')

@section('title', 'Admin · Edit ' . $plan->name)

@section('content')
<div class="row mb-3">
    <div class="col">
        <h2>Edit plan</h2>
        <p class="text-muted">
            <a href="{{ route('admin.plans.index') }}" class="small">← back to plans</a>
        </p>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($plan->needsStripeSync())
    <div class="alert alert-warning d-flex justify-content-between align-items-center">
        <div>
            <strong>Not synced to Stripe yet.</strong>
            This plan exists only in the database. Sync it to create the Stripe Product and Price before users can subscribe.
        </div>
        <form action="{{ route('admin.plans.sync', $plan) }}" method="POST" class="mb-0">
            @csrf
            <button type="submit" class="btn btn-warning">Sync to Stripe</button>
        </form>
    </div>
@endif

<div class="card shadow-sm">
    <div class="card-body">
        <form action="{{ route('admin.plans.update', $plan) }}" method="POST" novalidate>
            @csrf @method('PUT')
            @include('admin.plans._form')

            <hr>

            <div class="alert alert-light small mb-3">
                <strong>Stripe IDs</strong> (read-only — managed by PlanSyncService)<br>
                Product: <code>{{ $plan->stripe_product_id ?? '—' }}</code><br>
                Price: <code>{{ $plan->stripe_price_id ?? '—' }}</code>
            </div>

            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="{{ route('admin.plans.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection
