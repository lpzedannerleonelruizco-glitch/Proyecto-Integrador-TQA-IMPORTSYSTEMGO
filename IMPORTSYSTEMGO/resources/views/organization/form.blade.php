@extends('layouts.app')
@section('title', $definition['label'])
@section('content')
<h1 class="h2 mb-4">{{ $record->exists ? 'Editar' : 'Crear' }} · {{ $definition['label'] }}</h1>
<div class="card border-0 shadow-sm" style="max-width: 48rem"><div class="card-body p-4">
<p class="text-secondary">Los cambios quedan auditados. La sucursal se determina en el servidor. No se eliminan registros.</p>
<form method="POST" action="{{ $record->exists ? route('organization.update', [$resource, $record->id]) : route('organization.store', $resource) }}">
@csrf @if($record->exists) @method('PUT') @endif
<div class="row g-3">
@foreach($definition['fields'] as $field => $label)
    @php
        $value = old($field, $record->{$field} instanceof \Carbon\CarbonInterface ? $record->{$field}->format('Y-m-d') : ($record->{$field} ?? match($field) { 'timezone' => 'America/La_Paz', 'is_active' => 1, 'status' => 'active', default => '' }));
        $value = $field === 'is_active' ? (int) $value : $value;
    @endphp
    <div class="col-md-6"><label for="{{ $field }}" class="form-label">{{ $label }}</label>
    @if(isset($definition['options'][$field]))
        <select class="form-select" id="{{ $field }}" name="{{ $field }}">@foreach($definition['options'][$field] as $key => $text)<option value="{{ $key }}" @selected((string)$value === (string)$key)>{{ $text }}</option>@endforeach</select>
    @else
        <input class="form-control" id="{{ $field }}" name="{{ $field }}" type="{{ in_array($field, ['hired_at', 'terminated_at']) ? 'date' : 'text' }}" value="{{ $value }}" @required(!in_array($field, ['legal_address', 'terminated_at']))>
    @endif
    </div>
@endforeach
</div>
<div class="mt-4 d-flex gap-3"><button class="btn btn-primary">Guardar cambios</button><a class="btn btn-outline-secondary" href="{{ route('organization.index', $resource) }}">Cancelar</a></div>
</form>
</div></div>
@endsection
