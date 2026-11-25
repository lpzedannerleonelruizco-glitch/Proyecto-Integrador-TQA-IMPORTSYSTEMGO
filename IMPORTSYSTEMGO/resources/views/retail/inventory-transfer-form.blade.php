@extends('layouts.app')
@section('title', 'Solicitar transferencia')
@section('page-label', 'Inventarios · transferencias')
@section('content')
<section class="page-hero"><div><span class="eyebrow">Movimiento entre almacenes</span><h1>Solicitar transferencia</h1><p>Requiere aprobación; el destino no recibirá stock hasta confirmar la recepción.</p></div></section>
<form class="panel-card mt-4" method="POST" action="{{ route('inventory.transfers.store') }}" id="transfer-form">@csrf<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
<div class="row g-3"><div class="col-md-6"><label class="form-label">Almacén origen</label><select class="form-select" name="source_warehouse_id" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($warehouse->branch_id === $branch->id)>{{ $warehouse->branch->code }} · {{ $warehouse->name }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Almacén destino</label><select class="form-select" name="destination_warehouse_id" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->branch->code }} · {{ $warehouse->name }}</option>@endforeach</select></div>
<div class="col-12"><label class="form-label">Motivo</label><textarea class="form-control" name="reason" rows="2" maxlength="500" required>{{ old('reason') }}</textarea></div>
<div class="col-12"><div class="d-flex justify-content-between align-items-center"><h2 class="h6 mb-0">Productos</h2><button class="btn btn-sm btn-outline-primary" type="button" id="add-transfer-item">Agregar línea</button></div><div id="transfer-items" class="mt-3"><div class="row g-2 transfer-item mb-2"><div class="col-md-8"><select class="form-select" name="items[0][product_id]" required><option value="">Producto</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} · {{ $product->name }}</option>@endforeach</select></div><div class="col-md-4"><input class="form-control" type="number" name="items[0][quantity]" min="0.01" step="0.01" placeholder="Cantidad" required></div></div></div></div>
</div><div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('inventory.transfers.index') }}" class="btn btn-light">Cancelar</a><button class="btn btn-primary">Solicitar aprobación</button></div></form>
@push('scripts')
<script>
let transferItemIndex = 1;
document.getElementById('add-transfer-item')?.addEventListener('click', () => {
    const first = document.querySelector('.transfer-item');
    const row = first.cloneNode(true);
    row.querySelectorAll('select,input').forEach((field) => {
        field.name = field.name.replace(/\[0\]/, `[${transferItemIndex}]`);
        field.value = '';
    });
    document.getElementById('transfer-items').appendChild(row);
    transferItemIndex += 1;
});
</script>
@endpush
@endsection
