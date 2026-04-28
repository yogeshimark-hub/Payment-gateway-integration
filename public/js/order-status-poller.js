/**
 * Reusable poller for /orders/{uuid}/status JSON endpoint.
 * Used by every payment success page (Combos 1b, 2a, 2b).
 *
 * Usage:
 *   OrderStatusPoller.start({
 *     statusUrl:    "{{ route('orders.status', $order->uuid) }}",
 *     onPaid:       (data) => { ... },
 *     onFailed:     (data) => { ... },   // optional
 *     onTimeout:    ()     => { ... },   // optional
 *     maxAttempts:  10,                  // default 10
 *     intervalMs:   1500,                // default 1500
 *   });
 */
window.OrderStatusPoller = (function ($) {
    'use strict';

    function start(opts) {
        const max      = opts.maxAttempts ?? 10;
        const interval = opts.intervalMs ?? 1500;
        let attempts   = 0;

        function poll() {
            attempts++;
            $.getJSON(opts.statusUrl)
                .done(function (data) {
                    if (data.status === 'paid') {
                        opts.onPaid(data);
                        return;
                    }
                    if (data.status === 'failed' && typeof opts.onFailed === 'function') {
                        opts.onFailed(data);
                        return;
                    }
                    if (attempts < max) {
                        setTimeout(poll, interval);
                    } else if (typeof opts.onTimeout === 'function') {
                        opts.onTimeout();
                    }
                })
                .fail(function () {
                    // network error — retry
                    if (attempts < max) setTimeout(poll, interval);
                });
        }

        setTimeout(poll, interval);
    }

    return { start: start };
})(jQuery);
