<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Services\PasswordService;
use Illuminate\Support\Facades\Auth;

class PasswordController extends Controller
{
    public function edit()
    {
        return view('auth.password');
    }

    public function update(ChangePasswordRequest $request, PasswordService $passwords)
    {
        $passwords->replace($request->user(), $request->validated('password'));
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Contraseña actualizada. Inicia sesión de nuevo.');
    }
}
