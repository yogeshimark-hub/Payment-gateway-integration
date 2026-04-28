<?php

namespace App\Listeners;

use App\Events\OrderFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listens for: OrderFailed
 * Auto-registered by Laravel via the OrderFailed typehint on handle().
 */
class NotifyOrderFailed implements ShouldQueue
{
    public function handle(OrderFailed $event): void
    {
        Log::warning('Order payment failed', [
            'order_uuid' => $event->order->uuid,
            'user_id'    => $event->order->user_id,
            'reason'     => $event->reason,
        ]);

        // TODO: send "payment failed, please retry" email to the user.
        // Mail::to($event->order->user)->send(new OrderFailedMail($event->order, $event->reason));
    }
}
