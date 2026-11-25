@extends('layouts.app')
@section('title', 'Catálogo de casos')
@section('page-label', 'Catálogo QA')
@section('content')
<section class="page-hero"><div><span class="eyebrow">Trazabilidad y ejecución verificada</span><h1>Catálogo de casos</h1><p>{{ $cases->count() }} casos registrados en {{ $suites->count() }} suites. El motor declarado no implica automatización implementada.</p></div></section>
<section class="panel-card mt-4"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Código</th><th>Nombre</th><th>Suite</th><th>Tipo</th><th>Motor declarado</th><th>Automatización real</th><th>Estado</th></tr></thead><tbody>
@foreach($cases as $case)@php($automationCase = $automation[$case->code] ?? ['status' => 'NOT RUN'])<tr><td>{{ $case->code }}</td><td>{{ $case->name }}</td><td>{{ $case->suite?->name ?? 'Sin suite' }}</td><td>{{ $case->case_type }}</td><td><span class="badge-soft">{{ $case->executor }}</span></td><td><strong>{{ $automationCase['status'] }}</strong>@if(isset($automationCase['reason']))<small class="d-block text-secondary">{{ $automationCase['reason'] }}</small>@endif</td><td>{{ $case->is_active ? 'ACTIVO' : 'INACTIVO' }}</td></tr>@endforeach
</tbody></table></div></section>
@endsection
