<?php

namespace App\Http\Requests;

use App\Support\PaymentReferenceSafety;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollectPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'sale_id' => ['required', 'integer', 'exists:sales,id'],
            'payments' => ['required', 'array', 'min:1', 'max:4'],
            'payments.*.method' => ['required', Rule::in(['cash', 'qr', 'debit_card', 'credit_card', 'transfer'])],
            'payments.*.amount' => ['required', 'decimal:0,2', 'gt:0', 'max:999999.99'],
            'payments.*.reference' => [
                'nullable',
                'string',
                'max:80',
                'required_unless:payments.*.method,cash',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && PaymentReferenceSafety::containsFullCardNumber($value)) {
                        $fail('No ingreses números completos de tarjeta como referencia.');
                    }
                },
            ],
            'payments.*.idempotency_key' => ['required', 'string', 'max:64', 'distinct'],
        ];
    }
}
