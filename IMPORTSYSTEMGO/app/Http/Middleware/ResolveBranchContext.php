<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\User;
use App\Services\Authorization\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBranchContext
{
    public function __construct(private readonly BranchContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $branchId = $request->session()->get('active_branch_id');

        if (! is_int($branchId) && ! ctype_digit((string) $branchId)) {
            $hasValidMembership = $user->branchMemberships()
                ->whereHas('branch', fn ($query) => $query->where('is_active', true))
                ->activeAt(now())
                ->exists();

            abort_unless($hasValidMembership, 403, 'No tienes ninguna sucursal autorizada.');
            $request->session()->flash(
                'status',
                'Selecciona una sucursal para consultar los pronósticos.',
            );
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('branches.select');
        }

        $branch = Branch::query()->find($branchId);

        if (! $branch) {
            abort(403, 'La sucursal activa no está disponible.');
        }

        $this->context->activate($user, $branch);

        try {
            return $next($request);
        } finally {
            $this->context->clear($user);
        }
    }
}
