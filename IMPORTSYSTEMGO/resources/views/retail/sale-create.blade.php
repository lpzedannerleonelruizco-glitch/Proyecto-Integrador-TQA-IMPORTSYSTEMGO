@extends('layouts.app')
@section('title', 'Nueva venta')
@section('page-label', 'Punto de venta')
@section('content')
<section class="page-hero">
    <div><span class="eyebrow">Venta asistida</span><h1>Nueva venta</h1><p>La mercadería se reserva ahora y se descuenta al completar el cobro.</p></div>
    <a class="btn btn-light" href="{{ route('sales.index') }}">Volver</a>
</section>
<form method="POST" action="{{ route('sales.store') }}" class="panel-card mt-4" data-loading>
    @csrf
    <div class="row g-3 align-items-end">
        <div class="col-lg-8"><label class="form-label" for="sale-customer">Cliente</label><select class="form-select" id="sale-customer" name="customer_id"><option value="">Consumidor final</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->full_name }}{{ $customer->document_number ? ' · '.$customer->document_number : '' }}</option>@endforeach</select></div>
        @if(app(\App\Services\Authorization\ScopedAuthorizationService::class)->allows(auth()->user(), 'customers.manage', $branch))<div class="col-lg-4"><button class="btn btn-outline-primary w-100" type="button" data-bs-toggle="offcanvas" data-bs-target="#quickCustomer">Registrar cliente rápido</button></div>@endif
    </div>
    <hr class="my-4">
    <div class="panel-heading"><div><span class="eyebrow">Detalle</span><h2>Productos</h2></div><button class="btn btn-outline-primary btn-sm" type="button" id="add-sale-line">Agregar línea</button></div>
    <div id="sale-lines"><div class="row g-3 sale-line mb-3"><div class="col-md-9"><select class="form-select" name="items[0][product_id]" required><option value="">Seleccionar producto</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} · {{ $product->name }} · Bs {{ number_format((float) $product->list_price, 2) }}</option>@endforeach</select></div><div class="col-md-3"><input class="form-control" type="number" name="items[0][quantity]" min="1" max="99" value="1" required aria-label="Cantidad"></div></div></div>
    <button class="btn btn-primary" type="submit">Enviar venta a caja</button>
</form>
@if(app(\App\Services\Authorization\ScopedAuthorizationService::class)->allows(auth()->user(), 'customers.manage', $branch))
<div class="offcanvas offcanvas-end" tabindex="-1" id="quickCustomer" aria-labelledby="quickCustomerLabel">
    <div class="offcanvas-header"><div><span class="eyebrow">Sin abandonar la venta</span><h2 class="h5 mb-0" id="quickCustomerLabel">Nuevo cliente</h2></div><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button></div>
    <div class="offcanvas-body"><div class="alert alert-danger d-none" id="quick-customer-errors" role="alert"></div><form id="quick-customer-form" action="{{ route('customers.quick-store') }}" method="POST">@csrf @include('customers._fields', ['customer' => new \App\Models\Customer])<button class="btn btn-primary w-100" type="submit" data-loading-text="Guardando…">Guardar y seleccionar</button></form></div>
</div>
@endif
@endsection
@push('scripts')
<script>
document.getElementById('add-sale-line')?.addEventListener('click', () => {
    const host = document.getElementById('sale-lines'); const row = host.querySelector('.sale-line').cloneNode(true); const index = host.querySelectorAll('.sale-line').length;
    row.querySelector('select').name = `items[${index}][product_id]`; row.querySelector('select').value = ''; row.querySelector('input').name = `items[${index}][quantity]`; row.querySelector('input').value = 1; host.appendChild(row);
});
document.getElementById('quick-customer-form')?.addEventListener('submit', async (event) => {
    event.preventDefault(); const form = event.currentTarget; const button = form.querySelector('button[type="submit"]'); const errors = document.getElementById('quick-customer-errors');
    errors.replaceChildren(); errors.classList.add('d-none'); button.disabled = true;
    try {
        const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } }); const payload = await response.json();
        if (!response.ok) { const list = document.createElement('ul'); list.className = 'mb-0'; Object.values(payload.errors ?? { error: [payload.message ?? 'No se pudo registrar el cliente.'] }).flat().forEach((message) => { const item = document.createElement('li'); item.textContent = message; list.appendChild(item); }); errors.appendChild(list); errors.classList.remove('d-none'); return; }
        document.getElementById('sale-customer').add(new Option(payload.customer.label, payload.customer.id, true, true)); form.reset(); bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('quickCustomer')).hide();
    } catch (_) { errors.textContent = 'No se pudo conectar con el servidor. Intenta nuevamente.'; errors.classList.remove('d-none'); }
    finally { form.classList.remove('loading'); button.disabled = false; }
});
</script>
@endpush
