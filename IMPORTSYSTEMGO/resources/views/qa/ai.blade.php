@extends('layouts.app')
@section('title', 'QA AI Assistant')
@section('page-label', 'QA AI Assistant')
@section('content')
<section class="page-hero"><div><span class="eyebrow">Asistencia desacoplada</span><h1>QA AI Assistant</h1><p>Genera propuestas para revisión humana; nunca cambia estados de pruebas.</p></div></section>
<section id="demo-ia" class="panel-card mt-4">
    <h2 class="h5 mb-1">Asistente de pruebas</h2>
    <p class="small text-body-secondary">Modo local · respuestas preparadas</p>
    @if(session()->has('qa_demo'))
        <div class="d-flex flex-column gap-2 mb-3" role="status">
            @if($answer = session('qa_demo.answer'))
                <div class="bg-primary-subtle rounded-3 px-3 py-2 align-self-end">
                    <strong class="small">Tú</strong>
                    <div>{{ session('qa_demo.prompt') }}</div>
                </div>
                <div class="bg-body-tertiary rounded-3 px-3 py-2 align-self-start">
                    <strong class="small">Asistente QA</strong>
                    <div>{!! nl2br(e($answer)) !!}</div>
                </div>
            @else
                <p class="mb-0">Tengo respuestas para las tres preguntas sugeridas.</p>
            @endif
        </div>
    @endif
    <form method="POST" action="{{ route('qa.ai.store') }}">
        @csrf
        <input type="hidden" name="mode" value="demo">
        <label class="form-label" for="qaDemoPrompt">Tu pregunta</label>
        <div class="d-flex flex-wrap gap-2">
            <input class="form-control flex-grow-1 w-auto" id="qaDemoPrompt" name="prompt" list="qaDemoQuestions" required minlength="10" maxlength="8000" placeholder="Escribe o elige una pregunta">
            <button class="btn btn-primary" type="submit">Enviar pregunta</button>
        </div>
        <datalist id="qaDemoQuestions">
            @foreach($demoQuestions as $question)
                <option value="{{ $question }}"></option>
            @endforeach
        </datalist>
    </form>
</section>
<section class="panel-card mt-4">
<div class="operational-stat mb-3"><span>Proveedor</span><strong>{{ $provider }}</strong><small>{{ $provider === 'none' ? 'Sin configurar' : ($model ?: 'Modelo no definido') }}</small></div>
<form method="POST" action="{{ route('qa.ai.store') }}">@csrf<label class="form-label" for="qaPrompt">Requerimiento o registro de error</label><textarea class="form-control" id="qaPrompt" name="prompt" rows="8" maxlength="8000" required placeholder="Describe el requisito o pega solo el registro técnico permitido...">{{ old('prompt') }}</textarea><div class="d-flex justify-content-end mt-3"><button class="btn btn-primary" type="submit">Analizar con IA</button></div></form>
</section>
@foreach($sessions as $session)<section class="panel-card mt-4"><div class="panel-heading"><div><span class="eyebrow">{{ $session->provider }} · {{ $session->status }}</span><h2>{{ $session->created_at->toDateTimeString() }}</h2></div></div><h3 class="h6">Prompt</h3><pre class="bg-light p-3 border rounded">{{ $session->prompt }}</pre><h3 class="h6">Respuesta</h3>@if($session->response)<div>{!! nl2br(e($session->response)) !!}</div>@else<pre class="text-danger">{{ $session->error_message ?: 'Sin respuesta' }}</pre>@endif</section>@endforeach
@endsection
