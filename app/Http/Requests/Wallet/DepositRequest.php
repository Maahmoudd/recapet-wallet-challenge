<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class DepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'idempotency_key' => ['required', 'string', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Deposit amount must be at least $0.01',
            'amount.max' => 'Deposit amount cannot exceed $999,999.99',
            'amount.regex' => 'Amount must have at most 2 decimal places',
            'idempotency_key.uuid' => 'Idempotency key must be a valid UUID',
        ];
    }
}
