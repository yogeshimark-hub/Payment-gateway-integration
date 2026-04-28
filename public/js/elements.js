/**
 * Combo 2b — Stripe Elements with classic Card Element.
 *
 * Reuses Combo 1b's backend (PaymentIntentService). The PaymentIntent is
 * created server-side when the page loads, and the clientSecret is rendered
 * directly into the Blade. We mount a single Card Element styled to match
 * Bootstrap inputs, then call stripe.confirmCardPayment on submit.
 *
 * On success we navigate to returnUrl (the success polling page).
 */
window.ElementsFlow = (function ($) {
    'use strict';

    let stripe = null;
    let card   = null;

    const cardStyle = {
        base: {
            color:        '#212529',
            fontFamily:   'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
            fontSize:     '16px',
            '::placeholder': { color: '#6c757d' },
        },
        invalid: {
            color:     '#dc3545',
            iconColor: '#dc3545',
        },
    };

    function setBusy(busy) {
        $('#pay-btn').prop('disabled', busy);
        $('#pay-btn-text').toggleClass('d-none', busy);
        $('#pay-btn-spinner').toggleClass('d-none', !busy);
    }

    function showError(message) {
        $('#card-errors').text(message || '');
    }

    async function handleSubmit(e, opts) {
        e.preventDefault();
        showError('');
        setBusy(true);

        const result = await stripe.confirmCardPayment(opts.clientSecret, {
            payment_method: {
                card: card,
                billing_details: {
                    name: $('#card-name').val() || undefined,
                },
            },
        });

        if (result.error) {
            setBusy(false);
            showError(result.error.message);
            return;
        }

        if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
            window.location.href = opts.returnUrl;
            return;
        }

        // requires_action / processing — stripe.js may handle the redirect itself,
        // otherwise just navigate to the success poller and let it wait.
        window.location.href = opts.returnUrl;
    }

    function init(opts) {
        if (! window.STRIPE_KEY) {
            showError('Stripe publishable key is missing. Set STRIPE_KEY in .env.');
            return;
        }

        stripe = Stripe(window.STRIPE_KEY);
        const elements = stripe.elements();
        card = elements.create('card', { style: cardStyle, hidePostalCode: false });
        card.mount('#card-element');

        card.on('change', function (event) {
            showError(event.error ? event.error.message : '');
        });

        $('#elements-form').on('submit', function (e) { handleSubmit(e, opts); });
    }

    return { init: init };
})(jQuery);
