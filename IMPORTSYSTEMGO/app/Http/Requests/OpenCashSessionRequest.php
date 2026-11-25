<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'cash_register_id' => ['required', 'integer', 'exists:cash_registers,id'],
            'opening_amount' => ['required', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'opening_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
