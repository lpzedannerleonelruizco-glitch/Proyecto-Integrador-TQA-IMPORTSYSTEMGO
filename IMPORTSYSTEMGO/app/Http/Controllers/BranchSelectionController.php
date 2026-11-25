<?php

namespace App\Http\Controllers;

use App\Http\Requests\SelectBranchRequest;
use App\Models\Branch;
use App\Services\Authorization\BranchContext;
use Illuminate\Http\Request;

class BranchSelectionController extends Controller
{
    public function index(Request $request)
    {
        $branches = Branch::query()->active()
            ->whereHas('memberships', fn ($query) => $query->where('user_id', $request->user()->id)->activeAt(now()))
            ->orderBy('name')->paginate(20);

        return view('branches.select', compact('branches'));
    }

    public function store(SelectBranchRequest $request, BranchContext $context)
    {
        $branch = Branch::query()->find($request->validated('branch_id'));
        abort_unless($branch, 403);
        try {
            $context->activate($request->user(), $branch);
            $request->session()->put('active_branch_id', $branch->id);
            $request->session()->regenerate();
        } finally {
            $context->clear($request->user());
        }

        return redirect()->intended(route('dashboard'));
    }
}
