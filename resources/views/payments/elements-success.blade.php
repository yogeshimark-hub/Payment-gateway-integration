@extends('layouts.app')

@section('title', 'Payment processing')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body p-4 text-center">
                <div id="status-pending" @if ($order->isPaid()) style="display:none" @endif>
                    <div class="display-3 mb-3">⏳</div>
                    <h3>Confirming your payment…</h3>
                    <p class="text-muted">Stripe is sending us the success notification (this typically takes 1–3 seconds).</p>
                    <div class="spinner-border text-info" role="status"></div>
                </div>

                <div id="status-paid" @unless ($order->isPaid()) style="display:none" @endunless>
                    <div class="display-1 text-success">✓</div>
                    <h3>Payment received</h3>
                    <p class="text-muted mb-1">Thank you. Your order is confirmed.</p>
                    <p class="small text-muted">
                        Order <code>{{ substr($order->uuid, 0, 8) }}…</code> ·
                        <span id="paid-amount">{{ $order->formatted_amount }}</span> ·
                        <span id="paid-at">{{ $order->paid_at?->format('Y-m-d H:i:s') }}</span>
                    </p>
                    <a href="{{ route('dashboard') }}" class="btn btn-primary mt-3">Back to dashboard</a>
                </div>

                <div id="status-timeout" style="display:none">
                    <div class="display-3 mb-3">⚠</div>
                    <h3>Still confirming…</h3>
                    <p class="text-muted">
                        Taking longer than expected. The webhook may be delayed; the daily reconciliation job will catch it if it was lost.
                    </p>
                    <button class="btn btn-outline-primary" onclick="window.location.reload()">Check again</button>
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Back to dashboard</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@unless ($order->isPaid())
<script src="{{ asset('js/order-status-poller.js') }}"></script>
<script>
    OrderStatusPoller.start({
        statusUrl: "{{ route('orders.status', $order->uuid) }}",
        onPaid: function (data) {
            $('#paid-amount').text(data.amount);
            if (data.paid_at) $('#paid-at').text(data.paid_at);
            $('#status-pending').hide();
            $('#status-paid').show();
        },
        onFailed: function () {
            $('#status-pending').hide();
            $('#status-timeout').find('h3').text('Payment failed');
            $('#status-timeout').show();
        },
        onTimeout: function () {
            $('#status-pending').hide();
            $('#status-timeout').show();
        },
    });
</script>
@endunless
@endpush
