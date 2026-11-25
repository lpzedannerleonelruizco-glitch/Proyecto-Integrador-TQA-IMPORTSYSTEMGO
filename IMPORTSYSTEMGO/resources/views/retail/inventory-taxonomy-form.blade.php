@extends('layouts.app')
@section('title', $record ? 'Editar catálogo' : 'Nuevo catálogo')
@section('page-label', 'Inventarios · catálogos')
@section('content')
@php($routeType = $type === 'categories' ? 'categorias' : 'marcas')
@php($label = $type === 'categories' ? 'categoría' : 'marca')
<section class="page-hero"><div><span class="eyebrow">Catálogo maestro</span><h1>{{ $record ? 'Editar' : 'Crear' }} {{ $label }}</h1><p>Evita duplicados: el código es único en toda la organización.</p></div></section>
<form class="panel-card mt-4" method="POST" action="{{ $record ? route('inventory.taxonomy.update', [$routeType, $record->id]) : route('inventory.taxonomy.store', $routeType) }}">@csrf @if($record) @method('PUT') @endif
<div class="row g-3"><div class="col-md-4"><label class="form-label" for="code">Código único</label><input id="code" name="code" class="form-control text-uppercase" value="{{ old('code', $record?->code) }}" required maxlength="32"></div><div class="col-md-5"><label class="form-label" for="name">Nombre</label><input id="name" name="name" class="form-control" value="{{ old('name', $record?->name) }}" required maxlength="100"></div><div class="col-md-3"><label class="form-label" for="is_active">Estado</label><select id="is_active" name="is_active" class="form-select" required><option value="1" @selected((string) old('is_active', (int) ($record?->is_active ?? true)) === '1')>Activo</option><option value="0" @selected((string) old('is_active', (int) ($record?->is_active ?? true)) === '0')>Inactivo</option></select></div></div><div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('inventory.taxonomy.index', $routeType) }}" class="btn btn-light">Cancelar</a><button class="btn btn-primary">Guardar {{ $label }}</button></div></form>
@endsection
