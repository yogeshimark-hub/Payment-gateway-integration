<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\PaymentIntentController;
use App\Http\Controllers\SubscriptionController;
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

    // Polled by every success page to detect when the webhook flipped status.
    Route::get('/orders/{order:uuid}/status', [OrderStatusController::class, 'show'])->name('orders.status');
});

// Stripe webhook — no auth, no CSRF; signature is verified by Cashier's
// VerifyWebhookSignature middleware (auto-applied in WebhookController constructor).
Route::post('/stripe/webhook', [WebhookController::class, 'handleWebhook'])
    ->name('stripe.webhook');
