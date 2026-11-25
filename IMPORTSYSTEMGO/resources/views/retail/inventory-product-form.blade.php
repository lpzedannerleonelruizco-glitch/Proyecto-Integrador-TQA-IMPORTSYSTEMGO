@extends('layouts.app')
@section('title', $product->exists ? 'Editar producto' : 'Registrar producto')
@section('page-label', 'Inventarios · productos')
@section('content')
<section class="page-hero"><div><span class="eyebrow">Catálogo</span><h1>{{ $product->exists ? 'Editar producto' : 'Nuevo producto' }}</h1><p>El costo histórico solo cambia mediante una recepción autorizada.</p></div></section>
<form class="panel-card mt-4" method="POST" action="{{ $product->exists ? route('inventory.products.update', $product) : route('inventory.products.store') }}" enctype="multipart/form-data">
    @csrf @if($product->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="name">Nombre comercial</label><input id="name" class="form-control" name="name" value="{{ old('name', $product->name) }}" required maxlength="160"></div>
        <div class="col-md-3"><label class="form-label" for="sku">SKU único</label><input id="sku" class="form-control text-uppercase" name="sku" value="{{ old('sku', $product->sku) }}" required maxlength="48"></div>
        <div class="col-md-3"><label class="form-label" for="barcode">Código de barras</label><input id="barcode" class="form-control" name="barcode" value="{{ old('barcode', $product->barcode) }}" maxlength="64"></div>
        <div class="col-md-4"><label class="form-label" for="category_id">Categoría</label><select id="category_id" name="category_id" class="form-select" required><option value="">Selecciona</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label" for="brand_id">Marca</label><select id="brand_id" name="brand_id" class="form-select" required><option value="">Selecciona</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected(old('brand_id', $product->brand_id) == $brand->id)>{{ $brand->name }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label" for="model">Modelo</label><input id="model" name="model" class="form-control" value="{{ old('model', $product->model) }}" maxlength="100"></div>
        <div class="col-md-3"><label class="form-label" for="list_price">Precio de venta (Bs)</label><input id="list_price" type="number" min="0" step="0.01" name="list_price" class="form-control" value="{{ old('list_price', $product->list_price ?? '0.00') }}" required></div>
        <div class="col-md-3"><label class="form-label">Costo vigente</label><input class="form-control" value="Bs {{ number_format((float) ($product->acquisition_cost ?? 0), 2) }}" disabled><small class="text-secondary">Se actualiza al ingresar mercadería.</small></div>
        <div class="col-md-3"><label class="form-label" for="minimum_stock">Stock mínimo</label><input id="minimum_stock" type="number" min="0" step="0.01" name="minimum_stock" class="form-control" value="{{ old('minimum_stock', $product->minimum_stock ?? '0.00') }}" required></div>
        <div class="col-md-3"><label class="form-label" for="unit_of_measure">Unidad de medida</label><input id="unit_of_measure" name="unit_of_measure" class="form-control" value="{{ old('unit_of_measure', $product->unit_of_measure ?? 'unidad') }}" maxlength="24" required></div>
        <div class="col-md-4"><label class="form-label" for="tracking_mode">Control de inventario</label><select id="tracking_mode" name="tracking_mode" class="form-select" required><option value="quantity" @selected(old('tracking_mode', $product->tracking_mode ?? 'quantity') === 'quantity')>Por cantidad</option><option value="serial" @selected(old('tracking_mode', $product->tracking_mode) === 'serial')>Número de serie</option><option value="imei" @selected(old('tracking_mode', $product->tracking_mode) === 'imei')>IMEI</option></select></div>
        <div class="col-md-4"><label class="form-label" for="is_active">Estado</label><select id="is_active" name="is_active" class="form-select" required><option value="1" @selected((string) old('is_active', (int) ($product->is_active ?? true)) === '1')>Activo</option><option value="0" @selected((string) old('is_active', (int) ($product->is_active ?? true)) === '0')>Inactivo</option></select></div>
        <div class="col-md-4"><label class="form-label" for="image">Imagen (JPG, PNG o WebP; máx. 4 MB)</label><input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp" class="form-control"></div>
        <div class="col-12"><label class="form-label" for="description">Descripción</label><textarea id="description" name="description" class="form-control" rows="4" maxlength="5000">{{ old('description', $product->description) }}</textarea></div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-light" href="{{ route('inventory.products.index') }}">Cancelar</a><button class="btn btn-primary">Guardar producto</button></div>
</form>
@endsection
