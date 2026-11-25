@extends('layouts.app')
@section('title', 'Usuarios')
@section('content')
<div class="d-flex justify-content-between mb-4"><h1 class="h2">Usuarios</h1>@if($canManage)<a class="btn btn-primary" href="{{ route('users.create') }}">Crear cuenta</a>@endif</div>
<div class="card border-0 shadow-sm table-responsive"><table class="table mb-0"><thead><tr><th>Nombre</th><th>Correo</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($users as $account)<tr><td>{{ $account->name }}</td><td>{{ $account->email }}</td><td>{{ $account->isActive() ? 'Activo' : 'Inactivo' }}</td><td>@if($canManage)<a href="{{ route('users.edit', $account->id) }}">Administrar</a>@endif</td></tr>
@empty<tr><td colspan="4">No hay cuentas disponibles.</td></tr>@endforelse
</tbody></table></div><div class="mt-4">{{ $users->links() }}</div>
@endsection
