@extends('layouts.app')
@section('title', 'Recuperar acceso')
@section('content')
<section class="card border-0 shadow-sm mx-auto" style="max-width: 30rem"><div class="card-body p-4">
<h1 class="h3">Recuperar acceso</h1>
@if($available)
<p class="text-secondary">Enviaremos un enlace de uso único al correo asociado con tu cuenta.</p>
<form method="POST" action="{{ route('password.email') }}">@csrf
<label for="email" class="form-label">Correo electrónico</label><input class="form-control mb-3" id="email" name="email" type="email" autocomplete="username" required maxlength="255">
<button class="btn btn-primary">Solicitar enlace</button>
</form>
@else
<div class="alert alert-warning">La recuperación por correo aún no está habilitada. Contacta a administración. No se generan enlaces en registros de texto.</div>
@endif
<a class="d-block mt-3" href="{{ route('login') }}">Volver al acceso</a>
</div></section>
@endsection
