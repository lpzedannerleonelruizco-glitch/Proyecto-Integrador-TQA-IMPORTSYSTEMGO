@extends('layouts.app')
@section('title', 'Inventario')
@section('page-label', 'Inventario y kardex')
@section('content')
<section class="page-hero">
    <div><span class="eyebrow">Centro de existencias</span><h1>Inventario por almacén</h1><p>{{ $branch->name }} · Dashboard, saldos, valorización y trazabilidad.</p></div>
    <div class="d-flex flex-wrap gap-2">
        @if(app(\App\Services\Authorization\ScopedAuthorizationService::class)->allows(auth()->user(), 'inventory.stock.manage', $branch))
            <a class="btn btn-light" href="{{ route('inventory.receipts.create') }}">Nuevo ingreso</a>
        @endif
        <a class="btn btn-outline-light" href="{{ route('inventory.products.index') }}">Productos</a>
    </div>
</section>
<section class="metric-grid">
    <article class="metric-card"><span class="metric-accent bg-primary"></span><span class="metric-label">Productos activos</span><strong>{{ number_format($metrics['products']) }}</strong><small>Catálogo habilitado</small></article>
    <article class="metric-card"><span class="metric-accent bg-success"></span><span class="metric-label">Stock físico</span><strong>{{ number_format((float) $metrics['physical'], 2) }}</strong><small>Unidades en almacenes activos</small></article>
    <article class="metric-card"><span class="metric-accent bg-info"></span><span class="metric-label">Disponible</span><strong>{{ number_format((float) $metrics['available'], 2) }}</strong><small>Físico menos reservas vigentes</small></article>
    <article class="metric-card"><span class="metric-accent bg-warning"></span><span class="metric-label">Valorización estimada</span><strong>Bs {{ number_format((float) $metrics['value'], 2) }}</strong><small>Al costo promedio vigente</small></article>
</section>
<div class="row g-4 mt-1">
    <div class="col-xl-8"><section class="panel-card h-100">
        <div class="panel-heading"><div><span class="eyebrow">Existencias</span><h2>Saldo actual por almacén</h2></div><span class="badge-soft">{{ $balancesPage->total() }} registros</span></div>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Producto</th><th>Almacén</th><th>Físico</th><th>Reservado</th><th>Disponible</th></tr></thead><tbody>
        @forelse($balancesPage as $balance)
            @php($available = (float) $balance->physical_quantity - (float) $balance->reserved_quantity)
            <tr><td><a class="fw-semibold text-decoration-none" href="{{ route('inventory.products.show', $balance->product) }}">{{ $balance->product->sku }}</a><br>{{ $balance->product->name }}</td><td>{{ $balance->warehouse->name }}</td><td>{{ number_format((float) $balance->physical_quantity, 2) }}</td><td>{{ number_format((float) $balance->reserved_quantity, 2) }}</td><td><span class="badge {{ $available <= (float) $balance->product->minimum_stock ? 'text-bg-warning' : 'text-bg-success' }}">{{ number_format($available, 2) }}</span></td></tr>
        @empty<tr><td colspan="5"><div class="empty-state"><h3>Sin saldos registrados</h3><p>Confirma un ingreso para crear existencias mediante kardex.</p></div></td></tr>@endforelse
        </tbody></table></div>{{ $balancesPage->links() }}
    </section></div>
    <div class="col-xl-4"><section class="panel-card h-100"><div class="panel-heading"><div><span class="eyebrow">Alertas</span><h2>Bajo mínimo</h2></div><span class="badge-soft">{{ $lowStock->count() }} alertas</span></div>
        @forelse($lowStock as $product)<a class="d-flex justify-content-between align-items-center gap-3 py-3 border-bottom text-decoration-none" href="{{ route('inventory.products.show', $product->id) }}"><span><strong>{{ $product->sku }}</strong><br><small class="text-secondary">{{ $product->name }}</small></span><span class="badge text-bg-warning">Min. {{ number_format((float) $product->minimum_stock, 2) }}</span></a>@empty<div class="empty-state"><h3>Sin alertas</h3><p>Todos los productos están sobre el mínimo configurado.</p></div>@endforelse
        @if($categoryTotals->isNotEmpty())<div class="mt-4"><span class="eyebrow">Stock por categoría</span>@php($maxCategory = max(1, (float) $categoryTotals->max('quantity')))@foreach($categoryTotals as $category)<div class="mt-3"><div class="d-flex justify-content-between small"><span>{{ $category->name }}</span><strong>{{ number_format((float) $category->quantity, 2) }}</strong></div><div class="progress mt-1" role="progressbar" aria-label="Stock {{ $category->name }}"><div class="progress-bar" style="width: {{ min(100, ((float) $category->quantity / $maxCategory) * 100) }}%"></div></div></div>@endforeach</div>@endif
    </section></div>
</div>
<div class="row g-4 mt-1">
    <div class="col-12"><section class="panel-card"><div class="panel-heading"><div><span class="eyebrow">Kardex reciente</span><h2>Últimos movimientos</h2></div><a href="{{ route('inventory.products.index') }}" class="btn btn-sm btn-outline-primary">Trazabilidad por SKU</a></div>
        @forelse($movements as $movement)<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom py-3"><div><strong>{{ $movement->product->sku }} · {{ $movement->product->name }}</strong><br><small class="text-secondary">{{ $movement->warehouse->name }} · {{ ucfirst(str_replace('_', ' ', $movement->type)) }} · {{ $movement->actor->name }} · {{ $movement->occurred_at->format('d/m/Y H:i') }}</small></div><strong class="{{ (float) $movement->quantity_change >= 0 ? 'text-success' : 'text-danger' }}">{{ (float) $movement->quantity_change > 0 ? '+' : '' }}{{ number_format((float) $movement->quantity_change, 2) }}</strong></div>@empty<p class="text-secondary mb-0">Todavía no hay movimientos registrados.</p>@endforelse
    </section></div>
</div>
@endsection
