<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Services\Authorization\GlobalAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleAdministrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(GlobalAuthorizationService::class)->allows($this->user(), 'security.roles.manage');
    }

    public function rules(): array
    {
        $roleId = $this->route('roleId');
        $role = $roleId === null ? null : Role::query()->findOrFail($roleId);
        $scopeType = $role?->scope_type ?? $this->input('scope_type');
        $branchId = $role?->branch_id ?? $this->input('branch_id');
        $uniqueName = Rule::unique('roles', 'name')->where('guard_name', 'web');

        if ($branchId === null || $branchId === '') {
            $uniqueName->whereNull('branch_id');
        } else {
            $uniqueName->where('branch_id', (int) $branchId);
        }
        if ($roleId !== null) {
            $uniqueName->ignore((int) $roleId);
        }

        $rules = [
            'name' => ['required', 'string', 'max:125', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $uniqueName],
            'permissions' => ['required', 'array', 'min:1', 'max:100'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ];

        if ($roleId === null) {
            $rules['scope_type'] = ['required', Rule::in([Role::SCOPE_GLOBAL, Role::SCOPE_BRANCH])];
            $rules['branch_id'] = $scopeType === Role::SCOPE_GLOBAL
                ? ['prohibited']
                : ['nullable', 'integer', Rule::exists('branches', 'id')->where('is_active', true)];
        } else {
            $rules['scope_type'] = ['prohibited'];
            $rules['branch_id'] = ['prohibited'];
        }

        return $rules;
    }
}
