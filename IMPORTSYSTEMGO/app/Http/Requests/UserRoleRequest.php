<?php

namespace App\Http\Requests;

use App\Services\Authorization\GlobalAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(GlobalAuthorizationService::class)->allows($this->user(), 'security.roles.manage');
    }

    public function rules(): array
    {
        return [
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'operation' => ['required', Rule::in(['assign', 'revoke'])],
            'reason' => ['required_if:operation,revoke', 'prohibited_unless:operation,revoke', 'string', 'max:255'],
        ];
    }
}
