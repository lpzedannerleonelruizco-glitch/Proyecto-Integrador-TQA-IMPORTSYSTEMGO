@extends('layouts.app')
@section('title', 'Historial QA')
@section('page-label', 'Historial QA')
@section('content')
<section class="page-hero"><div><span class="eyebrow">Resultados registrados</span><h1>Historial de ejecuciones</h1></div><a class="btn btn-light" href="{{ route('qa.executions.create') }}">Nueva ejecución</a></section>
<section class="panel-card mt-4"><div class="table-responsive"><table class="table"><thead><tr><th>UUID</th><th>Tipo</th><th>Estado</th><th>Inicio</th><th>Correo</th><th>Acción</th></tr></thead><tbody>@forelse($executions as $execution)<tr><td>{{ $execution->public_id }}</td><td>{{ $execution->type }}</td><td>{{ $execution->status }}</td><td>{{ optional($execution->started_at)?->toDateTimeString() ?: 'Pendiente' }}</td><td>{{ $execution->email_status }}</td><td><a class="btn btn-light btn-sm" href="{{ route('qa.executions.show',$execution) }}">Ver</a></td></tr>@empty<tr><td colspan="6">Sin ejecuciones.</td></tr>@endforelse</tbody></table></div><div class="mt-3">{{ $executions->links() }}</div></section>
@endsection
