# Payment Gateway Integration (Laravel + Stripe)

A reference Laravel application that demonstrates **four Stripe integration patterns** working side-by-side in a single codebase, unified by **one webhook handler** for database synchronisation.

| Combo | Components | Used for |
|-------|------------|----------|
| **Combo 1** | Laravel Cashier + Stripe Payment Intents | Subscriptions (recurring) + custom one-time charges |
| **Combo 2** | Stripe Checkout + Stripe Elements | Hosted simple checkout + custom branded checkout |

---

## Table of Contents

1. [High-Level Architecture](#high-level-architecture)
2. [Approach & Engineering Decisions](#approach--engineering-decisions)
   - [Architectural Style](#1-architectural-style)
   - [Folder Layout](#2-folder-layout)
   - [Database Design](#3-database-design)
   - [Authentication](#4-authentication)
   - [Stripe Integration Approach](#5-stripe-integration-approach)
   - [Webhook Strategy (Defence in Depth)](#6-webhook-strategy-defence-in-depth)
   - [Security & Payment Gating](#7-security--payment-gating)
   - [Design Patterns Used](#8-design-patterns-used)
   - [Validation Strategy](#9-validation-strategy)
   - [Money & Currency Handling](#10-money--currency-handling)
   - [Frontend Stack](#11-frontend-stack)
3. [Flow 1 — Cashier (Subscriptions)](#flow-1--cashier-subscriptions)
4. [Flow 2 — Payment Intents (Custom One-time)](#flow-2--payment-intents-custom-one-time)
5. [Flow 3 — Stripe Checkout (Hosted)](#flow-3--stripe-checkout-hosted)
6. [Flow 4 — Stripe Elements (Custom UI)](#flow-4--stripe-elements-custom-ui)
7. [Unified Webhook Flow (DB Sync)](#unified-webhook-flow-db-sync)
8. [Benefits of Mixing Both Combos](#benefits-of-mixing-both-combos)
9. [When to Use Which](#when-to-use-which)
10. [Implementation Roadmap](#implementation-roadmap)
11. [Setup](#setup)
12. [Testing the Four Combos](#testing-the-four-combos)

---

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                       LARAVEL APPLICATION                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────────┐    ┌──────────────────────────┐   │
│  │   COMBO 1                │    │   COMBO 2                │   │
│  │   Server-driven flows    │    │   Frontend-driven flows  │   │
│  ├──────────────────────────┤    ├──────────────────────────┤   │
│  │ Cashier → Subscriptions  │    │ Checkout → Hosted page   │   │
│  │ PaymentIntents → 1-time  │    │ Elements → Custom UI     │   │
│  └────────────┬─────────────┘    └────────────┬─────────────┘   │
│               │                                │                 │
│               └────────────────┬───────────────┘                 │
│                                ▼                                 │
│                  ┌─────────────────────────┐                     │
│                  │   STRIPE WEBHOOK        │                     │
│                  │   /stripe/webhook       │                     │
│                  │   (single source of     │                     │
│                  │    truth for DB sync)   │                     │
│                  └────────────┬────────────┘                     │
│                               ▼                                  │
│                  ┌─────────────────────────┐                     │
│                  │   MySQL  (orders,       │                     │
│                  │   subscriptions, users) │                     │
│                  └─────────────────────────┘                     │
└─────────────────────────────────────────────────────────────────┘
```

---

## Approach & Engineering Decisions

This is the engineering rationale behind every choice in the project. Read this section if you want to understand **why** the code is shaped the way it is — not just what it does.

### 1. Architectural Style

Layered architecture with strict separation:

```
HTTP layer        →  Controllers   (thin: route in, response out)
Validation layer  →  Form Requests
Business layer    →  Services      (one per concern)
Domain layer      →  Models + Enums + Events
Persistence layer →  Migrations + Eloquent
Infrastructure    →  Stripe SDK behind an Interface
```

**Rule:** A controller never touches Stripe directly. It calls a service. The service implements an interface. The interface can be swapped (Razorpay, PayPal, mock for tests).

---

### 2. Folder Layout

```
app/
  Contracts/
    PaymentGatewayInterface.php          ← contract
  Services/
    Stripe/
      StripePaymentGateway.php           ← implements the contract
      SubscriptionService.php            ← Cashier wrapper
      PaymentIntentService.php           ← raw PI for one-time + Elements
      CheckoutService.php                ← hosted Checkout
      WebhookEventHandler.php            ← routes webhook events to handlers
    Reconciliation/
      StripeReconciler.php               ← used by cron job
  Http/
    Controllers/
      AuthController.php
      DashboardController.php
      SubscriptionController.php
      PaymentIntentController.php
      CheckoutController.php
      ElementsController.php
      WebhookController.php
      OrderStatusController.php          ← polled by success page
    Requests/
      LoginRequest.php
      RegisterRequest.php
      CreatePaymentIntentRequest.php
      CreateCheckoutSessionRequest.php
      SubscribeRequest.php
    Middleware/
      EnsurePaymentCompleted.php         ← payment gate
      EnsureSubscribed.php               ← subscription gate
  Models/
    User.php  Plan.php  Product.php  Price.php
    Order.php  OrderItem.php  Payment.php
    WebhookEvent.php
  Enums/
    OrderStatus.php  PaymentStatus.php
    OrderType.php    PaymentMethod.php
  Events/
    OrderPaid.php  OrderFailed.php
    SubscriptionStarted.php  SubscriptionCancelled.php
  Listeners/
    SendOrderConfirmationEmail.php       ← ShouldQueue
    SendSubscriptionWelcomeEmail.php     ← ShouldQueue
    GrantUserAccess.php                  ← ShouldQueue
  Jobs/
    ReconcileStripePaymentsJob.php       ← cron
  Mail/
    OrderConfirmationMail.php
    SubscriptionWelcomeMail.php
  Providers/
    PaymentServiceProvider.php           ← binds interface → implementation
    EventServiceProvider.php             ← maps events to listeners
database/
  migrations/  (cashier, plans, products, prices, orders, order_items, payments, webhook_events)
  seeders/PlanSeeder.php  ProductSeeder.php
resources/
  views/
    layouts/app.blade.php
    auth/login.blade.php  auth/register.blade.php
    dashboard.blade.php
    subscriptions/index.blade.php
    payments/intent.blade.php  payments/checkout.blade.php  payments/elements.blade.php
    payments/success.blade.php  payments/cancel.blade.php
public/
  js/
    auth-validation.js
    payment-intent.js  elements.js
  css/app.css
routes/
  web.php
  console.php  (cron schedule)
```

---

### 3. Database Design

```
users  (Laravel default + Cashier columns: stripe_id, pm_type, pm_last_four, trial_ends_at)
   │
   ├──< subscriptions          (Cashier-managed — recurring billing)
   │       └──< subscription_items
   │
   └──< orders                  (one-time purchases — Combos 1b, 2a, 2b)
           │
           ├──< order_items     (line items per order)
           └──< payments        (one order can have retries → multiple payment attempts)

products ──< prices              (catalog: a product can have multiple prices)
plans                            (subscription plans → Stripe Price IDs)
webhook_events                   (idempotency log: stripe_event_id unique)
```

**Specific choices:**

| Choice | Why |
|---|---|
| Money stored as integer **cents** + ISO currency | No float precision bugs; matches Stripe's API |
| Status fields as **PHP 8.1 enums** | Type safety + IDE autocomplete (no magic strings) |
| Soft deletes on `Order` | Audit trail; never lose a paid order |
| Foreign keys with explicit `cascadeOnDelete` / `restrictOnDelete` | Data integrity |
| Unique index on `webhook_events.stripe_event_id` | Idempotency guarantee |
| One `Order` → many `Payment` rows | Records each retry attempt |

---

### 4. Authentication

| Layer | Choice |
|---|---|
| Library | **Custom** (no Breeze / Jetstream / Fortify) |
| Routes | `/login`, `/register`, `/logout`, `/dashboard` |
| Server validation | Laravel **Form Request** classes (`LoginRequest`, `RegisterRequest`) |
| Server protection | Built-in `auth` middleware on protected routes |
| Frontend validation | **jQuery** — required-field check on submit, error span under each field, error auto-clears on `input` event |
| Form submission | jQuery AJAX (POST with CSRF token) — handles 422 by rendering server errors per field |
| Sessions | Default Laravel session driver |

---

### 5. Stripe Integration Approach

| Combo | Service used | Pattern |
|---|---|---|
| Cashier subscriptions | `SubscriptionService` → uses Cashier's `$user->newSubscription()` | Redirect to Stripe-hosted subscription checkout |
| Payment Intents (custom one-time) | `PaymentIntentService` (via `PaymentGatewayInterface`) | Backend creates PI → frontend confirms with `clientSecret` |
| Stripe Checkout (hosted) | `CheckoutService` (via interface) | Backend creates Session → 303 redirect to Stripe |
| Stripe Elements | Reuses `PaymentIntentService` | Same backend, custom card field on your page |

**Key principle:** Combo 2b (Elements) and Combo 1b (Payment Intents) **share the same service** — only the frontend differs. This proves the layering pays off.

---

### 6. Webhook Strategy (Defence in Depth)

Three layers, each doing one job:

| Layer | What | Why |
|---|---|---|
| **Real-time** | `POST /stripe/webhook` → `WebhookController` → `WebhookEventHandler` | Primary source of truth. Stripe auto-retries on non-2xx. |
| **Polling** | Success page polls `GET /orders/{id}/status` for ~10s | The redirect can beat the webhook; polling smooths the UX. |
| **Reconciliation** | `ReconcileStripePaymentsJob` scheduled daily via Laravel scheduler | Safety net for missed webhooks (server downtime, etc). |

**Idempotency mechanism:**

1. Webhook arrives → verify Stripe signature
2. Lookup `stripe_event_id` in `webhook_events` table
3. If exists → return 200 OK immediately (already processed)
4. If new → wrap in `DB::transaction`: insert event row, dispatch domain event, return 200

**Side effects via Events + queued Listeners:**

```
WebhookEventHandler
   └── dispatch(new OrderPaid($order))
        ├── SendOrderConfirmationEmail   (ShouldQueue)
        ├── GrantUserAccess              (ShouldQueue)
        └── (future: NotifySlack, etc.)
```

Adding a new side effect later = adding a listener, not editing the webhook.

> **Why not cron-only sync?** Cron would mean the user sees "Pending" until the next cron tick. Webhook is real-time; cron is the safety net, not the primary mechanism.

---

### 7. Security & Payment Gating

| Concern | How |
|---|---|
| Auth on routes | `auth` middleware |
| Payment-gated content | Custom `EnsurePaymentCompleted` middleware → checks `Order::status = Paid` in DB |
| Subscription-gated content | Custom `EnsureSubscribed` middleware → uses `$user->subscribed('default')` |
| CSRF | Laravel default + `X-CSRF-TOKEN` header for AJAX |
| Webhook security | Stripe signature verification on every request |
| Money truth | Status is **only** set by webhook code, never by client request |
| Logging | Every Stripe call + webhook event logged via Laravel `Log` channel |

The user's session never decides "did they pay?" — only the DB does, only the webhook can write it.

---

### 8. Design Patterns Used

| Pattern | Where | Why |
|---|---|---|
| Service Layer | `app/Services/*` | Business logic out of controllers |
| Dependency Injection | Controllers receive services in constructor | Testable, swappable |
| Interface (Strategy) | `PaymentGatewayInterface` | Swap gateway without rewriting callers |
| Form Request (Validation) | `app/Http/Requests/*` | Validation lives with the request, not the controller |
| Domain Events | `app/Events/*` + listeners | Decouple side effects from triggering code |
| Enum-as-state | `OrderStatus`, `PaymentStatus` | Type safety + IDE autocomplete |
| Idempotency token | `webhook_events.stripe_event_id` | Safe Stripe retries |
| Middleware (Chain of Responsibility) | `EnsurePaymentCompleted` etc. | Centralised authorisation |

**Deliberately NOT used:**

- **Abstract classes** — only added if duplication actually appears (avoid premature abstraction)
- **Repositories on top of Eloquent** — Eloquent is already an active record / repository hybrid; another layer adds noise without benefit
- **DDD aggregates / value objects** — overkill for this scope

---

### 9. Validation Strategy

| Where | What |
|---|---|
| **Frontend** (jQuery) | Required, email format, password length — quick UX feedback |
| **Backend** (Form Request) | Authoritative. Includes business rules (unique email, password ≥ 8, etc.) |
| **Why both** | Frontend = UX. Backend = security. Never trust the frontend. |

---

### 10. Money & Currency Handling

- Stored as `bigInteger` cents: `amount_cents`
- Currency: `string(3)` ISO code on every monetary row (`USD`, `INR`, etc.)
- Format helper on the Model: `$order->formatted_total` returns `$12.50`
- Stripe uses cents natively → no conversion bugs

---

### 11. Frontend Stack

| Layer | Choice |
|---|---|
| Templates | Blade |
| CSS framework | **Bootstrap 5** via CDN |
| JS | **jQuery 3.x** via CDN (auth + payment forms) + **Stripe.js v3** via Stripe's CDN (card fields) |
| Custom JS/CSS | Plain files in `public/js/` and `public/css/`, included with `{{ asset(...) }}` |
| Build step | **None — Vite is not used.** No `npm run dev`, no bundling. |
| Stripe key on frontend | Exposed via Blade: `<script>window.STRIPE_KEY = "{{ config('services.stripe.key') }}";</script>` in the layout |

**Layout skeleton** (`resources/views/layouts/app.blade.php`):
```html
<!DOCTYPE html>
<html>
<head>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
  @yield('content')

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://js.stripe.com/v3/"></script>
  <script>window.STRIPE_KEY = "{{ config('services.stripe.key') }}";</script>
  @stack('scripts')
</body>
</html>
```

---

## Flow 1 — Cashier (Subscriptions)

Recurring billing handled by Laravel Cashier. Cashier keeps the `subscriptions` and `subscription_items` tables in sync automatically via its own webhook listeners.

```
┌────────┐   1. Click "Subscribe Pro"    ┌──────────────┐
│  USER  │ ─────────────────────────────▶│  Laravel     │
└────────┘                                │  Controller  │
    ▲                                     └──────┬───────┘
    │                                            │ 2. $user->newSubscription('pro', $priceId)
    │                                            │    ->checkout()  (Cashier helper)
    │                                            ▼
    │                                     ┌──────────────┐
    │ 3. Redirect to Stripe-hosted        │   STRIPE     │
    │    subscription checkout            │              │
    │ ◀───────────────────────────────────┤              │
    │ 4. User enters card                 │              │
    │ ───────────────────────────────────▶│              │
    │ 5. Redirect → success_url           │              │
    │ ◀───────────────────────────────────┤              │
    └─────────────────────────────────────└──────┬───────┘
                                                 │ 6. Async webhook
                                                 ▼
                                          customer.subscription.created
                                          invoice.paid
                                                 │
                                                 ▼
                                          Cashier auto-syncs
                                          `subscriptions` table
```

---

## Flow 2 — Payment Intents (Custom One-time)

For one-off charges with full control over UX (donations, marketplace add-ons, course purchases). The frontend confirms the payment using a `clientSecret` issued by Laravel.

```
┌────────┐ 1. POST /pay/intent {amount}  ┌──────────────┐
│ BROWSER│ ─────────────────────────────▶│  Laravel     │
└────────┘                                │  Controller  │
                                          └──────┬───────┘
                                                 │ 2. PaymentIntent::create([...])
                                                 ▼
                                          ┌──────────────┐
                                          │   STRIPE     │
                                          └──────┬───────┘
                                                 │ 3. clientSecret returned
                                                 ▼
┌────────┐ 4. stripe.confirmCardPayment(clientSecret)
│ BROWSER│ ────────────────────────────────────▶ STRIPE
└────────┘                                          │
                                                    │ 5. payment_intent.succeeded
                                                    ▼
                                              WEBHOOK → DB update
                                              (orders.status = 'paid')
```

---

## Flow 3 — Stripe Checkout (Hosted)

The fastest path to a working payment page. Stripe hosts the entire UI, including Apple Pay, Google Pay, Link, tax, and localisation — zero frontend code on your side.

```
USER ──"Buy Now"──▶ Laravel ──Session::create()──▶ Stripe
                                                     │
USER ◀──── redirect to checkout.stripe.com ──────────┘
  │
  └── enters card on Stripe page ──▶ success_url
                                          │
                              webhook: checkout.session.completed
                                          ▼
                                   DB: orders → 'paid'
```

---

## Flow 4 — Stripe Elements (Custom UI)

Same Payment Intents backend as Flow 2, but the card field is rendered by Stripe Elements **inside your own page** — so users stay on your domain with your branding while Stripe handles PCI scope.

```
BROWSER (your form)                Laravel              Stripe
   │                                  │                    │
   │ 1. POST /create-intent ─────────▶│                    │
   │                                  │ 2. PaymentIntent ─▶│
   │ 3. clientSecret ◀────────────────│ ◀──────────────────│
   │                                                        │
   │ 4. stripe.elements() mounts card field on YOUR page    │
   │                                                        │
   │ 5. stripe.confirmPayment(clientSecret) ───────────────▶│
   │                                                        │
   │                          6. webhook payment_intent.succeeded
   │                                          ▼
   │                                   Laravel → DB sync
```

> Elements is essentially Payment Intents + a Stripe-hosted UI **widget**. Both flows share the same backend controller and webhook events.

---

## Unified Webhook Flow (DB Sync)

A **single endpoint** at `POST /stripe/webhook` is the source of truth for all four flows. Every successful payment, failed charge, or subscription change ultimately reaches the database here — even if the user closes the tab mid-payment.

```
                    ┌───────────────────────────┐
                    │   Stripe sends events     │
                    └────────────┬──────────────┘
                                 ▼
                  POST /stripe/webhook  (verify signature)
                                 │
        ┌────────────────────────┼─────────────────────────┐
        ▼                        ▼                         ▼
 checkout.session.completed   invoice.paid          payment_intent.
   (Combo 2 - Checkout)       (Combo 1 - Cashier)    succeeded/failed
        │                        │                  (Combo 1 PI / Combo 2 Elements)
        ▼                        ▼                         ▼
  orders.update()         Cashier handles           orders.update()
                          subscriptions table        + send receipt mail
                          automatically
        └────────────────────────┴─────────────────────────┘
                                 ▼
                       return 200 OK to Stripe
```

**Webhook events handled:**

| Event | Source flow | Action |
|-------|-------------|--------|
| `checkout.session.completed` | Combo 2 (Checkout) | Mark order as paid |
| `payment_intent.succeeded` | Combo 1 PI / Combo 2 Elements | Mark order as paid, send receipt |
| `payment_intent.payment_failed` | Combo 1 PI / Combo 2 Elements | Mark order as failed, notify user |
| `customer.subscription.created` | Combo 1 (Cashier) | Cashier auto-syncs |
| `customer.subscription.updated` | Combo 1 (Cashier) | Cashier auto-syncs |
| `customer.subscription.deleted` | Combo 1 (Cashier) | Cashier auto-syncs |
| `invoice.paid` | Combo 1 (Cashier) | Cashier auto-syncs, send invoice email |
| `invoice.payment_failed` | Combo 1 (Cashier) | Mark grace period, dunning email |

---

## Benefits of Mixing Both Combos

| Capability | Why you need it |
|---|---|
| **Cashier** | Laravel-native helpers (`$user->subscribed('pro')`), trial periods, proration, invoice PDFs, grace periods — all "for free". |
| **Payment Intents** | SCA/3DS compliance built-in, full control over UX for one-off charges (donations, marketplace, add-ons). |
| **Stripe Checkout** | 5-minute integration, Stripe maintains the page (Apple Pay, Google Pay, Link, localisation, tax) — perfect for MVP and low-traffic flows. |
| **Stripe Elements** | Keeps users on **your** domain & branding while Stripe still handles PCI scope — best conversion rate for primary checkout. |
| **One Webhook** | Single source of truth → DB never drifts from Stripe state, even if user closes the tab mid-payment. |

---

## When to Use Which

| Scenario | Use |
|---|---|
| Monthly/annual SaaS plans, "Pro $9/mo" | **Cashier** |
| Free trial → paid conversion | **Cashier** (built-in trial logic) |
| Upgrade / downgrade / proration | **Cashier** |
| One-time product purchase, tightly branded checkout | **Elements** |
| Donation page, course purchase, "Pay what you want" | **Payment Intents** (custom amount) |
| Quick MVP, internal tool, low-traffic page | **Checkout** (hosted) |
| Marketplace add-on / upsell after signup | **Payment Intents** (programmatic) |
| You need Apple Pay / Google Pay with zero JS work | **Checkout** |
| You need pixel-perfect branded form on your domain | **Elements** |

---

## Implementation Roadmap

The project was built incrementally — each step explained, verified, and confirmed before the next.

| # | Step | Status |
|---|---|---|
| **0** | Install `laravel/cashier` + `stripe/stripe-php`, configure `.env` | ✅ Done |
| **1** | Schema, models, relations, enums, custom auth (jQuery validation) | ✅ Done |
| **2** | Webhook foundation — interface, gateway, idempotency, events, listeners, middleware, reconciliation cron | ✅ Done |
| **3** | Combo 1a — Cashier subscriptions | ✅ Done |
| **4** | Combo 1b — Payment Intents (Payment Element) | ✅ Done |
| **5** | Combo 2a — Stripe Checkout (hosted) | ✅ Done |
| **6** | Combo 2b — Stripe Elements (Card Element, branded) | ✅ Done |
| **7** | Landing page tying all four; testing checklist; README polish | ✅ Done |

### Routes Summary (actual)

```
GET  /                                guest → /login | auth → /dashboard

# Auth (custom, no Breeze)
GET  /login                           login
POST /login                           login.submit
GET  /register                        register
POST /register                        register.submit
POST /logout                          logout                    [auth]

# Dashboard
GET  /dashboard                       dashboard                 [auth]

# Combo 1a — Cashier subscriptions
GET  /subscriptions                   subscriptions.index       [auth]
POST /subscriptions/subscribe         subscriptions.subscribe   [auth]
GET  /subscriptions/success           subscriptions.success     [auth]
GET  /subscriptions/cancel            subscriptions.cancel      [auth]
GET  /subscriptions/manage            subscriptions.manage      [auth]
POST /subscriptions/cancel-current    subscriptions.cancel-current [auth]
POST /subscriptions/resume            subscriptions.resume      [auth]

# Combo 1b — Payment Intents
GET  /pay/intent                      payments.intent.show      [auth]
POST /pay/intent                      payments.intent.create    [auth]
GET  /pay/intent/success/{order}      payments.intent.success   [auth]

# Combo 2a — Stripe Checkout (hosted)
GET  /pay/checkout                    payments.checkout.index   [auth]
POST /pay/checkout/start              payments.checkout.start   [auth]
GET  /pay/checkout/success/{order}    payments.checkout.success [auth]
GET  /pay/checkout/cancel             payments.checkout.cancel  [auth]

# Combo 2b — Stripe Elements
GET  /pay/elements                    payments.elements.index   [auth]
GET  /pay/elements/{product}          payments.elements.show    [auth]
GET  /pay/elements/success/{order}    payments.elements.success [auth]

# Order status (polled by every success page)
GET  /orders/{order}/status           orders.status             [auth]

# Stripe webhook (CSRF-exempt, signature-verified by Cashier middleware)
POST /stripe/webhook                  stripe.webhook
```

26 application routes total. The `paid` and `subscribed` middleware aliases are registered globally and ready for use on any route that needs payment-gating.

---

## Setup

> No `npm install` / Vite — frontend assets come from CDN (Bootstrap 5, jQuery 3, Stripe.js).

### 1. Install PHP dependencies

```bash
composer install
```

### 2. Environment

Copy `.env.example` → `.env` and fill in:

```env
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_DATABASE=payment_gateways
DB_USERNAME=root
DB_PASSWORD=

# Stripe (test mode keys from https://dashboard.stripe.com/test/apikeys)
STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=                # filled in step 6 below

# Cashier
CASHIER_CURRENCY=usd
CASHIER_CURRENCY_LOCALE=en
```

### 3. Database

```bash
php artisan key:generate
php artisan migrate
php artisan db:seed                   # seeds 2 plans, 3 products, 1 test user
```

### 4. Stripe Products (only required for Combos 1a & 2a/2b)

The seeder uses placeholder `stripe_price_id` values for plans. For Combo 1a (subscriptions) you must replace them with real Stripe Price IDs:

1. Go to https://dashboard.stripe.com/test/products → create `Starter Monthly` ($9.99/month) and `Pro Monthly` ($29.99/month)
2. Copy each Price ID (starts with `price_…`)
3. `UPDATE plans SET stripe_price_id = 'price_xxx' WHERE slug = 'starter-monthly';` (and likewise for `pro-monthly`)

Combos 1b / 2a / 2b do **not** require Stripe products — they use inline `price_data` or arbitrary amounts.

### 5. Run the dev server

```bash
php artisan serve                     # http://127.0.0.1:8000
```

### 6. Stripe webhook listener (separate terminal)

```bash
stripe listen --forward-to localhost:8000/stripe/webhook
```

Copy the `whsec_…` it prints → paste as `STRIPE_WEBHOOK_SECRET` in `.env`.

### 7. Queue worker (separate terminal — runs the listeners)

```bash
php artisan queue:work
```

Required for: order confirmation emails, `GrantUserAccess`, `NotifyOrderFailed`. Without this the listeners just sit in the `jobs` table.

---

## Testing the Four Combos

Every combo can be tested with Stripe's test card `4242 4242 4242 4242` (any future date, any CVC, any zip).

> **Pre-flight:** `php artisan serve` ✓ · `stripe listen --forward-to ...` ✓ · `php artisan queue:work` ✓

### Combo 1a — Cashier subscriptions

1. Make sure plans have real Stripe Price IDs (Setup step 4).
2. Register at `/register` → land on `/dashboard`.
3. Click **Combo 1a → Choose plan** → `/subscriptions`.
4. Click **Subscribe with Stripe** → redirected to Stripe-hosted checkout.
5. Pay with `4242…` → redirected back to `/subscriptions/success`.
6. Page auto-refreshes every 3s until webhook fires `customer.subscription.created` → Cashier writes the `subscriptions` row → success page shows **active**.
7. Verify: `SELECT * FROM subscriptions; SELECT type, processed_at FROM webhook_events;`
8. Test cancel: `/subscriptions/manage` → Cancel → grace period UI → Resume.

### Combo 1b — Payment Intents

1. Click **Combo 1b → Pay any amount** → `/pay/intent`.
2. Enter $19 → **Continue** → backend creates Order + PaymentIntent → returns `client_secret`.
3. Stripe Payment Element mounts. Pay with `4242…`.
4. Stripe redirects to `/pay/intent/success/{order_uuid}` which polls `/orders/{uuid}/status` every 1.5s.
5. Within ~3s the webhook fires `payment_intent.succeeded` → handler marks order paid → polling sees `status=paid` → success view.
6. Verify: `SELECT type, status, amount_cents, paid_at FROM orders ORDER BY id DESC LIMIT 3;`

### Combo 2a — Stripe Checkout

1. Click **Combo 2a → Browse products** → `/pay/checkout`.
2. Click **Buy with Stripe Checkout** on a product → 303 redirect to `checkout.stripe.com/...`.
3. Pay with `4242…` → Stripe redirects to `/pay/checkout/success/{uuid}`.
4. Same polling flow — webhook fires `checkout.session.completed` → page flips to ✓.
5. Verify: `SELECT * FROM orders WHERE type='checkout' ORDER BY id DESC LIMIT 1;`

### Combo 2b — Stripe Elements

1. Click **Combo 2b → Pay with Elements** → `/pay/elements`.
2. Pick a product → `/pay/elements/{slug}`.
3. PaymentIntent is created **on page load**; classic Card Element mounts (notice URL stays on `127.0.0.1`).
4. Type cardholder name + `4242…` → Pay.
5. `stripe.confirmCardPayment` succeeds → JS redirects to `/pay/elements/success/{uuid}`.
6. Same polling pattern. Webhook fires `payment_intent.succeeded` → ✓.

### Idempotency check

Trigger Stripe to re-deliver an event:
```bash
stripe events resend evt_xxx
```
Watch `webhook_events.processed_at` — only the first delivery has it set; subsequent retries return 200 with `Already processed` and no duplicate emails / DB writes.

### Reconciliation cron (manual run)

```bash
php artisan schedule:run            # or just dispatch the job:
php artisan tinker
> \App\Jobs\ReconcileStripePaymentsJob::dispatch();
```

Reads recent Stripe PaymentIntents and logs warnings for any that succeeded in Stripe but aren't `paid` in the DB.

---

## License

MIT
