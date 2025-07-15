<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class TransactionHistoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'in:deposit,withdrawal,transfer'],
            'status' => ['nullable', 'string', 'in:pending,completed,failed'],
            'from_date' => ['nullable', 'date', 'before_or_equal:to_date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort_by' => ['nullable', 'string', 'in:created_at,amount,status'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Transaction type must be deposit, withdrawal, or transfer',
            'status.in' => 'Status must be pending, completed, or failed',
            'from_date.before_or_equal' => 'From date must be before or equal to the to date',
            'to_date.after_or_equal' => 'To date must be after or equal to the from date',
            'per_page.max' => 'Maximum 100 transactions per page allowed',
            'sort_by.in' => 'Sort by must be created_at, amount, or status',
            'sort_direction.in' => 'Sort direction must be asc or desc',
        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'per_page' => $this->per_page ?? 20,
            'page' => $this->page ?? 1,
            'sort_by' => $this->sort_by ?? 'created_at',
            'sort_direction' => $this->sort_direction ?? 'desc',
        ]);
    }
}
