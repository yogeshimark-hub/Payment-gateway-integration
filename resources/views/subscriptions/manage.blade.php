@extends('layouts.app')

@section('title', 'Manage subscription')

@section('content')
<div class="row mb-4">
    <div class="col">
        <h2>Manage subscription</h2>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body">
                @if ($subscription)
                    <h5>Current subscription</h5>

                    <table class="table table-sm">
                        <tr><th>Type</th><td>{{ $subscription->type }}</td></tr>
                        <tr><th>Stripe ID</th><td><code>{{ $subscription->stripe_id }}</code></td></tr>
                        <tr><th>Stripe price</th><td><code>{{ $subscription->stripe_price ?? '—' }}</code></td></tr>
                        <tr><th>Status</th><td>{{ $subscription->stripe_status }}</td></tr>
                        <tr><th>Trial ends</th><td>{{ $subscription->trial_ends_at?->format('Y-m-d H:i') ?? '—' }}</td></tr>
                        <tr><th>Ends at</th><td>{{ $subscription->ends_at?->format('Y-m-d H:i') ?? '—' }}</td></tr>
                    </table>

                    @if ($subscription->onGracePeriod())
                        <div class="alert alert-warning">
                            Subscription is set to cancel on
                            <strong>{{ $subscription->ends_at?->format('Y-m-d H:i') }}</strong>.
                            You still have access until then.
                        </div>
                        <form action="{{ route('subscriptions.resume') }}" method="POST">
                            @csrf
                            <button class="btn btn-success">Resume subscription</button>
                        </form>
                    @elseif ($subscription->canceled())
                        <div class="alert alert-secondary">This subscription has ended.</div>
                        <a class="btn btn-primary" href="{{ route('subscriptions.index') }}">Subscribe again</a>
                    @else
                        <form action="{{ route('subscriptions.cancel-current') }}" method="POST"
                              onsubmit="return confirm('Cancel at the end of the current period?');">
                            @csrf
                            <button class="btn btn-outline-danger">Cancel subscription</button>
                        </form>
                    @endif
                @else
                    <p class="text-muted">No subscription on file.</p>
                    <a class="btn btn-primary" href="{{ route('subscriptions.index') }}">Choose a plan</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
