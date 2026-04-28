<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched once per order, the first time it transitions to PAID.
 *
 * Listeners:
 *   - SendOrderConfirmationEmail
 *   - GrantUserAccess
 */
class OrderPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}
}
