@extends('layouts.app')

@section('title', 'Pay with Payment Intent')

@section('content')
<div class="row mb-3">
    <div class="col">
        <h2>Pay any amount</h2>
        <p class="text-muted">
            <span class="badge bg-success">Combo 1b · Payment Intents</span>
            One-time payment with full UX control. The card field is rendered by Stripe Payment Element on this page.
        </p>
    </div>
</div>

<div class="row">
    <div class="col-md-6">

        {{-- Step 1: amount form --}}
        <div id="amount-section" class="card shadow-sm mb-3">
            <div class="card-body">
                <h5 class="card-title">1. Choose amount</h5>
                <form id="amount-form" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label for="amount_dollars" class="form-label">Amount (USD)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="amount_dollars" id="amount_dollars"
                                   class="form-control" step="0.01" min="1" max="1000" value="19.00">
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="note" class="form-label">Note (optional)</label>
                        <input type="text" name="note" id="note" class="form-control" maxlength="500">
                        <div class="invalid-feedback"></div>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Continue to payment</button>
                </form>
            </div>
        </div>

        {{-- Step 2: card form --}}
        <div id="card-section" class="card shadow-sm" style="display: none;">
            <div class="card-body">
                <h5 class="card-title">2. Enter payment details</h5>
                <p class="small text-muted mb-3">
                    Order: <code id="order-uuid-display"></code> · Amount: <strong id="amount-display"></strong>
                </p>
                <form id="payment-form">
                    <div id="payment-element" class="mb-3"></div>
                    <div id="payment-error" class="alert alert-danger d-none"></div>
                    <button type="submit" id="pay-btn" class="btn btn-success w-100">
                        <span id="pay-btn-text">Pay now</span>
                        <span id="pay-btn-spinner" class="spinner-border spinner-border-sm d-none"></span>
                    </button>
                </form>
            </div>
        </div>

    </div>

    <div class="col-md-6">
        <div class="card bg-light">
            <div class="card-body small">
                <h6>How this flow works</h6>
                <ol class="mb-0 ps-3">
                    <li>You enter an amount and click <em>Continue</em>.</li>
                    <li>Server creates an <code>orders</code> row + Stripe PaymentIntent, returns the <code>client_secret</code>.</li>
                    <li>Stripe Payment Element mounts and collects card.</li>
                    <li>You click Pay → <code>stripe.confirmPayment</code> → Stripe redirects to the success page.</li>
                    <li>Stripe sends <code>payment_intent.succeeded</code> webhook → server marks order paid.</li>
                </ol>
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
        PaymentIntentFlow.init({
            createUrl:    "{{ route('payments.intent.create') }}",
            amountForm:   '#amount-form',
            cardSection:  '#card-section',
            amountSection:'#amount-section',
        });
    });
</script>
@endpush
