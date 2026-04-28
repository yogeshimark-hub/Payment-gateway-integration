<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listens for: OrderPaid
 * Auto-registered by Laravel via the OrderPaid typehint on handle().
 *
 * Skeleton — extend to grant access after a successful purchase.
 * Examples: enroll user in a course, generate ebook download URL, mark service as active.
 */
class GrantUserAccess implements ShouldQueue
{
    public function handle(OrderPaid $event): void
    {
        Log::info('GrantUserAccess: granting access for order', [
            'order_uuid' => $event->order->uuid,
            'user_id'    => $event->order->user_id,
        ]);

        // TODO: implement domain-specific access logic per order item.
        // foreach ($event->order->items as $item) {
        //     match ($item->product?->type) {
        //         ProductType::Course => Enrollment::create([...]),
        //         ProductType::Ebook  => DownloadLink::create([...]),
        //         default             => null,
        //     };
        // }
    }
}
