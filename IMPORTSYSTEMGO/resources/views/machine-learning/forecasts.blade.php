@extends('layouts.app')
@section('title', 'Pronóstico de demanda')
@section('page-label', 'IMPORTSYSTEMGO Intelligence')
@section('content')
<section class="page-hero">
    <div>
        <span class="eyebrow">Aprendizaje automático</span>
        <h1>Inteligencia de demanda</h1>
        <p>Pronósticos de reposición con validación temporal y decisión humana obligatoria.</p>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <span class="badge-soft">{{ $branch->code }} · {{ $branch->name }}</span>
            <span class="badge-soft">Datos académicos DEMO</span>
        </div>
    </div>
    <div class="text-end">
        <span class="badge text-bg-{{ $model?->status === 'active' ? 'success' : 'warning' }}">
            {{ $model ? strtoupper($model->status) : 'SIN MODELO' }}
        </span>
        @if($model)<small class="d-block mt-2">Entrenado {{ $model->trained_at->format('d/m/Y H:i') }}</small>@endif
    </div>
</section>

@if(!$model)
<section class="panel-card">
    <div class="empty-state">
        <h2 class="h4">Todavía no existe un modelo entrenado</h2>
        <p>Un administrador debe ejecutar el comando interactivo de entrenamiento cuando exista historial suficiente.</p>
        <code>php artisan ml:demand:train</code>
    </div>
</section>
@else
<div class="metric-grid">
    <article class="metric-card" title="Error porcentual ponderado del modelo sobre demanda válida"><span class="metric-label">WAPE del modelo</span><strong>{{ number_format(($model->metrics['wape'] ?? 0) * 100, 2) }}%</strong><small>Menor es mejor</small></article>
    <article class="metric-card" title="WAPE calculada por el promedio móvil usado como referencia"><span class="metric-label">WAPE línea base</span><strong>{{ number_format(($model->metrics['baseline_wape'] ?? 0) * 100, 2) }}%</strong><small>Promedio móvil</small></article>
    <article class="metric-card"><span class="metric-label">Mejora frente a base</span><strong>@if(($model->metrics['baseline_wape'] ?? 0)>0){{ number_format((1 - ($model->metrics['wape'] ?? 0) / $model->metrics['baseline_wape']) * 100, 1) }}% @else—@endif</strong><small>WAPE relativo</small></article>
    <article class="metric-card" title="Error medio absoluto expresado en unidades"><span class="metric-label">MAE</span><strong>{{ number_format($model->metrics['mae'] ?? 0, 2) }}</strong><small>Unidades</small></article>
    <article class="metric-card" title="Negativo subestima; positivo sobreestima la demanda"><span class="metric-label">Sesgo</span><strong>{{ number_format($model->metrics['bias'] ?? 0, 2) }}</strong><small>Unidades / pronóstico</small></article>
    <article class="metric-card"><span class="metric-label">Productos evaluados</span><strong>{{ $summary['products'] }}</strong><small>{{ number_format($summary['coverage'], 0) }} filas validadas</small></article>
    <article class="metric-card"><span class="metric-label">Productos en riesgo</span><strong>{{ $summary['break_stock'] }}</strong><small>Con posible quiebre</small></article>
    <article class="metric-card"><span class="metric-label">Reposición sugerida</span><strong>{{ number_format($summary['suggested_units'], 0) }}</strong><small>{{ $summary['pending'] }} recomendaciones pendientes</small></article>
</div>

<div class="row g-4 mt-1">
    <div class="col-xl-4">
        <section class="panel-card h-100">
            <span class="eyebrow">Modelo activo</span>
            <h2 class="h5 mt-2">{{ $model->algorithm }}</h2>
            <dl class="row mt-4 mb-0">
                <dt class="col-7">MAE</dt><dd class="col-5 text-end">{{ number_format($model->metrics['mae'] ?? 0, 2) }}</dd>
                <dt class="col-7">Horizonte</dt><dd class="col-5 text-end">{{ config('machine-learning.forecast_horizon_days') }} días</dd>
                <dt class="col-7">Entrenamiento</dt><dd class="col-5 text-end">{{ $model->trained_from?->format('d/m/Y') }}–{{ $model->trained_until?->format('d/m/Y') }}</dd>
                <dt class="col-7">Artefacto</dt><dd class="col-5 text-end font-monospace">{{ substr($model->artifact_sha256, 0, 12) }}</dd>
            </dl>
        </section>
    </div>
    <div class="col-xl-8">
        <section class="panel-card h-100">
            <div class="panel-heading"><div><span class="eyebrow">Metodología</span><h2>Control profesional</h2></div></div>
            <div class="row g-3">
                <div class="col-md-4"><div class="p-3 bg-light rounded-3"><strong>Sin fuga temporal</strong><small class="d-block text-secondary">Entrena con pasado y valida con fechas posteriores.</small></div></div>
                <div class="col-md-4"><div class="p-3 bg-light rounded-3"><strong>Modelo versionado</strong><small class="d-block text-secondary">Artefacto privado con SHA-256 y métricas auditadas.</small></div></div>
                <div class="col-md-4"><div class="p-3 bg-light rounded-3"><strong>Humano al mando</strong><small class="d-block text-secondary">Nunca crea órdenes de compra automáticamente.</small></div></div>
            </div>
        </section>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-xl-8">
        <section class="panel-card h-100">
            <div class="panel-heading"><div><span class="eyebrow">Comparación operativa</span><h2>Demanda prevista frente a disponibilidad</h2></div></div>
            <div class="chart-frame">@if(count($forecastChart))<canvas data-chart="mlDemand" data-label="Pronóstico a 30 días"></canvas>@else<div class="empty-state"><strong>Sin pronósticos visibles</strong><p>Prueba con otra sucursal o filtros autorizados.</p></div>@endif</div>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="panel-card h-100">
            <div class="panel-heading"><div><span class="eyebrow">Riesgo</span><h2>Distribución pendiente</h2></div></div>
            <div class="chart-frame">@if(array_sum(array_column($riskChart, 'value'))>0)<canvas data-chart="mlRisk" data-label="Recomendaciones" data-type="doughnut"></canvas>@else<div class="empty-state"><strong>Sin recomendaciones pendientes</strong></div>@endif</div>
        </section>
    </div>
</div>
<div class="row g-4 mt-1">
    <div class="col-xl-4"><section class="panel-card h-100"><div class="panel-heading"><div><span class="eyebrow">Validación</span><h2>Modelo frente a línea base</h2></div></div><div class="chart-frame">@if(count($modelComparison))<canvas data-chart="mlModel" data-label="WAPE (%)"></canvas>@else<div class="empty-state">Sin métricas comparables</div>@endif</div></section></div>
    <div class="col-xl-4"><section class="panel-card h-100"><div class="panel-heading"><div><span class="eyebrow">Territorio</span><h2>Riesgo crítico por sucursal</h2></div></div><div class="chart-frame">@if(array_sum(array_column($branchRiskChart, 'value'))>0)<canvas data-chart="mlBranchRisk" data-label="Recomendaciones críticas"></canvas>@else<div class="empty-state">Sin riesgos críticos pendientes</div>@endif</div></section></div>
    <div class="col-xl-4"><section class="panel-card h-100"><div class="panel-heading"><div><span class="eyebrow">Reposición</span><h2>Mayor necesidad sugerida</h2></div></div><div class="chart-frame">@if(count($topReplenishmentChart))<canvas data-chart="mlTopReplenishment" data-label="Unidades sugeridas"></canvas>@else<div class="empty-state">Sin reposiciones pendientes</div>@endif</div></section></div>
</div>
@php
$chartData = [
    'mlDemand' => $forecastChart,
    'mlRisk' => $riskChart,
    'mlModel' => $modelComparison,
    'mlBranchRisk' => $branchRiskChart,
    'mlTopReplenishment' => $topReplenishmentChart,
    'mlHistory' => $demandHistory,
    'mlForecastBand' => $forecastBand,
];
@endphp
<script type="application/json" id="dashboard-chart-data">@json($chartData)</script>

<section class="panel-card mt-4">
    <div class="panel-heading"><div><span class="eyebrow">Flujo comprensible</span><h2>Cómo funciona</h2></div></div>
    <div class="row g-3">
        @foreach([
            ['Datos históricos', 'Se agrupan por producto y sucursal.'],
            ['Variables', 'Se usan rezagos y promedios móviles de 7, 14 y 28 días.'],
            ['Entrenamiento', 'HistGradientBoostingRegressor aprende patrones históricos.'],
            ['Validación temporal', 'Se evalúa con un periodo posterior independiente.'],
            ['Comparación', 'El modelo compite contra una línea base simple.'],
            ['Pronóstico', 'Se calcula a 30 días con intervalo inferior y superior.'],
            ['Recomendación', 'Stock, reservas, seguridad y horizonte generan la sugerencia.'],
            ['Decisión humana', 'Una persona aprueba o rechaza; no se crea una compra.'],
        ] as $step)
        <div class="col-md-3"><article class="workspace-card h-100"><span class="workspace-icon">{{ mb_substr($step[0], 0, 1) }}</span><div><h3 class="h6">{{ $step[0] }}</h3><p class="small mb-0">{{ $step[1] }}</p></div></article></div>
        @endforeach
    </div>
</section>

<section class="panel-card mt-4">
    <div class="panel-heading"><div><span class="eyebrow">Ayuda ejecutiva</span><h2>Lectura de métricas</h2></div></div>
    <div class="row g-3">
        <div class="col-md-4"><strong>WAPE</strong><p class="small text-secondary mb-0">Porcentaje de error ponderado. Convierte el decimal almacenado en porcentaje; menor es mejor.</p></div>
        <div class="col-md-4"><strong>MAE</strong><p class="small text-secondary mb-0">Error medio en unidades pronosticadas, independiente del tamaño relativo.</p></div>
        <div class="col-md-4"><strong>Sesgo</strong><p class="small text-secondary mb-0">Negativo: subestima; positivo: sobreestima. No demuestra causalidad.</p></div>
        <div class="col-md-4"><strong>Línea base</strong><p class="small text-secondary mb-0">Promedio móvil sencillo usado como referencia comparable.</p></div>
        <div class="col-md-4"><strong>Intervalo</strong><p class="small text-secondary mb-0">Rango razonable de incertidumbre, no una garantía futura.</p></div>
        <div class="col-md-4"><strong>Champion / Candidate</strong><p class="small text-secondary mb-0">Champion supera la referencia y está activo; candidate queda evaluado sin sustituirlo.</p></div>
    </div>
</section>

<section class="panel-card mt-4">
    <div class="panel-heading"><div><span class="eyebrow">Horizonte de {{ config('machine-learning.forecast_horizon_days') }} días</span><h2>Recomendaciones por sucursal</h2></div></div>
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3"><label class="form-label" for="ml-search">Buscar SKU o producto</label><input id="ml-search" class="form-control" name="search" value="{{ request('search') }}" placeholder="SKU o nombre"></div>
        <div class="col-md-2"><label class="form-label" for="ml-branch">Sucursal</label><select id="ml-branch" class="form-select" name="branch"><option value="">Todas</option>@foreach($allowedBranches as $allowed)<option value="{{ $allowed->id }}" @selected(request('branch')==$allowed->id)>{{ $allowed->code }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label" for="ml-category">Categoría</label><select id="ml-category" class="form-select" name="category"><option value="">Todas</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category')==$category->id)>{{ $category->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label" for="ml-risk">Riesgo</label><select id="ml-risk" class="form-select" name="risk"><option value="">Todos</option>@foreach(['critical'=>'Crítico','high'=>'Alto','medium'=>'Medio','low'=>'Bajo'] as $value=>$label)<option value="{{ $value }}" @selected(request('risk')==$value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label" for="ml-status">Estado</label><select id="ml-status" class="form-select" name="status"><option value="">Todos</option>@foreach(['pending'=>'Pendiente','approved'=>'Aprobada','rejected'=>'Rechazada'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')==$value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label" for="ml-sort">Ordenar sugerencia</label><select id="ml-sort" class="form-select" name="sort"><option value="quantity_desc" @selected(request('sort','quantity_desc')==='quantity_desc')>Mayor cantidad</option><option value="quantity_asc" @selected(request('sort')==='quantity_asc')>Menor cantidad</option></select></div>
        <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100">Filtrar</button></div>
    </form>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Producto</th><th>Sucursal</th><th>Físico</th><th>Reservado</th><th>Disponible</th><th>Pronóstico</th><th>Intervalo</th><th>Sugerencia</th><th>Riesgo</th><th>Estado</th><th>Detalle</th><th>Decisión</th></tr></thead>
            <tbody>
            @forelse($recommendations as $item)
            <tr>
                <td><strong>{{ $item->product->name }}</strong><small class="d-block text-secondary">{{ $item->product->sku }}</small></td>
                <td>{{ $item->branch->code }}</td>
                <td>{{ number_format((float)$item->physical_quantity_snapshot, 2) }}</td>
                <td>{{ number_format((float)$item->reserved_quantity_snapshot, 2) }}</td>
                <td>{{ number_format((float)$item->physical_quantity_snapshot-(float)$item->reserved_quantity_snapshot, 2) }}</td>
                <td>{{ number_format((float)$item->forecast->predicted_quantity, 2) }}</td>
                <td>{{ number_format((float)$item->forecast->lower_bound, 1) }}–{{ number_format((float)$item->forecast->upper_bound, 1) }}</td>

                <td><strong>{{ number_format((float)$item->recommended_quantity, 0) }}</strong></td>
                <td><span class="badge text-bg-{{ ['critical'=>'danger','high'=>'warning','medium'=>'info','low'=>'success'][$item->risk_level] }}">{{ strtoupper($item->risk_level) }}</span></td>
                <td><span class="badge text-bg-secondary">{{ strtoupper($item->status) }}</span>@if($item->decisionMaker)<small class="d-block">{{ $item->decisionMaker->name }}</small>@endif</td>
                <td><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#recommendationDetailModal" data-recommendation-detail="{{ $item->id }}">Ver detalle</button></td>
                <td>
                    @if($item->status==='pending' && $access->canDecide(auth()->user(), $item->branch))
                    <div class="d-flex gap-1">
                        <form method="POST" action="{{ route('ml.recommendations.decide', $item) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="approved"><button class="btn btn-sm btn-primary" data-confirm="¿Aprobar esta recomendación? Esto no crea una compra automáticamente.">Aprobar</button></form>
                        <form method="POST" action="{{ route('ml.recommendations.decide', $item) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="rejected"><button class="btn btn-sm btn-outline-danger" data-confirm="¿Rechazar esta recomendación?">Rechazar</button></form>
                    </div>
                    @else
                    <span class="text-secondary">Solo lectura</span>
                    @endif
                </td>
            </tr>
            <tr class="table-light"><td colspan="12" class="small"><strong>Cómo se obtuvo:</strong> {{ $item->explanation['reason'] ?? 'Demanda pronosticada, stock disponible y límite de reposición.' }} <span class="text-secondary">La decisión final es humana y no genera una compra.</span></td></tr>
            @empty
            <tr><td colspan="12"><div class="empty-state"><h3>Sin recomendaciones visibles</h3><p>Prueba a cambiar los filtros o el ámbito autorizado.</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $recommendations->links() }}
</section>

<section class="panel-card mt-4">
    <div class="panel-heading"><div><span class="eyebrow">Solo lectura</span><h2>Simulador ¿qué pasaría si?</h2></div></div>
    <form method="GET" class="row g-2">
        <div class="col-md-3"><label class="form-label" for="simulation-product">Producto</label><select id="simulation-product" class="form-select" name="simulation_product" required>@foreach($recommendations as $item)<option value="{{ $item->product_id }}" @selected(request('simulation_product')==$item->product_id)>{{ $item->product->sku }} · {{ $item->product->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label" for="simulation-branch">Sucursal</label><select id="simulation-branch" class="form-select" name="simulation_branch">@foreach($allowedBranches as $allowed)<option value="{{ $allowed->id }}" @selected(request('simulation_branch', $branch->id)==$allowed->id)>{{ $allowed->code }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label" for="simulation-factor">Variación demanda</label><input id="simulation-factor" class="form-control" type="number" name="simulation_factor" min="0.5" max="2" step="0.1" value="{{ request('simulation_factor', 1) }}"></div>
        <div class="col-md-2"><label class="form-label" for="simulation-safety">Stock seguridad</label><input id="simulation-safety" class="form-control" type="number" name="simulation_safety" min="0" step="1" value="{{ request('simulation_safety', 0) }}"></div>
        <div class="col-md-2"><label class="form-label" for="simulation-lead">Plazo (días)</label><input id="simulation-lead" class="form-control" type="number" name="simulation_lead" min="1" max="90" value="{{ request('simulation_lead', 14) }}"></div>
        <div class="col-md-2"><label class="form-label" for="simulation-available">Stock disponible</label><input id="simulation-available" class="form-control" type="number" name="simulation_available" min="0" step="1" value="{{ request('simulation_available', $recommendations->first() ? (float) $recommendations->first()->physical_quantity_snapshot - (float) $recommendations->first()->reserved_quantity_snapshot : 0) }}"></div>
        <div class="col-md-1 d-flex align-items-end"><button class="btn btn-outline-primary w-100">Simular</button></div>
    </form>
    <div class="alert alert-secondary mt-3 mb-0" role="note">
        <strong>Esta es una simulación académica</strong> y no modifica inventarios, compras ni pronósticos almacenados.
    </div>
    @if($simulation)
    <div class="alert alert-info mt-3 mb-0">
        <strong>Resultado hipotético:</strong>
        Demanda ajustada {{ number_format($simulation['demand'], 2) }} unidades ·
        Disponible usado {{ number_format($simulation['available'], 2) }} ·
        Stock proyectado {{ number_format($simulation['projected_stock'], 2) }} ·
        Posible faltante {{ number_format(max(0, -$simulation['projected_stock']), 2) }} ·
        Cantidad sugerida {{ number_format($simulation['suggested'], 0) }}.
    </div>
    @endif
    <div class="row g-4 mt-1">
        <div class="col-xl-6"><section class="panel-card h-100"><div class="panel-heading"><div><span class="eyebrow">Producto seleccionado</span><h2>Historial reciente</h2></div></div><div class="chart-frame">@if(count($demandHistory))<canvas data-chart="mlHistory" data-label="Demanda observada" data-type="line"></canvas>@else<div class="empty-state">Sin historial sintético visible</div>@endif</div></section></div>
        <div class="col-xl-6"><section class="panel-card h-100"><div class="panel-heading"><div><span class="eyebrow">Pronóstico activo</span><h2>Demanda e intervalo</h2></div></div><div class="chart-frame">@if(count($forecastBand))<canvas data-chart="mlForecastBand" data-label="Demanda pronosticada"></canvas>@else<div class="empty-state">Sin pronóstico visible</div>@endif</div></section></div>
    </div>
</section>

<section class="panel-card mt-4">
    <div class="panel-heading"><div><span class="eyebrow">Versionado</span><h2>Historial de modelos</h2></div></div>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Versión</th><th>Algoritmo</th><th>Estado</th><th>Entrenamiento</th><th>Filas</th><th>Periodo</th><th>WAPE</th><th>Base</th><th>MAE</th><th>Sesgo</th><th>Resultado</th><th>Artefacto</th></tr></thead><tbody>
    @forelse($modelHistory as $version)
    <tr><td><code>{{ substr($version->public_id, 0, 8) }}</code></td><td>{{ $version->algorithm }}</td><td><span class="badge text-bg-{{ $version->status==='active'?'success':($version->status==='candidate'?'warning':'secondary') }}">{{ strtoupper($version->status) }}</span></td><td>{{ $version->trained_at->format('d/m/Y H:i') }}<small class="d-block text-secondary">{{ $version->trainer?->name }}</small></td><td>{{ number_format($version->training_rows, 0) }} / {{ number_format($version->metrics['validation_rows'] ?? 0, 0) }}</td><td>{{ $version->trained_from?->format('d/m/Y') }}–{{ $version->trained_until?->format('d/m/Y') }}</td><td>{{ number_format(($version->metrics['wape'] ?? 0)*100,2) }}%</td><td>{{ number_format(($version->metrics['baseline_wape'] ?? 0)*100,2) }}%</td><td>{{ number_format($version->metrics['mae'] ?? 0,2) }}</td><td>{{ number_format($version->metrics['bias'] ?? 0,2) }}</td><td>{{ ($version->metrics['champion'] ?? false) ? 'CHAMPION' : 'CANDIDATE' }}</td><td><code title="Fragmento seguro del SHA-256">{{ substr($version->artifact_sha256,0,12) }}</code></td></tr>
    @empty
    <tr><td colspan="12" class="text-secondary">Sin versiones registradas.</td></tr>
    @endforelse
    </tbody></table></div>
</section>
@php
$recommendationDetails = [];
foreach ($recommendations as $item) {
   $recommendationDetails[$item->id] = [
       'product' => $item->product->name,
       'sku' => $item->product->sku,
       'branch' => $item->branch->code.' · '.$item->branch->name,
       'physical' => (float) $item->physical_quantity_snapshot,
       'reserved' => (float) $item->reserved_quantity_snapshot,
       'available' => max(0, (float) $item->physical_quantity_snapshot - (float) $item->reserved_quantity_snapshot),
       'forecast' => (float) $item->forecast->predicted_quantity,
       'lower' => (float) $item->forecast->lower_bound,
       'upper' => (float) $item->forecast->upper_bound,
       'safety' => (float) $item->safety_stock,
       'suggested' => (float) $item->recommended_quantity,
       'risk' => $item->risk_level,
       'reason' => $item->explanation['reason'] ?? 'Demanda pronosticada, stock disponible y límite de reposición.',
       'model' => $model?->algorithm,
       'generated' => $item->forecast->generated_at?->format('d/m/Y H:i'),
   ];
}
@endphp
<script type="application/json" id="recommendation-details">@json($recommendationDetails)</script>

<div class="modal fade" id="recommendationDetailModal" tabindex="-1" aria-labelledby="recommendationDetailLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><span class="eyebrow">Explicación ejecutable</span><h2 class="h5 mb-0" id="recommendationDetailLabel">Detalle de recomendación</h2></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><strong>Producto</strong><div id="detail-product" class="mt-1"></div></div>
                    <div class="col-md-6"><strong>Sucursal</strong><div id="detail-branch" class="mt-1"></div></div>
                    <div class="col-md-4"><span class="metric-label">Stock físico</span><strong class="d-block" id="detail-physical"></strong></div>
                    <div class="col-md-4"><span class="metric-label">Reservado</span><strong class="d-block" id="detail-reserved"></strong></div>
                    <div class="col-md-4"><span class="metric-label">Disponible</span><strong class="d-block" id="detail-available"></strong></div>
                    <div class="col-md-4"><span class="metric-label">Pronóstico</span><strong class="d-block" id="detail-forecast"></strong></div>
                    <div class="col-md-4"><span class="metric-label">Intervalo</span><strong class="d-block" id="detail-range"></strong></div>
                    <div class="col-md-4"><span class="metric-label">Stock seguridad</span><strong class="d-block" id="detail-safety"></strong></div>
                    <div class="col-md-4"><span class="metric-label">Reposición sugerida</span><strong class="d-block" id="detail-suggested"></strong></div>
                    <div class="col-md-4"><span class="metric-label">Riesgo</span><strong class="d-block" id="detail-risk"></strong></div>
                    <div class="col-md-4"><span class="metric-label">Modelo</span><strong class="d-block" id="detail-model"></strong></div>
                    <div class="col-12"><span class="metric-label">Razón principal</span><p id="detail-reason" class="mb-1"></p></div>
                    <div class="col-12"><span class="metric-label">Fecha de generación</span><p id="detail-generated" class="mb-0"></p></div>
                </div>
                <div class="alert alert-light border mt-3 mb-0">Esta explicación resume datos reales y una regla de reposición. No demuestra causalidad ni genera una compra automáticamente.</div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
