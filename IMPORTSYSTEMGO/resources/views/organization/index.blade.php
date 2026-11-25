@extends('layouts.app')
@section('title', $definition['label'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 gap-3">
    <div><p class="text-primary small text-uppercase mb-1">Organización</p><h1 class="h2">{{ $definition['label'] }}</h1></div>
    @if($canManage)<a class="btn btn-primary" href="{{ route('organization.create', $resource) }}">Crear registro</a>@endif
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr>@foreach($definition['fields'] as $label)<th scope="col">{{ $label }}</th>@endforeach @if($canManage)<th scope="col">Acciones</th>@endif</tr></thead>
    <tbody>@forelse($records as $record)<tr>
        @foreach($definition['fields'] as $field => $label)
        <td>{{ $definition['options'][$field][(string) $record->getRawOriginal($field)] ?? ($record->{$field} instanceof \Carbon\CarbonInterface ? $record->{$field}->format('Y-m-d') : $record->{$field}) }}</td>
        @endforeach
        @if($canManage)<td><a href="{{ route('organization.edit', [$resource, $record->id]) }}">Editar</a></td>@endif
    </tr>@empty<tr><td colspan="{{ count($definition['fields']) + 1 }}" class="text-secondary py-5 text-center">Todavía no hay registros disponibles en este ámbito.</td></tr>@endforelse</tbody>
</table></div></div>
<div class="mt-4">{{ $records->links() }}</div>
@endsection
