<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Order Confirmation</title>
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #222;">

<h2 style="color: #198754;">Thanks for your purchase, {{ $order->user->name }}!</h2>

<p>Your order has been confirmed. Here are the details:</p>

<table cellpadding="8" cellspacing="0" border="0" style="width: 100%; border-collapse: collapse; margin: 20px 0;">
    <tr style="background: #f8f9fa;">
        <td style="border: 1px solid #dee2e6;"><strong>Order ID</strong></td>
        <td style="border: 1px solid #dee2e6;"><code>{{ $order->uuid }}</code></td>
    </tr>
    <tr>
        <td style="border: 1px solid #dee2e6;"><strong>Type</strong></td>
        <td style="border: 1px solid #dee2e6;">{{ $order->type->value }}</td>
    </tr>
    <tr style="background: #f8f9fa;">
        <td style="border: 1px solid #dee2e6;"><strong>Amount</strong></td>
        <td style="border: 1px solid #dee2e6;">{{ $order->formatted_amount }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #dee2e6;"><strong>Paid at</strong></td>
        <td style="border: 1px solid #dee2e6;">{{ $order->paid_at?->format('Y-m-d H:i:s') }}</td>
    </tr>
    <tr style="background: #f8f9fa;">
        <td style="border: 1px solid #dee2e6;"><strong>Payment reference</strong></td>
        <td style="border: 1px solid #dee2e6;"><code>{{ $order->stripe_payment_intent_id }}</code></td>
    </tr>
</table>

@if ($order->items->isNotEmpty())
    <h3>Items</h3>
    <ul>
        @foreach ($order->items as $item)
            <li>{{ $item->name }} × {{ $item->quantity }} — {{ number_format($item->subtotal_cents / 100, 2) }} {{ strtoupper($order->currency) }}</li>
        @endforeach
    </ul>
@endif

<p style="margin-top: 30px; color: #6c757d; font-size: 13px;">
    If you didn't make this purchase, please contact support immediately.
</p>

</body>
</html>
