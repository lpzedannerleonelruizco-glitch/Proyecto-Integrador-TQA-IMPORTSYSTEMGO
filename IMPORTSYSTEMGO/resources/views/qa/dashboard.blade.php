@extends('layouts.app')
@section('title', 'Dashboard QA')
@section('page-label', 'IMPORTSYSTEMGO QA CENTER')
@section('content')
@php
    $counts = [
        'PASS' => $executions->flatMap(fn ($execution) => $execution->results)->where('status', 'PASS')->count(),
        'FAIL' => $executions->flatMap(fn ($execution) => $execution->results)->where('status', 'FAIL')->count(),
        'SKIPPED' => $executions->flatMap(fn ($execution) => $execution->results)->where('status', 'SKIPPED')->count(),
        'BLOCKED' => $executions->flatMap(fn ($execution) => $execution->results)->where('status', 'BLOCKED')->count(),
        'NOT RUN' => $executions->flatMap(fn ($execution) => $execution->results)->where('status', 'NOT RUN')->count(),
    ];
    $completed = collect($executions)->whereIn('status', ['COMPLETED', 'FAILED'])->count();
    $measured = $counts['PASS'] + $counts['FAIL'];
@endphp
<section class="page-hero">
    <div><span class="eyebrow">Centro de aseguramiento de calidad</span><h1>IMPORTSYSTEMGO QA CENTER</h1><p>Pruebas reales, evidencias verificables y reportes automatizados.</p></div>
    <a class="btn btn-light" href="{{ route('qa.executions.create') }}">Nueva ejecución</a>
</section>
<div class="metric-grid mt-4">
    <article class="metric-card"><span class="metric-accent bg-primary"></span><span class="metric-label">Casos</span><strong>{{ $caseCount }}</strong><small>{{ $suiteCount }} suites activas</small></article>
    <article class="metric-card"><span class="metric-accent bg-success"></span><span class="metric-label">Aprobados</span><strong>{{ $counts['PASS'] }}</strong><small>Últimas ejecuciones cargadas</small></article>
    <article class="metric-card"><span class="metric-accent bg-danger"></span><span class="metric-label">Fallidos</span><strong>{{ $counts['FAIL'] }}</strong><small>Fallos funcionales o controlados</small></article>
    <article class="metric-card"><span class="metric-accent bg-warning"></span><span class="metric-label">Bloqueados</span><strong>{{ $counts['BLOCKED'] }}</strong><small>Requieren configuración o datos</small></article>
    <article class="metric-card"><span class="metric-accent bg-secondary"></span><span class="metric-label">No ejecutados</span><strong>{{ $counts['NOT RUN'] }}</strong><small>Sin automatización disponible</small></article>
    <article class="metric-card"><span class="metric-accent bg-warning"></span><span class="metric-label">Éxito</span><strong>{{ $measured ? number_format($counts['PASS'] / $measured * 100, 1) . '%' : 'N/A' }}</strong><small>PASS / (PASS + FAIL)</small></article>
</div>
<div class="row g-4 mt-1">
    <div class="col-xl-8"><section class="panel-card h-100"><div class="panel-heading"><div><span class="eyebrow">Actividad</span><h2>Últimas ejecuciones</h2></div></div>
        <div class="table-responsive"><table class="table"><thead><tr><th>Identificador</th><th>Tipo</th><th>Estado</th><th>Progreso</th><th>Correo</th></tr></thead><tbody>
        @forelse($executions as $execution)<tr><td><a href="{{ route('qa.executions.show', $execution) }}">{{ $execution->public_id }}</a></td><td>{{ $execution->type }}</td><td><span class="badge-soft">{{ $execution->status }}</span></td><td>{{ $execution->progress }}%</td><td>{{ $execution->email_status }}</td></tr>@empty<tr><td colspan="5">Sin ejecuciones registradas.</td></tr>@endforelse
        </tbody></table></div>
    </section></div>
    <div class="col-xl-4"><section class="panel-card h-100"><div class="panel-heading"><div><span class="eyebrow">Estado</span><h2>Motores</h2></div></div>
        <div class="d-grid gap-3"><div class="operational-stat"><span>PHPUnit</span><strong>Por verificar</strong></div><div class="operational-stat"><span>Selenium / Robot</span><strong>Por verificar</strong></div><div class="operational-stat"><span>Locust</span><strong>Por verificar</strong></div><div class="operational-stat"><span>Correo SMTP</span><strong>{{ config('mail.default') === 'smtp' ? 'SMTP definido' : 'No configurado' }}</strong></div><div class="operational-stat"><span>IA</span><strong>{{ config('qa.ai.provider') === 'ollama' ? 'Proveedor configurado' : 'No configurada' }}</strong></div></div>
    </section></div>
</div>
@endsection
