<?php

namespace App\Services;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthenticationService
{
    public function login(LoginRequest $request): void
    {
        // Neither the email nor any credential is written to logs or cache keys.
        $key = 'login:'.hash('sha256', mb_strtolower($request->string('email')->trim()->toString()).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Demasiados intentos. Espere un minuto.']);
        }
        RateLimiter::hit($key, 60);
        if (! Auth::guard('web')->attempt([
            'email' => trim($request->validated('email')),
            'password' => $request->validated('password'),
            'status' => User::STATUS_ACTIVE,
        ])) {
            throw ValidationException::withMessages(['email' => 'No fue posible iniciar sesión con estas credenciales.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->forget('active_branch_id');
        $request->user()->forceFill(['last_login_at' => now()])->save();
        Log::info('security.login', ['user_id' => $request->user()->getKey()]);
    }
}
