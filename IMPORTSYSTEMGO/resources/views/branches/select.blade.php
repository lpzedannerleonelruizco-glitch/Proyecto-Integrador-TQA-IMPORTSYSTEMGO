@extends('layouts.app')
@section('title', 'Seleccionar sucursal')
@section('content')
<p class="text-primary text-uppercase small fw-bold">Organización</p>
<h1 class="h2">Elige tu sucursal</h1>
<p class="text-secondary mb-4">Solo se muestran tus membresías vigentes en sucursales activas.</p>
<div class="row g-3">
    @forelse($branches as $branch)
    <div class="col-lg-4 col-md-6"><section class="card h-100 border-0 shadow-sm"><div class="card-body p-4">
        <span class="badge text-bg-light mb-3">{{ $branch->type === 'central' ? 'Centro logístico' : 'Sucursal' }}</span>
        <h2 class="h5">{{ $branch->name }}</h2><p class="text-secondary">{{ $branch->code }}</p>
        <form method="POST" action="{{ route('branches.activate') }}">@csrf<input type="hidden" name="branch_id" value="{{ $branch->id }}"><button class="btn btn-primary">Abrir sucursal</button></form>
    </div></section></div>
    @empty
    <div class="col-12"><div class="alert alert-info">No tienes sucursales disponibles. Solicita una membresía vigente a administración.</div></div>
    @endforelse
</div>
<div class="mt-4">{{ $branches->links() }}</div>
@endsection
