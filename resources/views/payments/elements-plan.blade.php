@extends('layouts.app')

@section('title', 'Pay — ' . $plan->name)

@section('content')
<div class="row mb-3">
    <div class="col">
        <h2>{{ $plan->name }}</h2>
        <p class="text-muted">
            <span class="badge bg-info text-dark">Combo 2b · Stripe Elements</span>
            <a href="{{ route('pricing.index') }}" class="ms-2 small">← back to plans</a>
        </p>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5>Pay {{ $plan->formatted_amount }}</h5>
                <p class="text-muted small mb-3">
                    Order: <code>{{ substr($order->uuid, 0, 8) }}…</code>
                </p>

                <form id="elements-form">
                    <div class="mb-3">
                        <label for="card-name" class="form-label">Cardholder name</label>
                        <input type="text" id="card-name" class="form-control" placeholder="Jane Doe">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Card details</label>
                        <div id="card-element"></div>
                        <div id="card-errors" role="alert"></div>
                    </div>

                    <button type="submit" id="pay-btn" class="btn btn-info text-white w-100">
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
                <h6>How this differs from /pay/elements/{product}</h6>
                <ul class="ps-3 mb-0">
                    <li>Amount comes from the Plan, not a Product/Price row.</li>
                    <li>Same Card Element + <code>PaymentIntentService</code> as the product flow.</li>
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
<script src="{{ asset('js/elements.js') }}"></script>
<script>
    $(function () {
        ElementsFlow.init({
            clientSecret: @json($clientSecret),
            returnUrl:    @json(route('payments.elements.success', $order->uuid)),
        });
    });
</script>
@endpush
