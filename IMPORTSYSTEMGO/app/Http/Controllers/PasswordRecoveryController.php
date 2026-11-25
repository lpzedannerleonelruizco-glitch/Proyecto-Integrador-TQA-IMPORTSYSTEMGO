<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetPasswordRequest;
use App\Services\PasswordRecoveryService;
use Illuminate\Http\Request;

class PasswordRecoveryController extends Controller
{
    public function create(PasswordRecoveryService $service)
    {
        return view('auth.forgot-password', ['available' => $service->available()]);
    }

    public function store(Request $request, PasswordRecoveryService $service)
    {
        $input = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $service->send($input['email']);

        return back()->with('status', 'Si la cuenta está habilitada, recibirás instrucciones para recuperar el acceso.');
    }

    public function edit(Request $request, string $token)
    {
        return response()->view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email', '')])
            ->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }

    public function update(ResetPasswordRequest $request, PasswordRecoveryService $service)
    {
        if (! $service->reset($request->validated())) {
            return back()->withErrors(['email' => 'El enlace no es válido o ha vencido. Solicita uno nuevo.']);
        }

        return redirect()->route('login')->with('status', 'Contraseña restablecida. Puedes iniciar sesión.');
    }
}
