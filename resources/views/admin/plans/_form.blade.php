{{-- Shared form fields used by create + edit --}}

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Please fix the errors below.</strong>
    </div>
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" id="name"
               class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $plan->name) }}" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="slug" class="form-label">Slug <small class="text-muted">(optional — auto from name)</small></label>
        <input type="text" name="slug" id="slug"
               class="form-control @error('slug') is-invalid @enderror"
               value="{{ old('slug', $plan->slug) }}" placeholder="my-plan-slug">
        @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label d-block">Billing type <span class="text-danger">*</span></label>
        @php
            $billingValue = old('billing_type', $plan->billing_type?->value ?? 'recurring');
        @endphp
        <div class="btn-group" role="group" aria-label="Billing type">
            <input type="radio" class="btn-check" name="billing_type" id="bt-recurring"
                   value="recurring" autocomplete="off" {{ $billingValue === 'recurring' ? 'checked' : '' }}>
            <label class="btn btn-outline-primary" for="bt-recurring">Recurring (subscription)</label>

            <input type="radio" class="btn-check" name="billing_type" id="bt-onetime"
                   value="one_time" autocomplete="off" {{ $billingValue === 'one_time' ? 'checked' : '' }}>
            <label class="btn btn-outline-warning" for="bt-onetime">One-time</label>
        </div>
        @error('billing_type') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="amount_dollars" class="form-label">Amount (USD) <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" step="0.01" min="0.50" max="100000"
                   name="amount_dollars" id="amount_dollars"
                   class="form-control @error('amount_dollars') is-invalid @enderror"
                   value="{{ old('amount_dollars', $plan->amount_cents ? number_format($plan->amount_cents / 100, 2, '.', '') : '') }}" required>
            @error('amount_dollars') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="col-md-2">
        <label for="currency" class="form-label">Currency</label>
        <select name="currency" id="currency" class="form-select">
            <option value="USD" {{ old('currency', $plan->currency ?? 'USD') === 'USD' ? 'selected' : '' }}>USD</option>
            <option value="EUR" {{ old('currency', $plan->currency ?? '') === 'EUR' ? 'selected' : '' }}>EUR</option>
            <option value="INR" {{ old('currency', $plan->currency ?? '') === 'INR' ? 'selected' : '' }}>INR</option>
            <option value="GBP" {{ old('currency', $plan->currency ?? '') === 'GBP' ? 'selected' : '' }}>GBP</option>
        </select>
    </div>

    <div class="col-md-3 recurring-only">
        <label for="interval" class="form-label">Interval</label>
        <select name="interval" id="interval" class="form-select @error('interval') is-invalid @enderror">
            <option value="month" {{ old('interval', $plan->interval?->value) === 'month' ? 'selected' : '' }}>month</option>
            <option value="year"  {{ old('interval', $plan->interval?->value) === 'year'  ? 'selected' : '' }}>year</option>
        </select>
        @error('interval') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3 recurring-only">
        <label for="interval_count" class="form-label">Every X interval(s)</label>
        <input type="number" min="1" max="12" name="interval_count" id="interval_count"
               class="form-control" value="{{ old('interval_count', $plan->interval_count ?? 1) }}">
    </div>

    <div class="col-12">
        <label for="features" class="form-label">Features <small class="text-muted">(one per line)</small></label>
        <textarea name="features" id="features" rows="4"
                  class="form-control @error('features') is-invalid @enderror">{{ old('features', is_array($plan->features) ? implode("\n", $plan->features) : '') }}</textarea>
        @error('features') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                   value="1" {{ old('is_active', $plan->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Active (visible to users)</label>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(function () {
        function toggleRecurringFields() {
            const isRecurring = $('input[name="billing_type"]:checked').val() === 'recurring';
            $('.recurring-only').toggle(isRecurring);
            $('#interval').prop('required', isRecurring);
        }
        $('input[name="billing_type"]').on('change', toggleRecurringFields);
        toggleRecurringFields();
    });
</script>
@endpush
