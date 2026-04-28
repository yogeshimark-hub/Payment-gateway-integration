<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Mail\OrderConfirmationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Listens for: OrderPaid
 * Auto-registered by Laravel via the OrderPaid typehint on handle().
 */
class SendOrderConfirmationEmail implements ShouldQueue
{
    public function handle(OrderPaid $event): void
    {
        $user = $event->order->user;

        if (! $user || ! $user->email) {
            Log::warning('OrderPaid: cannot send confirmation, no email', [
                'order_uuid' => $event->order->uuid,
            ]);
            return;
        }

        Mail::to($user)->send(new OrderConfirmationMail($event->order->loadMissing('items')));
    }
}
