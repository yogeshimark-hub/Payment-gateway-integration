<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount_dollars' => ['required', 'numeric', 'between:1,1000'],
            'note'           => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount_dollars.required' => 'Please enter an amount.',
            'amount_dollars.numeric'  => 'Amount must be a number.',
            'amount_dollars.between'  => 'Amount must be between $1 and $1000.',
        ];
    }

    public function amountCents(): int
    {
        return (int) round($this->float('amount_dollars') * 100);
    }
}
