@extends('layouts.app')

@section('title', 'Pay — ' . $plan->name)

@section('content')
<div class="row mb-3">
    <div class="col">
        <h2>{{ $plan->name }}</h2>
        <p class="text-muted">
            <span class="badge bg-success">Combo 1b · Payment Intents</span>
            <a href="{{ route('pricing.index') }}" class="ms-2 small">← back to plans</a>
        </p>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Pay {{ $plan->formatted_amount }}</h5>
                <p class="text-muted small mb-3">
                    Order: <code>{{ substr($order->uuid, 0, 8) }}…</code>
                </p>

                <form id="payment-form">
                    <div id="payment-element" class="mb-3"></div>
                    <div id="payment-error" class="alert alert-danger d-none"></div>
                    <button type="submit" id="pay-btn" class="btn btn-success w-100">
                        <span id="pay-btn-text">Pay {{ $plan->formatted_amount }}</span>
                        <span id="pay-btn-spinner" class="spinner-border spinner-border-sm d-none"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card bg-light">
            <div class="card-body small">
                <h6>How this differs from /pay/intent</h6>
                <ul class="ps-3 mb-0">
                    <li>Amount is fixed by the Plan — no amount-picker step.</li>
                    <li>PaymentIntent is created server-side on page load (not on submit).</li>
                    <li>Same <code>PaymentIntentService</code>; same Payment Element on the frontend.</li>
                </ul>
            </div>
        </div>
        <div class="alert alert-warning small mt-3 mb-0">
            Test card: <code>4242 4242 4242 4242</code> · any future date · any CVC · any zip.
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/payment-intent.js') }}"></script>
<script>
    $(function () {
        PaymentIntentFlow.initWithSecret({
            clientSecret: @json($clientSecret),
            returnUrl:    @json(route('payments.intent.success', $order->uuid)),
        });
    });
</script>
@endpush
