@extends('layouts.app')

@section('title', 'Subscription confirmed')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body p-4 text-center">
                @if ($subscription && $subscription->valid())
                    <div class="display-1 text-success">✓</div>
                    <h3>Subscription active</h3>
                    <p class="text-muted">Welcome aboard. Your subscription is set up and you can use it right away.</p>

                    <ul class="list-unstyled small text-start mx-auto" style="max-width: 360px;">
                        <li><strong>Type:</strong> {{ $subscription->type }}</li>
                        <li><strong>Stripe ID:</strong> <code>{{ $subscription->stripe_id }}</code></li>
                        <li><strong>Status:</strong> {{ $subscription->stripe_status }}</li>
                    </ul>

                    <a href="{{ route('subscriptions.manage') }}" class="btn btn-primary">Manage subscription</a>
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Back to dashboard</a>
                @else
                    <div class="display-3 mb-3">⏳</div>
                    <h3>Setting up your subscription…</h3>
                    <p class="text-muted">
                        Stripe is processing the payment and notifying our server (this typically takes a few seconds).
                        This page will refresh automatically.
                    </p>
                    <div class="spinner-border text-primary" role="status"></div>
                    @if ($sessionId)
                        <p class="text-muted small mt-3"><code>session_id={{ $sessionId }}</code></p>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if (! ($subscription && $subscription->valid()))
    <script>
        // Poll: webhook may take a moment to flip the subscription to active.
        setTimeout(function () { window.location.reload(); }, 3000);
    </script>
@endif
@endpush
