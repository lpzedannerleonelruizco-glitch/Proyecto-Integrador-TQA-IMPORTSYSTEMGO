@extends('layouts.app')
@section('title', 'Roles')
@section('content')
<div class="d-flex justify-content-between mb-4"><h1 class="h2">Roles y permisos</h1>@if($canManage)<a class="btn btn-primary" href="{{ route('roles.create') }}">Crear rol personalizado</a>@endif</div>
<div class="card border-0 shadow-sm table-responsive"><table class="table mb-0"><thead><tr><th>Rol</th><th>Alcance</th><th>Permisos</th><th>Administración</th></tr></thead><tbody>
@forelse($roles as $role)
<tr><td>{{ $role->name }} @if(array_key_exists($role->name, \App\Support\AuthorizationCatalog::roles()))<span class="badge text-bg-secondary">Sistema</span>@endif</td><td>{{ $role->scope_type === 'global' ? 'Global' : ($role->branch?->name ?? 'Todas las sucursales') }}</td><td>{{ $role->permissions->pluck('name')->join(', ') }}</td><td>@if($canManage && !array_key_exists($role->name, \App\Support\AuthorizationCatalog::roles()))<a href="{{ route('roles.edit', $role->id) }}">Editar</a>@else<span class="text-secondary">Protegido</span>@endif</td></tr>
@empty<tr><td colspan="4">No hay roles disponibles.</td></tr>@endforelse
</tbody></table></div><div class="mt-4">{{ $roles->links() }}</div>
<p class="text-secondary mt-3">Los roles del sistema están protegidos. Un rol personalizado solo puede delegar permisos que el administrador actual ya posee.</p>
@endsection
