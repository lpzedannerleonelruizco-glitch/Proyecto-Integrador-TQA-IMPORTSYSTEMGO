<?php

namespace App\Http\Requests;

use App\Services\Authorization\GlobalAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(GlobalAuthorizationService::class)->allows($this->user(), 'security.users.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore((int) $this->route('userId'))],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'password' => $this->route('userId') ? ['prohibited'] : ['required', 'confirmed', 'max:128', Password::min(12)->mixedCase()->numbers()->symbols()],
            'primary_branch_id' => ['prohibited'],
            'branch_id' => ['prohibited'],
            'employee_id' => ['prohibited'],
            'user_id' => ['prohibited'],
        ];
    }
}
