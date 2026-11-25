@extends('layouts.app')
@section('title', 'Nuevo ingreso')
@section('page-label', 'Inventarios · recepción')
@section('content')
<section class="page-hero"><div><span class="eyebrow">Recepción de mercadería</span><h1>Nuevo ingreso</h1><p>{{ $branch->name }} · El saldo se modifica solo al confirmar una recepción auditada.</p></div></section>
<form class="panel-card mt-4" method="POST" action="{{ route('inventory.receipts.store') }}">@csrf
<div class="row g-3">
    <div class="col-md-6"><label class="form-label" for="warehouse_id">Almacén</label><select id="warehouse_id" name="warehouse_id" class="form-select" required><option value="">Selecciona un almacén</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div>
    <div class="col-md-6"><label class="form-label" for="product_id">Producto</label><select id="product_id" name="product_id" class="form-select" required><option value="">Selecciona un producto</option>@foreach($products as $product)<option value="{{ $product->id }}" data-tracking="{{ $product->tracking_mode ?? ($product->requires_serial ? 'serial' : 'quantity') }}" @selected(old('product_id') == $product->id)>{{ $product->sku }} · {{ $product->name }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label" for="quantity">Cantidad recibida</label><input id="quantity" type="number" step="0.01" min="0.01" name="quantity" value="{{ old('quantity') }}" class="form-control" required></div>
    <div class="col-md-4"><label class="form-label" for="unit_cost">Costo unitario (Bs)</label><input id="unit_cost" type="number" step="0.01" min="0" name="unit_cost" value="{{ old('unit_cost') }}" class="form-control"></div>
    <div class="col-md-4"><label class="form-label" for="reference">Documento / referencia</label><input id="reference" name="reference" value="{{ old('reference') }}" maxlength="80" class="form-control" required placeholder="Ej. OC-2026-001"></div>
    <div class="col-12"><label class="form-label" for="identifiers_text">Números de serie o IMEI (uno por línea)</label><textarea id="identifiers_text" name="identifiers_text" class="form-control" rows="4" maxlength="200000" placeholder="Solo para productos con seguimiento serializado"></textarea><div class="form-text">El sistema exige un identificador único por unidad en productos serializados.</div></div>
    <div class="col-12"><label class="form-label" for="observations">Observaciones</label><textarea id="observations" name="observations" class="form-control" rows="3" maxlength="500">{{ old('observations') }}</textarea></div>
</div>
<div class="alert alert-info mt-4 mb-0">La operación se registra con bloqueo transaccional; no se aceptan saldos negativos ni duplicación de la referencia.</div>
<div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('inventory.index') }}" class="btn btn-light">Cancelar</a><button class="btn btn-primary" data-confirm="¿Confirmar el ingreso y actualizar el kardex?">Confirmar ingreso</button></div>
</form>
@push('scripts')
<script>
document.getElementById('product_id')?.addEventListener('change', function () {
    const mode = this.selectedOptions[0]?.dataset.tracking || 'quantity';
    document.getElementById('identifiers_text').required = mode !== 'quantity';
    document.getElementById('quantity').step = mode === 'quantity' ? '0.01' : '1';
});
</script>
@endpush
@endsection
