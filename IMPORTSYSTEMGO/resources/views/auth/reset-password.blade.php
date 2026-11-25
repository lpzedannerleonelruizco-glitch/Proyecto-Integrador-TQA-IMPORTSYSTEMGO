@extends('layouts.app')
@section('title', 'Restablecer contraseña')
@section('content')
<section class="card border-0 shadow-sm mx-auto" style="max-width: 30rem"><div class="card-body p-4">
<h1 class="h3">Restablecer contraseña</h1>
<p class="text-secondary">Mínimo 12 caracteres, incluyendo mayúsculas, minúsculas, números y símbolos.</p>
<form method="POST" action="{{ route('password.reset.store') }}">@csrf
<input type="hidden" name="token" value="{{ $token }}">
<label for="email" class="form-label">Correo electrónico</label><input id="email" name="email" type="email" value="{{ $email }}" class="form-control mb-3" autocomplete="username" required>
<label for="password" class="form-label">Nueva contraseña</label><input id="password" name="password" type="password" class="form-control mb-3" autocomplete="new-password" required minlength="12" maxlength="128">
<label for="password_confirmation" class="form-label">Confirmar contraseña</label><input id="password_confirmation" name="password_confirmation" type="password" class="form-control mb-3" autocomplete="new-password" required>
<button class="btn btn-primary">Restablecer acceso</button>
</form>
</div></section>
@endsection
