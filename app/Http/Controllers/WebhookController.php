<?php

namespace App\Http\Controllers;

use App\Models\WebhookEvent;
use App\Services\Stripe\WebhookEventHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Single webhook endpoint for ALL four combos.
 *
 *  • Inherits from Cashier's controller → subscription/customer events Cashier
 *    already handles (handleCustomerSubscriptionCreated, etc.) keep working.
 *  • Adds idempotency: every Stripe event ID is logged in webhook_events
 *    BEFORE dispatch, so Stripe retries can never double-process.
 *  • Adds custom handlers for one-time payment events (Combo 1b / 2a / 2b)
 *    via WebhookEventHandler.
 */
class WebhookController extends CashierWebhookController
{
    public function __construct(protected WebhookEventHandler $handler)
    {
        parent::__construct();
    }

    public function handleWebhook(Request $request)
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || ! isset($payload['id'], $payload['type'])) {
            return new Response('Invalid payload', 400);
        }

        $eventId = $payload['id'];
        $type    = $payload['type'];

        // ── Idempotency guard ────────────────────────────────────────────
        $event = WebhookEvent::where('stripe_event_id', $eventId)->first();

        if ($event && $event->isProcessed()) {
            Log::info('Webhook duplicate, skipping', compact('eventId', 'type'));
            return new Response('Already processed', 200);
        }

        if (! $event) {
            $event = WebhookEvent::create([
                'stripe_event_id' => $eventId,
                'type'            => $type,
                'payload'         => $payload,
            ]);
        }

        // ── Dispatch ─────────────────────────────────────────────────────
        try {
            $response = $this->dispatchToHandler($payload);
            $event->markProcessed();
            return $response;
        } catch (Throwable $e) {
            Log::error('Webhook dispatch failed', [
                'event_id' => $eventId,
                'type'     => $type,
                'error'    => $e->getMessage(),
            ]);
            $event->markFailed($e->getMessage());
            // Re-throw so Stripe gets a 5xx and retries
            throw $e;
        }
    }

    /**
     * Decide whether to use one of OUR handlers or fall back to Cashier's parent.
     */
    private function dispatchToHandler(array $payload): Response
    {
        $type = $payload['type'];

        $ourHandlers = [
            'payment_intent.succeeded'      => 'handlePaymentIntentSucceeded',
            'payment_intent.payment_failed' => 'handlePaymentIntentFailed',
            'checkout.session.completed'    => 'handleCheckoutSessionCompleted',
        ];

        if (isset($ourHandlers[$type])) {
            $method = $ourHandlers[$type];
            $this->handler->{$method}($payload);
            return $this->successMethod();
        }

        // Cashier's parent dispatches handle{StudlyEventName} on $this and
        // already implements handlers for customer.subscription.* etc.
        return parent::handleWebhook(request());
    }
}
