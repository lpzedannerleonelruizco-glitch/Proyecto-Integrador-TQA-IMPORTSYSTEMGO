<?php

namespace App\Http\Requests;

use App\Services\Authorization\ScopedAuthorizationService;
use App\Services\OrganizationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! app(ScopedAuthorizationService::class)->allows($this->user(), 'organization.'.$this->route('resource').'.manage')) {
            return false;
        }
        if ($this->route('record') !== null) {
            app(OrganizationService::class)->query($this->user(), $this->route('resource'), 'manage')->findOrFail((int) $this->route('record'));
        }

        return true;
    }

    public function rules(): array
    {
        $resource = $this->route('resource');
        $unique = fn ($column) => Rule::unique($resource, $column)->ignore((int) $this->route('record'));
        $rules = match ($resource) {
            'branches' => [
                'code' => ['required', 'alpha_dash:ascii', 'max:32', $unique('code')],
                'name' => ['required', 'string', 'max:150'],
                'type' => ['required', Rule::in(['central', 'store'])],
                'legal_address' => ['nullable', 'string', 'max:500'],
                'timezone' => ['required', 'timezone'],
                'is_active' => ['required', 'boolean'],
            ],
            'warehouses' => [
                'code' => ['required', 'alpha_dash:ascii', 'max:32', $unique('code')],
                'name' => ['required', 'string', 'max:150'],
                'type' => ['required', 'string', 'max:32'],
                'is_active' => ['required', 'boolean'],
            ],
            'employees' => [
                'employee_code' => ['required', 'alpha_dash:ascii', 'max:32', $unique('employee_code')],
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:150'],
                'status' => ['required', Rule::in(['active', 'inactive', 'terminated'])],
                'hired_at' => ['required', 'date_format:Y-m-d'],
                'terminated_at' => ['nullable', 'required_if:status,terminated', 'prohibited_unless:status,terminated', 'date_format:Y-m-d', 'after_or_equal:hired_at'],
            ],
            default => [],
        };

        return $rules + ['branch_id' => ['prohibited'], 'home_branch_id' => ['prohibited'], 'user_id' => ['prohibited']];
    }
}
