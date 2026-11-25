@extends('layouts.app')
@section('title', $customer->exists ? 'Editar cliente' : 'Registrar cliente')
@section('page-label', 'Gestión de clientes')
@section('content')
<section class="page-hero"><div><span class="eyebrow">{{ $customer->exists ? 'Actualización' : 'Nuevo registro' }}</span><h1>{{ $customer->exists ? 'Editar cliente' : 'Registrar cliente' }}</h1><p>Los documentos son opcionales; si se informan, deben ser únicos dentro de la sucursal.</p></div><a class="btn btn-light" href="{{ route('customers.index') }}">Volver</a></section>
<form class="panel-card mt-4" method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}">@csrf @if($customer->exists)@method('PUT')@endif @include('customers._fields')<div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-light" href="{{ route('customers.index') }}">Cancelar</a><button class="btn btn-primary">Guardar cliente</button></div></form>
@endsection
