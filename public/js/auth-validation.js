/**
 * Reusable jQuery form validation + AJAX submit.
 *
 * Usage:
 *   AuthValidation.bind('#login-form', ['email', 'password']);
 *
 * Behaviour:
 *   - on submit  → check listed required fields, mark .is-invalid + show .invalid-feedback
 *   - on input   → clear .is-invalid + .invalid-feedback for that field
 *   - if all OK  → AJAX POST (form.serialize) with CSRF + Accept: application/json
 *   - 422        → render server-side errors per field
 *   - 200        → window.location = response.redirect
 */
window.AuthValidation = (function ($) {
    'use strict';

    function clearError($field) {
        $field.removeClass('is-invalid');
        $field.siblings('.invalid-feedback').text('');
    }

    function showError($field, message) {
        $field.addClass('is-invalid');
        $field.siblings('.invalid-feedback').text(message);
    }

    function clearAllErrors($form) {
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.invalid-feedback').text('');
    }

    function validateRequired($form, fields) {
        let firstInvalid = null;
        fields.forEach(function (name) {
            const $field = $form.find('[name="' + name + '"]');
            if (! $field.val() || $field.val().toString().trim() === '') {
                showError($field, 'This field is required.');
                if (! firstInvalid) firstInvalid = $field;
            }
        });
        if (firstInvalid) firstInvalid.trigger('focus');
        return ! firstInvalid;
    }

    function bind(selector, requiredFields) {
        const $form = $(selector);
        if (! $form.length) return;

        // clear error as user types/changes
        $form.on('input change', 'input, select, textarea', function () {
            clearError($(this));
        });

        $form.on('submit', function (e) {
            e.preventDefault();
            clearAllErrors($form);

            if (! validateRequired($form, requiredFields)) return;

            const $submit = $form.find('button[type="submit"]');
            const originalText = $submit.text();
            $submit.prop('disabled', true).text('Please wait…');

            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: $form.serialize(),
                headers: { 'Accept': 'application/json' },
            })
                .done(function (res) {
                    if (res.redirect) window.location.href = res.redirect;
                })
                .fail(function (xhr) {
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        $.each(xhr.responseJSON.errors, function (field, messages) {
                            showError($form.find('[name="' + field + '"]'), messages[0]);
                        });
                    } else {
                        alert('Something went wrong. Please try again.');
                    }
                })
                .always(function () {
                    $submit.prop('disabled', false).text(originalText);
                });
        });
    }

    return { bind: bind };
})(jQuery);
