<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\AuthenticationService;
use App\Services\Authorization\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SessionController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuthenticationService $authentication)
    {
        $authentication->login($request);

        return redirect()->route('branches.select');
    }

    public function destroy(Request $request, BranchContext $context)
    {
        Log::info('security.logout', ['user_id' => $request->user()?->getKey()]);
        $context->clear($request->user());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
