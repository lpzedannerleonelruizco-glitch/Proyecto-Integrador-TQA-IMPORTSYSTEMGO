<?php

namespace App\Services\Qa;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class QaCredentialService
{
    public const EMAIL = 'qa.admin@example.test';

    public function reset(string $password): User
    {
        return DB::transaction(function () use ($password): User {
            $user = User::query()->where('email', self::EMAIL)->lockForUpdate()->firstOrFail();
            if (! $user->isActive() || ! $this->isQaAdministrator($user)) {
                throw new RuntimeException('qa_account_not_authorized');
            }

            $user->forceFill([
                'password' => $password,
                'password_changed_at' => now(),
                'remember_token' => null,
            ])->save();

            if (! Hash::check($password, (string) $user->password)) {
                throw new RuntimeException('qa_password_hash_verification_failed');
            }

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            if (Schema::hasTable('audit_logs')) {
                DB::table('audit_logs')->insert([
                    'actor_user_id' => $user->id,
                    'branch_id' => null,
                    'action' => 'qa.credentials.reset',
                    'resource_type' => 'users',
                    'resource_id' => $user->id,
                    'before_values' => null,
                    'after_values' => json_encode(['environment' => 'qa'], JSON_THROW_ON_ERROR),
                    'occurred_at' => now(),
                ]);
            }

            return $user->fresh();
        }, 3);
    }

    private function isQaAdministrator(User $user): bool
    {
        return $user->globalRoleAssignments()
            ->whereNull('ended_by')
            ->where('starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->whereHas('role', function ($query): void {
                $query->where('name', 'administrador-general')
                    ->where('scope_type', Role::SCOPE_GLOBAL)
                    ->where('guard_name', 'web');
            })
            ->exists();
    }
}
