<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['document_number', 'document_complement', 'phone', 'email', 'address', 'city', 'notes'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field)) ?: null]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'customer_type' => ['required', Rule::in(['natural', 'legal'])],
            'full_name' => ['required', 'string', 'min:3', 'max:160'],
            'document_type' => ['nullable', 'required_with:document_number', Rule::in(['ci', 'nit', 'passport', 'other'])],
            'document_number' => ['nullable', 'required_with:document_type', 'string', 'min:3', 'max:32', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'document_complement' => ['nullable', 'string', 'max:12', 'regex:/^[A-Za-z0-9\-]+$/'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
