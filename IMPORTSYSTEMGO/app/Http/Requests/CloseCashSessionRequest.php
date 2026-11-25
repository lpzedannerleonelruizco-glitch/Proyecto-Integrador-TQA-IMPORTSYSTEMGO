<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'counts' => ['required', 'array', 'min:1'],
            'counts.*.denomination' => ['required', 'decimal:0,2'],
            'counts.*.quantity' => ['required', 'integer', 'min:0', 'max:10000'],
            'closing_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
