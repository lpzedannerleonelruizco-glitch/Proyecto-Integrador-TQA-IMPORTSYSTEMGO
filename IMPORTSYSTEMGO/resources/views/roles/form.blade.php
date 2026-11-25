@extends('layouts.app')
@section('title', $role->exists ? 'Editar rol' : 'Crear rol')
@section('content')
<h1 class="h2 mb-4">{{ $role->exists ? 'Editar rol personalizado' : 'Crear rol personalizado' }}</h1>
<div class="card border-0 shadow-sm" style="max-width: 58rem"><div class="card-body p-4">
<form method="POST" action="{{ $role->exists ? route('roles.update', $role->id) : route('roles.store') }}">
@csrf @if($role->exists) @method('PUT') @endif
<div class="row g-3">
<div class="col-md-6"><label for="name" class="form-label">Código del rol</label><input id="name" name="name" class="form-control" value="{{ old('name', $role->name) }}" pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="125" required><div class="form-text">Usa minúsculas, números y guiones.</div></div>
@if(!$role->exists)
<div class="col-md-3"><label for="scope_type" class="form-label">Alcance</label><select id="scope_type" name="scope_type" class="form-select" required><option value="branch" @selected(old('scope_type', 'branch') === 'branch')>Sucursal</option><option value="global" @selected(old('scope_type') === 'global')>Global</option></select></div>
<div class="col-md-3"><label for="branch_id" class="form-label">Sucursal específica</label><select id="branch_id" name="branch_id" class="form-select"><option value="">Todas las sucursales</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)old('branch_id') === (string)$branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
@else
<div class="col-md-6"><label class="form-label">Alcance protegido</label><div class="form-control-plaintext">{{ $role->scope_type === 'global' ? 'Global' : ($role->branch?->name ?? 'Todas las sucursales') }}</div></div>
@endif
<fieldset class="col-12"><legend class="form-label">Permisos delegables</legend><div class="row g-2">
@foreach($permissions as $permission)
<div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="permission_{{ $loop->index }}" name="permissions[]" value="{{ $permission->name }}" @checked(in_array($permission->name, old('permissions', $assignedPermissions ?? []), true))><label class="form-check-label" for="permission_{{ $loop->index }}">{{ $permission->name }}</label></div></div>
@endforeach
</div></fieldset>
</div><p class="text-secondary mt-3">Solo puedes delegar permisos globales que ya tienes. Los cambios de permisos afectan de inmediato a quienes poseen este rol y quedan auditados.</p>
<div class="mt-4 d-flex gap-3"><button class="btn btn-primary">Guardar rol</button><a class="btn btn-outline-secondary" href="{{ route('roles.index') }}">Cancelar</a></div>
</form></div></div>
@endsection
