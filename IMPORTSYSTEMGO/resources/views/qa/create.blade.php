@extends('layouts.app')
@section('title', 'Nueva ejecución')
@section('page-label', 'Nueva ejecución QA')
@section('content')
<section class="page-hero"><div><span class="eyebrow">Orquestador autorizado</span><h1>Crear ejecución</h1><p>Selecciona casos reales. No se aceptan comandos arbitrarios.</p></div></section>
<form class="panel-card mt-4" method="POST" action="{{ route('qa.executions.store') }}">
@csrf
<div class="row g-3">
    <div class="col-md-6"><label class="form-label" for="recipient_email">Correo electrónico</label><input class="form-control" id="recipient_email" name="recipient_email" type="email" required value="{{ old('recipient_email') }}" maxlength="254"></div>
    <div class="col-md-3"><label class="form-label" for="type">Tipo</label><select class="form-select" id="type" name="type" required><option value="functional">Funcional</option><option value="non_functional">No funcional</option><option value="regression">Regresión</option><option value="all">Todos</option><option value="controlled_fail">FAIL CONTROLADO (validación)</option></select></div>
    <div class="col-md-3"><label class="form-label" for="suite_id">Suite</label><select class="form-select" id="suite_id" name="suite_id"><option value="">Todas las seleccionadas</option>@foreach($suites as $suite)<option value="{{ $suite->id }}">{{ $suite->code }} · {{ $suite->name }}</option>@endforeach</select></div>
</div>
<hr>
<div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Casos disponibles</h2><button class="btn btn-light btn-sm" type="button" id="selectAll">Seleccionar todos</button></div>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th><span class="visually-hidden">Seleccionar</span></th><th>Código</th><th>Nombre</th><th>Suite</th><th>Motor declarado</th><th>Automatización real</th></tr></thead><tbody>
@foreach($cases as $case)@php($automationCase = $automation[$case->code] ?? ['status' => 'NOT RUN'])<tr data-case-type="{{ $case->case_type }}" data-category="{{ $case->category }}" data-suite="{{ $case->suite_id }}" data-code="{{ $case->code }}"><td><input class="form-check-input case-checkbox" type="checkbox" name="case_ids[]" value="{{ $case->id }}" id="case{{ $case->id }}" @checked(collect(old('case_ids', []))->contains($case->id))></td><td><label for="case{{ $case->id }}">{{ $case->code }}</label></td><td>{{ $case->name }}</td><td>{{ $case->suite?->code ?? 'Sin suite' }}</td><td><span class="badge-soft">{{ $case->executor }}</span></td><td><strong>{{ $automationCase['status'] }}</strong>@if(isset($automationCase['reason']))<small class="d-block text-secondary">{{ $automationCase['reason'] }}</small>@endif</td></tr>@endforeach
</tbody></table></div>
<p class="text-secondary">Los casos registrados no se consideran automatizados si su estado indica NOT RUN o BLOCKED. La opción FAIL CONTROLADO verifica una validación real y falla solo por una expectativa de demostración invertida.</p>
<div class="d-flex justify-content-end"><button class="btn btn-primary" type="submit" id="runQaExecution">Ejecutar pruebas</button></div>
</form>
@push('scripts')<script>
const typeSelect = document.getElementById('type');
const suiteSelect = document.getElementById('suite_id');
const caseRows = [...document.querySelectorAll('tr[data-code]')];
function filterCases() {
    const type = typeSelect.value;
    const suite = suiteSelect.value;
    caseRows.forEach((row) => {
        const typeMatches = type === 'all'
            || (type === 'functional' && row.dataset.caseType !== 'performance')
            || (type === 'non_functional' && row.dataset.caseType === 'performance')
            || (type === 'regression' && row.dataset.category === 'regression')
            || (type === 'controlled_fail' && row.dataset.code === 'TC-SAN-003');
        const visible = typeMatches && (!suite || row.dataset.suite === suite);
        row.hidden = !visible;
        row.querySelector('.case-checkbox').disabled = !visible;
    });
}
typeSelect.addEventListener('change', filterCases);
suiteSelect.addEventListener('change', filterCases);
document.getElementById('selectAll').addEventListener('click', function () {
    const boxes = [...document.querySelectorAll('.case-checkbox:not(:disabled)')];
    const shouldSelect = boxes.some((box) => !box.checked);
    boxes.forEach((box) => { box.checked = shouldSelect; });
});
filterCases();
</script>@endpush
@endsection
