@extends('system.layouts.app')

@section('content')
<div class="container py-3" style="max-width:780px">
    <a href="{{ route('system.marketing.campaigns.index') }}" class="text-decoration-none small text-muted">← Campañas</a>
    <h3 class="mb-3 mt-1">Nueva campaña</h3>

    <form method="POST" action="{{ route('system.marketing.campaigns.store') }}" class="card p-4">
        @csrf
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="mb-3">
            <label class="form-label">Nombre interno</label>
            <input type="text" class="form-control" name="name" required maxlength="180" value="{{ old('name') }}">
        </div>

        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <label class="form-label">Canal</label>
                <select class="form-select" name="channel" required>
                    @foreach(['email' => 'Email', 'whatsapp' => 'WhatsApp', 'sms' => 'SMS (no impl.)'] as $v => $l)
                        <option value="{{ $v }}" {{ old('channel') === $v ? 'selected' : '' }}>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label">Asunto (solo email)</label>
                <input type="text" class="form-control" name="subject" maxlength="200" value="{{ old('subject') }}">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Mensaje</label>
            <textarea class="form-control" name="message" rows="6" required maxlength="4000" placeholder="Hola {nombre}, tenemos novedades…">{{ old('message') }}</textarea>
            <small class="text-muted">
                Variables disponibles: <code>{nombre}</code>, <code>{opt_out_url}</code>.
                Si no incluyes el link de opt-out, el sistema lo añadirá automáticamente al final del mensaje.
            </small>
        </div>

        <div class="card-header bg-light mt-3 mb-3 px-0 py-2"><strong>Segmentación</strong> <small class="text-muted">(opcional, deja en blanco para enviar a todos los contactos con consent)</small></div>

        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <label class="form-label">Etiquetas (separadas por coma)</label>
                <input type="text" class="form-control" name="segment_tags" value="{{ old('segment_tags') }}" placeholder="restaurante, abarrotes">
            </div>
            <div class="col-md-3">
                <label class="form-label">Source</label>
                <input type="text" class="form-control" name="segment_source" value="{{ old('segment_source') }}" placeholder="checkout, signup_seller, import">
            </div>
            <div class="col-md-3">
                <label class="form-label">Hostname ID</label>
                <input type="number" class="form-control" name="segment_hostname_id" value="{{ old('segment_hostname_id') }}">
            </div>
        </div>

        <div class="text-end">
            <button class="btn btn-primary">Crear campaña</button>
        </div>
    </form>
</div>
@endsection
