<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_email' => ['required', 'email', 'exists:users,email'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'description' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'min:10', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_email.exists' => 'Recipient user not found',
            'amount.min' => 'Transfer amount must be at least $0.01',
            'amount.max' => 'Transfer amount cannot exceed $999,999.99',
            'idempotency_key.required' => 'Idempotency key is required for transfer requests',
            'idempotency_key.min' => 'Idempotency key must be at least 10 characters',
        ];
    }
}
