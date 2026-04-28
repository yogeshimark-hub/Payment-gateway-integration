/**
 * Combo 1b — Payment Intents flow.
 *
 * Step 1: user submits amount form → AJAX creates PI → returns clientSecret.
 * Step 2: mount Stripe Payment Element with clientSecret → user submits card.
 * Step 3: stripe.confirmPayment redirects to return_url with payment_intent params.
 *         The success page then polls /orders/{uuid}/status until status=paid.
 */
window.PaymentIntentFlow = (function ($) {
    'use strict';

    let stripe       = null;
    let elements     = null;
    let returnUrl    = null;

    function clearError($field) {
        $field.removeClass('is-invalid');
        $field.siblings('.invalid-feedback').text('');
    }

    function showError($field, message) {
        $field.addClass('is-invalid');
        $field.siblings('.invalid-feedback').text(message);
    }

    function showPaymentError(message) {
        $('#payment-error').removeClass('d-none').text(message);
    }

    function setPayBusy(busy) {
        $('#pay-btn').prop('disabled', busy);
        $('#pay-btn-text').toggleClass('d-none', busy);
        $('#pay-btn-spinner').toggleClass('d-none', !busy);
    }

    async function handleAmountSubmit(e, opts) {
        e.preventDefault();
        const $form = $(opts.amountForm);
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.invalid-feedback').text('');

        try {
            const res = await $.ajax({
                url:     opts.createUrl,
                method:  'POST',
                data:    $form.serialize(),
                headers: { 'Accept': 'application/json' },
            });

            // Show card section, hide amount section
            $(opts.amountSection).hide();
            $(opts.cardSection).show();
            $('#order-uuid-display').text(res.order_uuid.substring(0, 8) + '…');
            $('#amount-display').text('$' + Number($form.find('#amount_dollars').val()).toFixed(2));

            // Mount Stripe Payment Element
            stripe    = Stripe(window.STRIPE_KEY);
            elements  = stripe.elements({ clientSecret: res.client_secret });
            returnUrl = res.return_url;

            const paymentElement = elements.create('payment');
            paymentElement.mount('#payment-element');

            $('#payment-form').on('submit', handleCardSubmit);
        } catch (xhr) {
            if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                $.each(xhr.responseJSON.errors, function (field, msgs) {
                    showError($form.find('[name="' + field + '"]'), msgs[0]);
                });
            } else {
                alert('Could not start payment. Please try again.');
            }
        }
    }

    async function handleCardSubmit(e) {
        e.preventDefault();
        $('#payment-error').addClass('d-none');
        setPayBusy(true);

        const { error } = await stripe.confirmPayment({
            elements: elements,
            confirmParams: {
                return_url: window.location.origin + returnUrl,
            },
        });

        // Only reached if redirect didn't happen — i.e. immediate validation error
        setPayBusy(false);
        if (error) {
            showPaymentError(error.message || 'Payment failed.');
        }
    }

    function init(opts) {
        const $form = $(opts.amountForm);
        $form.on('input change', 'input', function () { clearError($(this)); });
        $form.on('submit', function (e) { handleAmountSubmit(e, opts); });
    }

    /**
     * Single-step variant for the plan-based flow: PaymentIntent is already
     * created server-side, so we mount the Payment Element on page load and
     * just confirm on submit.
     */
    function initWithSecret(opts) {
        stripe    = Stripe(window.STRIPE_KEY);
        elements  = stripe.elements({ clientSecret: opts.clientSecret });
        returnUrl = opts.returnUrl;

        const paymentElement = elements.create('payment');
        paymentElement.mount('#payment-element');

        $('#payment-form').on('submit', handleCardSubmit);
    }

    return { init: init, initWithSecret: initWithSecret };
})(jQuery);
