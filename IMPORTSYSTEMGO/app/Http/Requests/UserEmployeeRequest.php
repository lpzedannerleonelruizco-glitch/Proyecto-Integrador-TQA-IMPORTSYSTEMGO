<?php

namespace App\Http\Requests;

use App\Services\Authorization\GlobalAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;

class UserEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(GlobalAuthorizationService::class)->allows($this->user(), 'security.users.manage');
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ];
    }
}
