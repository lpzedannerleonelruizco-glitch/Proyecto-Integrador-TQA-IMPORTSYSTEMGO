<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PasswordService
{
    public function replace(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($locked->isActive(), 403);
            $locked->forceFill([
                'password' => $password,
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();
            // Session middleware also verifies password hashes for non-database drivers.
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $locked->id)->delete();
            }
        });
        $user->refresh();
        Log::info('security.password_changed', ['user_id' => $user->id]);
    }
}
