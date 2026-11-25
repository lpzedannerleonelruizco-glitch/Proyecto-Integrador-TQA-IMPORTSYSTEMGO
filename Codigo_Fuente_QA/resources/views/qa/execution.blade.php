@extends('layouts.app')
@section('title', 'Detalle de ejecución')
@section('page-label', 'Ejecutar pruebas')
@section('content')
<section class="page-hero"><div><span class="eyebrow">Ejecución {{ $execution->public_id }}</span><h1 id="qaStatus">{{ $execution->status }}</h1><p>{{ $execution->type }} · {{ $execution->recipient_email }} · Correo: <strong id="qaEmailStatus">{{ $execution->email_status }}</strong></p></div></section>
@if($execution->failure_message)<div class="alert alert-danger mt-3" role="alert">La ejecución no pudo completarse: {{ $execution->failure_message }}</div>@endif
@if($emailError = $execution->emailLogs->sortByDesc('id')->first()?->error_message)<div class="alert alert-warning mt-3" role="status">No se confirmó el envío SMTP: {{ $emailError }}</div>@endif
<section class="panel-card mt-4">
    <div class="d-flex justify-content-between mb-2"><strong>Progreso</strong><span id="qaProgress">{{ $execution->progress }}%</span></div>
    <div class="progress" role="progressbar"><div class="progress-bar" id="qaProgressBar" style="width: {{ $execution->progress }}%"></div></div>
</section>
@foreach($execution->results as $result)<section class="panel-card mt-4"><div class="d-flex justify-content-between align-items-start gap-3"><div><span class="eyebrow">{{ $result->case->code }} · {{ $result->case->executor }}</span><h2 class="h5 mt-2">{{ $result->case->name }}</h2></div><span class="badge-soft">{{ $result->status }}</span></div>
<p><strong>Objetivo:</strong> {{ $result->objective }}</p>
<p><strong>Precondiciones:</strong> {{ $result->preconditions ?: 'No especificadas' }}</p>
<p><strong>Datos de prueba:</strong> <code>{{ json_encode($result->case->test_data, JSON_UNESCAPED_UNICODE) }}</code></p>
<p><strong>Resultado esperado:</strong> <code>{{ $result->expected_result }}</code></p>
<p><strong>Resultado obtenido:</strong> {{ $result->obtained_result ?: 'Aún no ejecutado' }}</p>
@if($result->failure_step)<p><strong>Paso fallido:</strong> {{ $result->failure_step }}</p>@endif
@if($result->error_message)<p class="text-danger"><strong>Error:</strong> {{ $result->error_message }}</p>@endif
@if($result->observations)<p><strong>Observaciones:</strong> {{ $result->observations }}</p>@endif
@if($result->validations)<h3 class="h6">Validaciones declaradas</h3><ul>@foreach($result->validations as $validation)<li><code>{{ json_encode($validation, JSON_UNESCAPED_UNICODE) }}</code></li>@endforeach</ul>@endif
@if($result->steps->isNotEmpty())<h3 class="h6">Pasos ejecutados</h3><ol>@foreach($result->steps as $step)<li><strong>{{ $step->name }}</strong> · {{ $step->status }}@if($step->obtained_result) — {{ $step->obtained_result }}@endif @if($step->error_message)<span class="text-danger">({{ $step->error_message }})</span>@endif</li>@endforeach</ol>@endif
@if($result->evidences->isNotEmpty())<h3 class="h6">Evidencias</h3><div class="d-flex flex-wrap gap-3">@foreach($result->evidences as $evidence)<a href="{{ route('qa.evidences.show', [$execution, $evidence]) }}" target="_blank" rel="noopener"><img src="{{ route('qa.evidences.show', [$execution, $evidence]) }}" alt="{{ $evidence->description }}" loading="lazy" style="max-width: 420px; max-height: 300px; object-fit: contain"><span class="d-block">{{ $evidence->description }} · SHA-256 {{ $evidence->sha256 }}</span></a>@endforeach</div>@endif
</section>@endforeach
@if($execution->reports->isNotEmpty())<section class="panel-card mt-4"><div class="d-flex gap-2">@foreach($execution->reports as $report)<a class="btn btn-primary" href="{{ route('qa.reports.download', [$execution, strtolower($report->format)]) }}">{{ $report->format }}</a>@endforeach</div></section>@endif
@push('scripts')<script>
const statusUrl=@json(route('qa.executions.progress',$execution));
async function refreshQa(){try{const response=await fetch(statusUrl,{headers:{'Accept':'application/json'}});if(!response.ok)throw new Error('No se pudo consultar el progreso.');const data=await response.json();document.getElementById('qaProgress').textContent=data.progress+'%';document.getElementById('qaProgressBar').style.width=data.progress+'%';document.getElementById('qaEmailStatus').textContent=data.email_status;if(['PENDING','RUNNING'].includes(data.status)){document.getElementById('qaStatus').textContent=data.status;setTimeout(refreshQa,2000);}else if(data.status!==@json($execution->status)){window.location.reload();}}catch(error){document.getElementById('qaStatus').textContent='No se pudo actualizar; recarga para verificar el resultado.';}}
setTimeout(refreshQa,1000);
</script>@endpush
@endsection
