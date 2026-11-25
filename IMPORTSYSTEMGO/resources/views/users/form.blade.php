@extends('layouts.app')
@section('title', 'Administrar cuenta')
@section('content')
<h1 class="h2 mb-4">{{ $account->exists ? 'Administrar cuenta' : 'Crear cuenta' }}</h1>
<section class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
<form method="POST" action="{{ $account->exists ? route('users.update', $account->id) : route('users.store') }}">@csrf @if($account->exists) @method('PUT') @endif
<div class="row g-3">
<div class="col-md-5"><label for="name" class="form-label">Nombre</label><input id="name" name="name" class="form-control" value="{{ old('name', $account->name) }}" required maxlength="255"></div>
<div class="col-md-5"><label for="email" class="form-label">Correo</label><input id="email" name="email" type="email" class="form-control" value="{{ old('email', $account->email) }}" required maxlength="255"></div>
<div class="col-md-2"><label for="status" class="form-label">Estado</label><select id="status" name="status" class="form-select"><option value="active" @selected(old('status', $account->status) !== 'inactive')>Activo</option><option value="inactive" @selected(old('status', $account->status) === 'inactive')>Inactivo</option></select></div>
@unless($account->exists)
<div class="col-md-6"><label for="password" class="form-label">Contraseña inicial segura</label><input id="password" name="password" type="password" class="form-control" autocomplete="new-password" required minlength="12" maxlength="128"></div>
<div class="col-md-6"><label for="password_confirmation" class="form-label">Confirmar contraseña</label><input id="password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password" required></div>
<p class="text-secondary">Mínimo 12 caracteres: mayúsculas, minúsculas, números y símbolos. No se mostrará ni enviará por correo.</p>
@endunless
</div><button class="btn btn-primary mt-3">Guardar cuenta</button></form>
</div></section>
@if($account->exists)
<section class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h4">Empleado vinculado</h2>
@if($employee)
<p class="mb-0">{{ $employee->employee_code }} · {{ $employee->first_name }} {{ $employee->last_name }} · {{ $employee->homeBranch?->name ?? 'Sin sucursal' }}</p>
<p class="text-secondary mb-0">Estado laboral: {{ $employee->status }}. La baja laboral desactiva la cuenta y revoca sus accesos.</p>
@elseif($account->isActive() && $availableEmployees->isNotEmpty())
<form method="POST" action="{{ route('users.employee', $account->id) }}" class="d-flex gap-2 my-3">@csrf
<label for="employee_id" class="visually-hidden">Empleado</label><select id="employee_id" name="employee_id" class="form-select" required><option value="">Elegir empleado activo</option>@foreach($availableEmployees as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->employee_code }} · {{ $candidate->first_name }} {{ $candidate->last_name }} · {{ $candidate->homeBranch->name }}</option>@endforeach</select><button class="btn btn-primary">Vincular empleado</button></form>
<p class="text-secondary mb-0">Requiere una membresía vigente de la cuenta en la sucursal del empleado.</p>
@else
<p class="text-secondary mb-0">{{ $account->isActive() ? 'No hay empleados activos sin cuenta disponibles.' : 'Activa la cuenta para vincular un empleado.' }}</p>
@endif
</div></section>
<section class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h4">Sucursales y vigencias</h2>
<form method="POST" action="{{ route('users.membership', $account->id) }}" class="d-flex gap-2 my-3">@csrf
<label for="branch_id" class="visually-hidden">Sucursal</label><select id="branch_id" name="branch_id" class="form-select" required><option value="">Elegir sucursal activa</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select><button class="btn btn-primary">Asignar sucursal</button></form>
<div class="table-responsive"><table class="table"><thead><tr><th>Sucursal</th><th>Inicio</th><th>Fin / revocación</th><th>Acción</th></tr></thead><tbody>
@foreach($memberships as $membership)<tr><td>{{ $membership->branch->name }}</td><td>{{ $membership->starts_at }}</td><td>{{ $membership->ended_by ? 'Revocada' : ($membership->ends_at ?? 'Sin vencimiento') }}</td><td>
@if(!$membership->ended_by && (!$membership->ends_at || $membership->ends_at->isFuture()))
<form method="POST" action="{{ route('users.membership.revoke', [$account->id, $membership->id]) }}" class="d-flex gap-2">@csrf<input aria-label="Motivo de revocación" name="reason" class="form-control form-control-sm" placeholder="Motivo obligatorio" maxlength="255" required><button class="btn btn-outline-danger btn-sm">Revocar</button></form>
@endif</td></tr>@endforeach
</tbody></table></div></div></section>
@if($canManageRoles)
<section class="card border-0 shadow-sm"><div class="card-body p-4"><h2 class="h4">Roles y alcance</h2>
<ul>@foreach($globalAssignments as $assignment)<li>{{ $assignment->role->name }} — global (desde {{ $assignment->starts_at }})</li>@endforeach @foreach($localAssignments as $assignment)<li>{{ $assignment->role_name }} — {{ $assignment->branch_name }}</li>@endforeach</ul>
<p class="text-secondary">Un rol local requiere membresía vigente. Para un rol global selecciona ámbito global. No se conceden permisos directos.</p>
<form method="POST" action="{{ route('users.role', $account->id) }}" class="row g-3">@csrf
<div class="col-md-5"><label for="role_id" class="form-label">Rol</label><select id="role_id" name="role_id" class="form-select" required>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }} ({{ $role->scope_type }})</option>@endforeach</select></div>
<div class="col-md-5"><label for="role_branch" class="form-label">Ámbito</label><select id="role_branch" name="branch_id" class="form-select"><option value="">Global</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
<div class="col-md-2"><label for="operation" class="form-label">Operación</label><select id="operation" name="operation" class="form-select"><option value="assign">Asignar</option><option value="revoke">Revocar</option></select></div>
<div class="col-md-8"><label for="role_reason" class="form-label">Motivo (obligatorio al revocar)</label><input id="role_reason" name="reason" class="form-control" maxlength="255" autocomplete="off"></div>
<div><button class="btn btn-primary">Aplicar cambio de rol</button></div>
</form></div></section>
@endif
@endif
@endsection
