<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ElementsController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\PaymentIntentController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\SubscriptionController;
use App\Models\Plan;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

// Guest-only routes (login & register)
Route::middleware('guest')->group(function () {
    Route::get('/login',     [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',    [AuthController::class, 'login'])->name('login.submit');
    Route::get('/register',  [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Unified pricing page — entry point that fans out to all four patterns (8d).
    Route::get('/plans', [PricingController::class, 'index'])->name('pricing.index');

    // Plan-aware payment entrypoints — every method picker on /plans lands here.
    Route::post('/plans/{plan}/pay/intent',   [PaymentIntentController::class, 'startForPlan'])->name('plans.pay.intent');
    Route::post('/plans/{plan}/pay/checkout', [CheckoutController::class, 'startForPlan'])->name('plans.pay.checkout');
    Route::get( '/plans/{plan}/pay/elements', [ElementsController::class, 'showForPlan'])->name('plans.pay.elements');

    // Combo 1a — Cashier subscriptions
    Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
        Route::get('/',                [SubscriptionController::class, 'index'])->name('index');
        Route::post('/subscribe',      [SubscriptionController::class, 'subscribe'])->name('subscribe');
        Route::get('/success',         [SubscriptionController::class, 'success'])->name('success');
        Route::get('/cancel',          [SubscriptionController::class, 'cancel'])->name('cancel');
        Route::get('/manage',          [SubscriptionController::class, 'manage'])->name('manage');
        Route::post('/cancel-current', [SubscriptionController::class, 'cancelCurrent'])->name('cancel-current');
        Route::post('/resume',         [SubscriptionController::class, 'resume'])->name('resume');
    });

    // Combo 1b — Payment Intents (custom one-time)
    Route::prefix('pay/intent')->name('payments.intent.')->group(function () {
        Route::get('/',                          [PaymentIntentController::class, 'show'])->name('show');
        Route::post('/',                         [PaymentIntentController::class, 'create'])->name('create');
        Route::get('/success/{order:uuid}',      [PaymentIntentController::class, 'success'])->name('success');
    });

    // Combo 2a — Stripe Checkout (hosted)
    Route::prefix('pay/checkout')->name('payments.checkout.')->group(function () {
        Route::get('/',                     [CheckoutController::class, 'index'])->name('index');
        Route::post('/start',               [CheckoutController::class, 'start'])->name('start');
        Route::get('/success/{order:uuid}', [CheckoutController::class, 'success'])->name('success');
        Route::get('/cancel',               [CheckoutController::class, 'cancel'])->name('cancel');
    });

    // Combo 2b — Stripe Elements (custom branded UI)
    Route::prefix('pay/elements')->name('payments.elements.')->group(function () {
        Route::get('/',                     [ElementsController::class, 'index'])->name('index');
        Route::get('/{product:slug}',       [ElementsController::class, 'show'])->name('show');
        Route::get('/success/{order:uuid}', [ElementsController::class, 'success'])->name('success');
    });

    // Polled by every success page to detect when the webhook flipped status.
    Route::get('/orders/{order:uuid}/status', [OrderStatusController::class, 'show'])->name('orders.status');
});

// Admin
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('plans', \App\Http\Controllers\Admin\PlanController::class)->except(['show']);
    Route::patch('plans/{plan}/toggle', [\App\Http\Controllers\Admin\PlanController::class, 'toggle'])->name('plans.toggle');
    Route::post('plans/{plan}/sync', [\App\Http\Controllers\Admin\PlanController::class, 'sync'])->name('plans.sync');
});

// Stripe webhook — no auth, no CSRF; signature is verified by Cashier's
// VerifyWebhookSignature middleware (auto-applied in WebhookController constructor).
Route::post('/stripe/webhook', [WebhookController::class, 'handleWebhook'])
    ->name('stripe.webhook');
