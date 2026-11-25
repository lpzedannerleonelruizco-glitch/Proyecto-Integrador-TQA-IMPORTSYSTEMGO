<?php

namespace App\Services\Authorization;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdministratorProtection
{
    // Must be called inside the surrounding write transaction.
    public function ensureRemovable(User $subject, string $errorField = 'status'): void
    {
        $role = Role::query()->where('name', 'administrador-general')->where('scope_type', Role::SCOPE_GLOBAL)
            ->whereNull('branch_id')->where('guard_name', 'web')->lockForUpdate()->first();
        if (! $role) {
            return;
        }
        // A locking read observes committed concurrent changes, including under MySQL REPEATABLE READ.
        $administrators = DB::table('global_user_roles as assignment')->join('users', 'users.id', '=', 'assignment.user_id')
            ->where('assignment.role_id', $role->id)->whereNull('assignment.ended_by')
            ->where('assignment.starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('assignment.ends_at')->orWhere('assignment.ends_at', '>', now()))
            ->where('users.status', User::STATUS_ACTIVE)->select('users.id')->lockForUpdate()->get()->pluck('id')->unique();
        if ($administrators->contains($subject->id) && $administrators->count() <= 1) {
            throw ValidationException::withMessages([$errorField => 'No se puede retirar el último administrador general activo.']);
        }
    }
}
