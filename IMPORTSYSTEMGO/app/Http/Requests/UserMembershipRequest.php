<?php

namespace App\Http\Requests;

use App\Services\Authorization\GlobalAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;

class UserMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(GlobalAuthorizationService::class)->allows($this->user(), 'security.users.manage');
    }

    public function rules(): array
    {
        if ($this->route('membershipId') !== null) {
            return [
                'branch_id' => ['prohibited'],
                'reason' => ['required', 'string', 'max:255'],
            ];
        }

        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'reason' => ['prohibited'],
        ];
    }
}
