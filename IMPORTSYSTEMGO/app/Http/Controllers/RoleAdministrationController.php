<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleAdministrationRequest;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Authorization\GlobalAuthorizationService;
use App\Services\RoleAdministrationService;
use Illuminate\Http\Request;

class RoleAdministrationController extends Controller
{
    public function index(Request $request, GlobalAuthorizationService $authorization)
    {
        $canView = $authorization->allows($request->user(), 'security.roles.view')
            || $authorization->allows($request->user(), 'security.roles.manage');
        abort_unless($canView, 403);

        $roles = Role::query()->where('guard_name', Permission::GUARD)
            ->with(['branch', 'permissions'])->orderBy('scope_type')->orderBy('name')->paginate(25);
        $canManage = $authorization->allows($request->user(), 'security.roles.manage');

        return view('roles.index', compact('roles', 'canManage'));
    }

    public function create(Request $request, RoleAdministrationService $service, GlobalAuthorizationService $authorization)
    {
        $service->authorize($request->user());
        $role = new Role;
        $branches = Branch::active()->orderBy('name')->get();
        $permissions = Permission::query()->where('guard_name', Permission::GUARD)
            ->whereIn('name', $authorization->permissionsFor($request->user()))
            ->orderBy('name')->get();

        return view('roles.form', compact('role', 'branches', 'permissions'));
    }

    public function store(RoleAdministrationRequest $request, RoleAdministrationService $service)
    {
        $role = $service->save($request->user(), $request->validated());

        return redirect()->route('roles.edit', $role->id)->with('status', 'Rol personalizado creado y auditado.');
    }

    public function edit(Request $request, RoleAdministrationService $service, GlobalAuthorizationService $authorization, int $roleId)
    {
        $service->authorize($request->user());
        $role = Role::query()->where('guard_name', Permission::GUARD)->findOrFail($roleId);
        abort_if($service->isSystemRole($role), 403);
        $branches = Branch::active()->orderBy('name')->get();
        $permissions = Permission::query()->where('guard_name', Permission::GUARD)
            ->whereIn('name', $authorization->permissionsFor($request->user()))
            ->orderBy('name')->get();
        $assignedPermissions = $role->permissions()->pluck('permissions.name')->all();

        return view('roles.form', compact('role', 'branches', 'permissions', 'assignedPermissions'));
    }

    public function update(RoleAdministrationRequest $request, RoleAdministrationService $service, int $roleId)
    {
        $role = $service->save($request->user(), $request->validated(), $roleId);

        return redirect()->route('roles.edit', $role->id)->with('status', 'Rol personalizado actualizado y auditado.');
    }
}
