<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;

/**
 * Polled by payment success pages while waiting for the webhook to flip status.
 *
 * Returns minimal JSON so the client can `if (status === 'paid') redirect`.
 */
class OrderStatusController extends Controller
{
    public function show(Order $order): JsonResponse
    {
        abort_unless($order->user_id === auth()->id(), 403);

        return response()->json([
            'uuid'    => $order->uuid,
            'status'  => $order->status->value,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'amount'  => $order->formatted_amount,
            'type'    => $order->type->value,
        ]);
    }
}
