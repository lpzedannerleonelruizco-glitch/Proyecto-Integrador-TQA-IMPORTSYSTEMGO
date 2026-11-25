<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserAccountRequest;
use App\Http\Requests\UserEmployeeRequest;
use App\Http\Requests\UserMembershipRequest;
use App\Http\Requests\UserRoleRequest;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\GlobalAuthorizationService;
use App\Services\IdentityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserAdministrationController extends Controller
{
    public function index(Request $request, IdentityService $service)
    {
        $service->authorize($request->user(), 'security.users.view');
        $users = User::query()->orderBy('name')->paginate(20);
        $canManage = app(GlobalAuthorizationService::class)->allows($request->user(), 'security.users.manage');

        return view('users.index', compact('users', 'canManage'));
    }

    public function create(Request $request, IdentityService $service)
    {
        $service->authorize($request->user(), 'security.users.manage');

        return view('users.form', ['account' => new User]);
    }

    public function store(UserAccountRequest $request, IdentityService $service)
    {
        $user = $service->save($request->user(), $request->validated());

        return redirect()->route('users.edit', $user->id)->with('status', 'Cuenta creada. Asigna sucursales y roles para habilitar su trabajo.');
    }

    public function edit(Request $request, IdentityService $service, int $userId)
    {
        $service->authorize($request->user(), 'security.users.manage');
        $account = User::findOrFail($userId);
        $memberships = $account->branchMemberships()->with('branch')->orderByDesc('id')->get();
        $branches = Branch::active()->orderBy('name')->get();
        $employee = $account->employee()->with('homeBranch')->first();
        $availableEmployees = Employee::query()->active()->whereNull('user_id')
            ->whereHas('homeBranch', fn ($query) => $query->where('is_active', true))
            ->with('homeBranch')->orderBy('employee_code')->get();
        $canManageRoles = app(GlobalAuthorizationService::class)->allows($request->user(), 'security.roles.manage');
        $roles = $canManageRoles ? Role::where('guard_name', 'web')->orderBy('name')->get() : collect();
        $localAssignments = $canManageRoles ? DB::table('model_has_roles as assignment')
            ->join('roles', 'roles.id', '=', 'assignment.role_id')->join('branches', 'branches.id', '=', 'assignment.branch_id')
            ->where('assignment.model_id', $account->id)->where('assignment.model_type', $account->getMorphClass())
            ->select(['roles.name as role_name', 'branches.name as branch_name'])->get() : collect();
        $globalAssignments = $canManageRoles ? $account->globalRoleAssignments()
            ->whereNull('ended_by')->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->with('role')->get() : collect();

        return view('users.form', compact('account', 'memberships', 'branches', 'employee', 'availableEmployees', 'roles', 'canManageRoles', 'localAssignments', 'globalAssignments'));
    }

    public function update(UserAccountRequest $request, IdentityService $service, int $userId)
    {
        $service->save($request->user(), $request->validated(), $userId);

        return back()->with('status', 'Cuenta actualizada y auditada.');
    }

    public function employee(UserEmployeeRequest $request, IdentityService $service, int $userId)
    {
        $service->linkEmployee($request->user(), User::findOrFail($userId), (int) $request->validated('employee_id'));

        return back()->with('status', 'Empleado vinculado a la cuenta.');
    }

    public function membership(UserMembershipRequest $request, IdentityService $service, int $userId)
    {
        $service->grantMembership($request->user(), User::findOrFail($userId), Branch::findOrFail((int) $request->validated('branch_id')));

        return back()->with('status', 'Membresía vigente creada.');
    }

    public function revokeMembership(UserMembershipRequest $request, IdentityService $service, int $userId, int $membershipId)
    {
        $service->revokeMembership($request->user(), User::findOrFail($userId), $membershipId, $request->validated('reason'));

        return back()->with('status', 'Membresía revocada; los roles locales fueron retirados.');
    }

    public function role(UserRoleRequest $request, IdentityService $service, int $userId)
    {
        $input = $request->validated();
        $service->changeRole(
            $request->user(),
            User::findOrFail($userId),
            Role::findOrFail($input['role_id']),
            empty($input['branch_id']) ? null : Branch::findOrFail($input['branch_id']),
            $input['operation'] === 'revoke',
            $input['reason'] ?? null,
        );

        return back()->with('status', 'Asignación de rol actualizada y auditada.');
    }
}
