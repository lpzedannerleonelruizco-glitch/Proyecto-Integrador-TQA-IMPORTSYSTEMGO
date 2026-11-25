@extends('layouts.app')
@section('title', 'Cambiar contraseña')
@section('content')
<section class="card border-0 shadow-sm" style="max-width: 36rem"><div class="card-body p-4">
<h1 class="h3">Cambiar contraseña</h1>
<p class="text-secondary">Usa al menos 12 caracteres con mayúsculas, minúsculas, números y símbolos. Se cerrarán las sesiones existentes.</p>
<form method="POST" action="{{ route('password.update') }}">
    @csrf @method('PUT')
    <div class="mb-3"><label for="current_password" class="form-label">Contraseña actual</label><input class="form-control" id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
    <div class="mb-3"><label for="password" class="form-label">Nueva contraseña</label><input class="form-control" id="password" name="password" type="password" autocomplete="new-password" required minlength="12" maxlength="128"></div>
    <div class="mb-3"><label for="password_confirmation" class="form-label">Repite la nueva contraseña</label><input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
    <button class="btn btn-primary">Actualizar y cerrar sesión</button>
</form>
</div></section>
@endsection
