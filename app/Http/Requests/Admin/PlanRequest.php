<?php

namespace App\Http\Requests\Admin;

use App\Enums\BillingInterval;
use App\Enums\BillingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        $planId = $this->route('plan')?->id;

        return [
            'name'           => ['required', 'string', 'max:255'],
            'slug'           => ['nullable', 'string', 'max:255', Rule::unique('plans', 'slug')->ignore($planId)],
            'billing_type'   => ['required', Rule::enum(BillingType::class)],
            'amount_dollars' => ['required', 'numeric', 'min:0.50', 'max:100000'],
            'currency'       => ['required', 'string', 'size:3'],
            'interval'       => ['nullable', 'required_if:billing_type,recurring', Rule::enum(BillingInterval::class)],
            'interval_count' => ['nullable', 'integer', 'min:1', 'max:12'],
            'features'       => ['nullable', 'string', 'max:5000'],
            'is_active'      => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount_dollars.min' => 'Stripe minimum is $0.50.',
            'interval.required_if' => 'Choose a billing interval for recurring plans.',
        ];
    }

    /**
     * Shape the request data into the columns the Plan model expects.
     */
    public function dataForPlan(): array
    {
        $billingType = BillingType::from($this->input('billing_type'));
        $name        = trim((string) $this->input('name'));
        $slug        = $this->filled('slug') ? Str::slug($this->input('slug')) : Str::slug($name);

        return [
            'name'           => $name,
            'slug'           => $slug,
            'billing_type'   => $billingType,
            'amount_cents'   => (int) round($this->float('amount_dollars') * 100),
            'currency'       => strtoupper($this->input('currency', 'USD')),
            'interval'       => $billingType === BillingType::Recurring
                ? BillingInterval::from($this->input('interval'))
                : null,
            'interval_count' => $billingType === BillingType::Recurring
                ? (int) ($this->input('interval_count') ?: 1)
                : 1,
            'features'       => $this->parseFeatures(),
            'is_active'      => $this->boolean('is_active'),
        ];
    }

    private function parseFeatures(): ?array
    {
        $raw = trim((string) $this->input('features'));
        if ($raw === '') {
            return null;
        }
        $lines = preg_split('/\r?\n/', $raw);
        $features = array_values(array_filter(array_map('trim', $lines)));
        return $features ?: null;
    }
}
