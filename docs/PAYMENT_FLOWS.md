# Payment Flows — Admin & User Reference

This document explains the full lifecycle of a plan in this app — from the moment an admin creates it to the moment a user pays for it — across all four Stripe integration patterns.

- [The four payment patterns](#the-four-payment-patterns)
- [Roles and credentials](#roles-and-credentials)
- [Admin flow — creating, editing, syncing, deleting plans](#admin-flow)
- [User flow — picking a plan and paying](#user-flow)
- [Pattern 1a — Cashier (recurring subscriptions)](#pattern-1a--cashier)
- [Pattern 1b — Payment Intents (one-time, custom UI)](#pattern-1b--payment-intents)
- [Pattern 2a — Stripe Checkout (one-time, hosted)](#pattern-2a--stripe-checkout)
- [Pattern 2b — Stripe Elements (one-time, custom card field)](#pattern-2b--stripe-elements)
- [The unified webhook](#the-unified-webhook)
- [Database tables — what gets written when](#database-tables)
- [Common operations cheatsheet](#common-operations-cheatsheet)

---

## The four payment patterns

| Pattern | Combo | Used for | UI hosted on | Service |
|---|---|---|---|---|
| Cashier subscriptions | 1a | Recurring billing | Stripe (Checkout Session) | `SubscriptionService` |
| Payment Intents | 1b | One-time, full UX control | Our app (Stripe Payment Element) | `PaymentIntentService` |
| Stripe Checkout | 2a | One-time, fastest to ship | Stripe (hosted page) | `CheckoutService` |
| Stripe Elements | 2b | One-time, custom branded card field | Our app (Stripe Card Element) | `PaymentIntentService` |

All four flows feed into **one unified webhook** (`/stripe/webhook`) that updates the `orders` / `subscriptions` tables. That's the architectural point of this reference app.

---

## Roles and credentials

Two seeded users (`php artisan db:seed`):

| Email | Password | Role | Can do |
|---|---|---|---|
| `admin@example.com` | `password` | Admin (`is_admin = true`) | Manage plans (create / edit / sync / toggle / delete) **and** purchase as a user |
| `test@example.com` | `password` | Regular user | Browse `/plans` and purchase |

The admin link only appears in the navbar when `auth()->user()->is_admin` is true. The middleware `App\Http\Middleware\EnsureAdmin` protects all `/admin/*` routes.

---

## Admin flow

The admin manages the **catalogue of plans**. Every change in the local DB is mirrored to Stripe in the same database transaction by `PlanSyncService`. If Stripe rejects the change, the DB is rolled back so the two never disagree.

### Step 1 — Log in and open the admin panel

1. Visit `/login`, sign in as `admin@example.com` / `password`
2. Click **Admin · Plans** in the navbar (visible only to admins)
3. Land at `/admin/plans` — a table of every plan with their current Stripe sync status

### Step 2 — Create a new plan

1. Click **+ New Plan** → `/admin/plans/create`
2. Fill in the form:
   - **Name** — required, used as the Stripe Product name
   - **Slug** — optional, auto-generated from name if blank
   - **Billing type** — choose `Recurring` or `One-time` (this controls what payment patterns the plan supports)
   - **Amount** — in dollars (converted to cents internally; Stripe minimum $0.50)
   - **Currency** — USD / EUR / INR / GBP
   - **Interval** — only shown for recurring plans (`month` or `year`)
   - **Every X interval(s)** — also only for recurring (e.g. `every 3 months`)
   - **Features** — one per line, stored as JSON array
   - **Active** — checkbox, controls whether users see the plan
3. Click **Create plan**

What happens server-side:

```
PlanController::store()
  ↓
DB::transaction:
  1. Plan::create($validated)              ← writes plans row
  2. PlanSyncService::syncOnCreate($plan)  ← creates Stripe Product + Price
  3. $plan->update(['stripe_product_id' => ..., 'stripe_price_id' => ...])
  ↓
Redirect to /admin/plans with success flash
```

If step 2 throws (e.g. invalid currency, network error), the entire transaction rolls back — no orphan row in `plans`, no orphan resource on Stripe.

### Step 3 — Edit an existing plan

There are two kinds of edits, with **very different Stripe behavior**.

**Edit name or features only.** Stripe Products are mutable — the SDK call is just `Product::update($id, [name => ...])`. The Price is untouched. Existing subscribers are completely unaffected.

**Edit amount, currency, billing_type, interval, or interval_count.** Stripe Prices are **immutable**. To change them we must:
1. Archive the old Price (`Price::update($oldId, ['active' => false])`)
2. Create a brand-new Price (`Price::create([...])`)
3. Save the new `stripe_price_id` on the plan

Existing subscribers stay on the old (archived) price. This is the correct behavior — you can't retroactively change someone's subscription price without their consent. New signups use the new price.

`PlanSyncService::syncOnUpdate(Plan $plan, array $original)` decides which case applies by diffing the original vs current attributes. The diff logic is in `priceFieldsChanged()`.

### Step 4 — Toggle a plan active/inactive

The **Activate** / **Deactivate** button on the index page mirrors the active flag to both the Stripe Product and Price:

```
$stripe->products->update($plan->stripe_product_id, ['active' => $plan->is_active]);
$stripe->prices->update($plan->stripe_price_id, ['active' => $plan->is_active]);
```

When a plan is inactive:
- It disappears from `/plans` (the `Plan::active()` scope filters it out)
- Existing subscriptions keep working — Stripe still bills active subscribers on archived prices
- The plan still shows in the admin index, just with a grey "inactive" badge

### Step 5 — Delete a plan

**Subscription guard runs first.** Before deleting, the controller checks for active subscriptions referencing this plan's `stripe_price_id`:

```sql
SELECT COUNT(*)
FROM subscription_items
JOIN subscriptions ON subscriptions.id = subscription_items.subscription_id
WHERE subscription_items.stripe_price = $plan->stripe_price_id
  AND subscriptions.stripe_status IN ('active', 'trialing', 'past_due')
  AND (subscriptions.ends_at IS NULL OR subscriptions.ends_at > NOW())
```

If the count > 0, deletion is **blocked** with an error: *"Cannot delete '<name>': N active subscription(s) reference this plan. Deactivate it instead."*

If the count is 0:
1. `PlanSyncService::syncOnDelete()` archives Product + Price (Stripe doesn't allow real delete)
2. `$plan->delete()` removes the DB row
3. Both inside one transaction

### Step 6 — Sync a legacy plan to Stripe

Some plans were seeded before the Stripe sync existed (`stripe_price_id = 'price_starter_monthly_REPLACE_ME'`). They have no real Stripe identity.

The `Plan::needsStripeSync()` helper returns true when:
- `stripe_price_id` is null, **or**
- `stripe_price_id` contains the string `REPLACE_ME`

When this is true:
- The plan card on `/plans` shows a **"Not synced to Stripe yet"** warning and disables the purchase button
- The admin index shows a yellow **"Sync now"** button in place of the price ID
- The admin edit page shows a yellow alert at the top with **"Sync to Stripe"**

Clicking either button hits `POST /admin/plans/{plan}/sync` → `PlanController::sync()`:
1. Strips any `REPLACE_ME` placeholder out of `stripe_product_id` and `stripe_price_id`
2. Calls `PlanSyncService::syncOnCreate($plan)` — same code path as creating a fresh plan
3. The plan now has real `prod_xxx` / `price_xxx` IDs and is purchasable

### Files involved in the admin flow

| File | Role |
|---|---|
| `app/Http/Controllers/Admin/PlanController.php` | All admin actions (CRUD + toggle + sync) |
| `app/Http/Requests/Admin/PlanRequest.php` | Validation + shaping form data into Plan attributes |
| `app/Http/Middleware/EnsureAdmin.php` | 403 for non-admin users |
| `app/Services/Stripe/PlanSyncService.php` | Mirrors plan changes to Stripe |
| `app/Models/Plan.php` | Eloquent model + `needsStripeSync()` helper |
| `resources/views/admin/plans/*.blade.php` | Admin UI |

---

## User flow

The user starts on `/plans` (the unified pricing page) and ends at a success page after Stripe processes their payment.

```
User
  ↓ visits /plans
[ Pricing page — lists all active plans ]
  ↓ clicks a button on a plan card
  ├── Recurring plan → Cashier path  (Pattern 1a)
  └── One-time plan  → user picks: Payment Intents (1b)  |  Checkout (2a)  |  Elements (2b)
  ↓
[ Stripe processes the payment ]
  ↓
[ Success page — auto-refreshes/polls until webhook arrives ]
  ↓ asynchronously
[ Stripe webhook → /stripe/webhook → updates DB ]
```

### How the picker decides what's available

`resources/views/pricing/_plan_card.blade.php` filters buttons by `$plan->billing_type`:

- **Recurring** → only the "Subscribe via Cashier" button (Cashier is the only path that handles recurring billing properly with this app's webhook)
- **One-time** → all three: Payment Intents, Checkout, Elements
- **Plan needs sync** → all buttons hidden, replaced by "Not synced" warning

This means: regardless of which button the user clicks, the underlying flow they land on is **always compatible with the plan's billing_type**.

---

## Pattern 1a — Cashier

**Use case:** Recurring billing (monthly, yearly, etc.). User pays a fixed amount on a fixed cadence.

### Step-by-step

```
USER                                APP                              STRIPE
─────────                           ─────                            ──────
Click "Subscribe via Cashier"
        ─── POST /subscriptions/subscribe (plan_id) ──→
                                    SubscriptionController::subscribe()
                                    └─ Plan::active()->find($plan_id)
                                    └─ SubscriptionService::checkout($user, $plan)
                                       └─ $user->newSubscription('default', $plan->stripe_price_id)
                                                ->checkout([success_url, cancel_url, metadata])
                                       ────────── creates Checkout Session ────→ Stripe creates session
                                    ←──────────────── Cashier Checkout (URL) ───
        ←─── 302 redirect to checkout.stripe.com/c/pay/cs_test_... ───

Stripe-hosted checkout page
        ─── enters card, clicks Pay ──→
                                                                     Stripe processes payment
                                                                     Stripe creates subscription
        ←─── 302 redirect to /subscriptions/success?session_id=... ───
                                    SubscriptionController::success()
                                    └─ view shows "Activating..." (auto-polls)

                                                                     ── webhook: checkout.session.completed ──→
                                                                     ── webhook: customer.subscription.created ──→
                                                                     ── webhook: invoice.paid ──→
                                                                     ── webhook: payment_intent.succeeded ──→
                                    Cashier WebhookController processes each:
                                    └─ creates `customer` columns on user
                                    └─ creates `subscriptions` row
                                    └─ creates `subscription_items` row(s)

User refreshes / poll fires
        ←─── view re-renders with active subscription ───
```

### Why we use `$user->newSubscription()` and not `Stripe\Subscription::create()` directly

`Laravel\Cashier\Billable::newSubscription()` returns a `SubscriptionBuilder`. It:
- Auto-creates the Stripe Customer if the user doesn't have one
- Stores `stripe_id` on the `users` table (the customer column block from migration `2026_04_28_080846`)
- After the webhook, populates `subscriptions` + `subscription_items` automatically — we don't write code for that

That's why the `subscriptions` table fills in *after* the webhook arrives, not at the moment of redirect.

### Files involved

| File | Purpose |
|---|---|
| `app/Http/Controllers/SubscriptionController.php` | Action handlers |
| `app/Services/Stripe/SubscriptionService.php` | Wraps `newSubscription()->checkout()` |
| `app/Models/User.php` | Uses `Billable` trait |
| `vendor/laravel/cashier/...` | Cashier handles all DB sync via webhooks |

---

## Pattern 1b — Payment Intents

**Use case:** One-time payment with maximum UX control. Card form lives on **our** page, not Stripe's.

### Step-by-step

```
USER                                APP                              STRIPE
─────────                           ─────                            ──────
Click "Pay with Payment Intents"
        ─── POST /plans/{plan}/pay/intent ──→
                                    PaymentIntentController::startForPlan($plan)
                                    └─ guards: must be active, one-time, synced
                                    └─ PaymentIntentService::createForPlan($user, $plan)
                                       DB::transaction:
                                       └─ Order::create(status=pending, type=payment_intent)
                                       └─ Stripe::paymentIntents->create(amount, currency, metadata)
                                                            ─────────────→ Stripe creates PaymentIntent
                                                            ←──────────── PI with client_secret
                                       └─ $order->update(stripe_payment_intent_id)
                                    └─ render view 'payments.intent-plan' with $clientSecret
        ←─── HTML page with Stripe Payment Element ───

[Page loads, JS mounts Payment Element]
PaymentIntentFlow.initWithSecret({clientSecret, returnUrl})
        ─ enters card, clicks "Pay" ─→
                                    JS: stripe.confirmPayment({elements, confirmParams: {return_url}})
                                                            ─────────────→ Stripe charges card
        ←─── 302 redirect to /pay/intent/success/{order:uuid} ───
                                    PaymentIntentController::success($order)
                                    └─ view shows "Processing..." + polls /orders/{uuid}/status

                                                                     ── webhook: payment_intent.succeeded ──→
                                    WebhookEventHandler::handlePaymentIntentSucceeded()
                                    DB::transaction:
                                    └─ $order->markAsPaid()  → status=paid, paid_at=now
                                    └─ Payment::create()      → 'payments' row with last4, method type
                                    OrderPaid::dispatch($order)

[Browser polls /orders/{uuid}/status]
        ←─── { status: "paid" } ───
[Success view re-renders with confirmation]
```

### Where the `client_secret` comes from

`Stripe\PaymentIntent` returns a `client_secret` field — that's the magic token that lets the browser confirm the payment **directly with Stripe** without our backend handling card data. Our server only sees the `client_secret`, never the card number.

### Why we create the Order **before** the PaymentIntent

We need an `order_uuid` to embed in the PaymentIntent's metadata (`order_uuid`, `user_id`, `plan_id`, `plan_slug`). When the webhook arrives later, we use `metadata.order_uuid` to find the local Order row. If the order didn't exist yet, the webhook would have nothing to attach to.

The whole thing is wrapped in `DB::transaction` so if the Stripe call fails, the Order row is rolled back — no orphan pending orders.

---

## Pattern 2a — Stripe Checkout

**Use case:** One-time payment with the fastest possible integration. Stripe hosts the entire payment page.

### Step-by-step

```
USER                                APP                              STRIPE
─────────                           ─────                            ──────
Click "Pay with Stripe Checkout"
        ─── POST /plans/{plan}/pay/checkout ──→
                                    CheckoutController::startForPlan($plan)
                                    └─ guards: must be active, one-time, synced
                                    └─ CheckoutService::startForPlan($user, $plan)
                                       DB::transaction:
                                       └─ Order::create(status=pending, type=checkout)
                                       └─ Stripe::checkout->sessions->create([
                                              mode: 'payment',
                                              line_items: [inline price_data],
                                              success_url: /pay/checkout/success/{uuid},
                                              cancel_url:  /pay/checkout/cancel,
                                              metadata: {order_uuid, user_id, plan_id}
                                          ])
                                                            ─────────────→ Stripe creates Session
                                                            ←──────────── Session with .url
                                       └─ $order->update(stripe_checkout_session_id)
        ←─── 303 redirect to checkout.stripe.com/c/pay/cs_test_... ───

[Stripe-hosted checkout page]
        ─── enters card, clicks Pay ──→
                                                                     Stripe processes payment
        ←─── 303 redirect to /pay/checkout/success/{uuid} ───
                                    CheckoutController::success($order)
                                    └─ view shows "Processing..." + polls

                                                                     ── webhook: checkout.session.completed ──→
                                    WebhookEventHandler::handleCheckoutSessionCompleted()
                                    DB::transaction:
                                    └─ $order->update(stripe_payment_intent_id)
                                    └─ $order->markAsPaid()
                                    └─ Payment::create()
                                    OrderPaid::dispatch($order)

[Browser polls /orders/{uuid}/status]
        ←─── { status: "paid" } ───
[Success view re-renders]
```

### Why inline `price_data` instead of pre-created Stripe Prices

`CheckoutService::startForPlan()` uses inline `price_data` (passing name + amount + currency directly in the line item) rather than referencing the Plan's `stripe_price_id`. This:

- Demonstrates that Stripe Checkout works **even without pre-created Prices** (good reference-app value)
- Keeps the one-time Checkout flow self-contained — you could delete every Price from Stripe and this still works
- Trade-off: line items don't appear under the Plan's Product in Stripe Dashboard reporting

If you want full reporting, swap inline `price_data` for `'price' => $plan->stripe_price_id` in `CheckoutService::startForPlan()`.

### Why `redirect()->away($session->url, 303)`

RFC 7231: a redirect after a POST should use 303 (See Other) so the next request is forced to GET. Any other status (302, 307) leaves browser behavior up to the browser.

---

## Pattern 2b — Stripe Elements

**Use case:** One-time payment with branded card field (matches your Bootstrap inputs). Like 1b but uses the simpler **Card Element** instead of the multi-method Payment Element.

### Step-by-step

```
USER                                APP                              STRIPE
─────────                           ─────                            ──────
Click "Pay with Stripe Elements"
        ─── GET /plans/{plan}/pay/elements ──→
                                    ElementsController::showForPlan($plan)
                                    └─ guards: must be active, one-time, synced
                                    └─ PaymentIntentService::createForPlan(
                                          $user, $plan, OrderType::Elements
                                      )
                                       (same DB transaction as Pattern 1b)
                                    └─ render view 'payments.elements-plan' with $clientSecret
        ←─── HTML page with Stripe Card Element ───

[Page loads, JS mounts Card Element]
ElementsFlow.init({clientSecret, returnUrl})
        ─ enters card, clicks Pay ──→
                                    JS: stripe.confirmCardPayment(clientSecret, {
                                            payment_method: {card, billing_details: {name}}
                                        })
                                                            ─────────────→ Stripe charges card
        ←─── redirect to /pay/elements/success/{uuid} ───

[Same webhook + polling flow as Pattern 1b]
```

### Difference from Pattern 1b

Both use `PaymentIntentService::createForPlan()` on the backend. They differ on the frontend:

| | 1b — Payment Intents | 2b — Stripe Elements |
|---|---|---|
| Element type | `elements.create('payment')` | `elements.create('card')` |
| Methods supported | Card + Apple Pay + Google Pay + Link + iDEAL + ... (Stripe decides) | Card only |
| Visual style | Stripe-controlled (themable) | Bare card input — fully styleable to match your design system |
| Order type | `OrderType::PaymentIntent` | `OrderType::Elements` |
| Best for | "I want maximum payment method coverage" | "I want full design control over the card field" |

---

## The unified webhook

**Endpoint:** `POST /stripe/webhook` — no auth, no CSRF (Stripe signs requests with a secret).

**Controller:** `App\Http\Controllers\WebhookController` (extends Cashier's `WebhookController`).

### Why "unified"

All four payment patterns above eventually result in Stripe sending one of these events:

| Event | Triggered by | Handler |
|---|---|---|
| `payment_intent.succeeded` | 1b, 2b, and the PI inside 2a | `WebhookEventHandler::handlePaymentIntentSucceeded()` |
| `payment_intent.payment_failed` | 1b, 2b | `WebhookEventHandler::handlePaymentIntentFailed()` |
| `checkout.session.completed` | 2a | `WebhookEventHandler::handleCheckoutSessionCompleted()` |
| `customer.subscription.created` | 1a | Cashier's parent handler — writes `subscriptions` table |
| `customer.subscription.updated` | 1a | Cashier's parent handler |
| `customer.subscription.deleted` | 1a | Cashier's parent handler |
| `invoice.paid` | 1a | Cashier's parent handler |
| `invoice.payment_failed` | 1a | Cashier's parent handler |

The same controller class handles **all** of them. Cashier events fall through to `parent::handle*` (Cashier's own logic). Our custom events (PaymentIntent / Checkout) are dispatched to `WebhookEventHandler`.

### Idempotency

Every event is recorded in `webhook_events` (a table with the Stripe event ID as its unique key) before processing. If Stripe re-delivers an event (which it does on transient failures), we see we've already processed it and skip. This is why the handlers also check `if ($order->isPaid()) return;` defensively.

### Signature verification

Stripe signs every webhook with `STRIPE_WEBHOOK_SECRET`. Cashier's `VerifyWebhookSignature` middleware (auto-applied by the parent controller) rejects any request without a valid signature. **Don't** disable this; without it, anyone could POST fake "payment succeeded" events to your endpoint.

---

## Database tables

| Table | Written by | Contents |
|---|---|---|
| `users` | Auth + Cashier | Standard Laravel user; Cashier adds `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at` |
| `plans` | Admin via `PlanController` | The catalogue — every product/price the admin has defined |
| `orders` | `PaymentIntentService` / `CheckoutService` (one-time flows) | A pending → paid record for each one-time purchase. UUID-keyed for safe public URLs. |
| `order_items` | `CheckoutService::start()` (product flow only — not used by plan flow) | Line item snapshot |
| `payments` | `WebhookEventHandler` | One row per successful or failed payment attempt |
| `subscriptions` | Cashier's webhook handler | One row per Stripe Subscription |
| `subscription_items` | Cashier's webhook handler | One row per `Subscription Item` (price within a subscription) |
| `webhook_events` | `WebhookController::handleWebhook()` | Idempotency log of every Stripe event we've seen |
| `customer_columns` (migration only) | n/a — the columns are added to `users` | Cashier customer fields |

### What writes when, summarized

| Action | DB writes (immediate) | DB writes (after webhook) |
|---|---|---|
| Admin creates plan | `plans` row | — |
| Admin syncs plan | `plans.stripe_*_id` updated | — |
| User Cashier-subscribes | — | `users.stripe_id`, `subscriptions`, `subscription_items` |
| User Payment Intent | `orders` (pending) | `orders.status=paid`, `payments` |
| User Stripe Checkout | `orders` (pending) | `orders.status=paid`, `orders.stripe_payment_intent_id`, `payments` |
| User Stripe Elements | `orders` (pending) | `orders.status=paid`, `payments` |

The "pending → paid" gap is what the success-page poller (`/orders/{uuid}/status`) bridges. It refreshes the success view until the webhook updates the row.

---

## Common operations cheatsheet

### Quickly add a $9 monthly plan and make it purchasable

1. Log in as `admin@example.com`
2. **Admin · Plans** → **+ New Plan**
3. Name: `Pro`, Billing type: `Recurring`, Amount: `9`, Interval: `month`, Active: ✓
4. **Create plan** — DB row created, Stripe Product + Price created, `stripe_*_id` populated
5. Done. Visit `/plans` (in another browser as `test@example.com`) — the plan appears with a **Subscribe via Cashier** button.

### Reset a plan back to its REPLACE_ME state (e.g. for testing the sync button)

```sql
UPDATE plans
SET stripe_price_id = 'price_starter_monthly_REPLACE_ME', stripe_product_id = NULL
WHERE name = 'Starter Monthly';
```

The card on `/plans` will now show "Not synced" and the admin index will show a yellow **Sync now** button.

### Change a plan's price safely

Edit the plan, change the **Amount** field, hit **Save changes**. The old Stripe Price is archived (existing subscribers keep their old price); a new Stripe Price is created and saved as `stripe_price_id`. Future signups use the new price.

### Cancel a user's subscription cleanly

User-side: `/subscriptions/manage` → **Cancel**.

Admin-side: not exposed in the UI. The Cashier method `$user->subscription()->cancel()` sets `ends_at` to the current period end. The user keeps access until then.

### Delete a plan that has subscribers

Not possible — the controller blocks it. **Deactivate** the plan instead. New users won't see it; existing subscribers continue uninterrupted on Stripe's archived price.

### Inspect what's in Stripe vs what's in DB

```sql
SELECT id, name, stripe_product_id, stripe_price_id, is_active FROM plans;
```

Then check `https://dashboard.stripe.com/test/products` — the IDs should match.

---

## Where to look in the code

```
Admin flow
├─ app/Http/Controllers/Admin/PlanController.php   ← CRUD + toggle + sync actions
├─ app/Http/Requests/Admin/PlanRequest.php         ← validation
├─ app/Http/Middleware/EnsureAdmin.php             ← 403 for non-admins
└─ app/Services/Stripe/PlanSyncService.php         ← Stripe mirror

User flow — entry
├─ app/Http/Controllers/PricingController.php      ← /plans
├─ resources/views/pricing/index.blade.php
└─ resources/views/pricing/_plan_card.blade.php    ← method picker per card

User flow — Cashier (1a)
├─ app/Http/Controllers/SubscriptionController.php
└─ app/Services/Stripe/SubscriptionService.php

User flow — Payment Intents (1b) and Elements (2b)
├─ app/Http/Controllers/PaymentIntentController.php
├─ app/Http/Controllers/ElementsController.php
└─ app/Services/Stripe/PaymentIntentService.php    ← shared by 1b and 2b

User flow — Stripe Checkout (2a)
├─ app/Http/Controllers/CheckoutController.php
└─ app/Services/Stripe/CheckoutService.php

Webhook (all four)
├─ app/Http/Controllers/WebhookController.php
└─ app/Services/Stripe/WebhookEventHandler.php

Cross-cutting
├─ app/Models/Plan.php                             ← needsStripeSync(), isRecurring()
├─ app/Models/Order.php                            ← UUID, markAsPaid()
├─ app/Enums/BillingType.php                       ← Recurring | OneTime
└─ app/Enums/OrderType.php                         ← PaymentIntent | Checkout | Elements
```
