@extends('system.layouts.app')

@section('content')
<div class="container-fluid py-3" style="max-width:960px">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h3 class="mb-0">Conversación #{{ $conv->id }}</h3>
            <div class="text-muted small">
                {{ \Carbon\Carbon::parse($conv->created_at)->format('d/m/Y H:i') }}
                · {{ $conv->messages_count }} respuestas
                · {{ number_format($conv->input_tokens + $conv->output_tokens) }} tokens
                · $ {{ number_format($costo, 4) }}
                @if($conv->buyer_id) · comprador #{{ $conv->buyer_id }} @endif
                @if($conv->first_page) · <a href="{{ $conv->first_page }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit(parse_url($conv->first_page, PHP_URL_PATH), 50) }}</a> @endif
            </div>
        </div>
        <a href="{{ route('system.marketplace.assistant') }}" class="btn btn-outline-secondary btn-sm">← Asistente IA</a>
    </div>

    <div class="d-flex flex-column gap-2">
        @foreach($mensajes as $m)
            <div class="card {{ $m->role === 'user' ? 'ms-auto border-primary' : 'me-auto' }}" style="max-width:85%">
                <div class="card-body py-2 px-3 small">
                    <div class="text-muted" style="font-size:11px">
                        {{ $m->role === 'user' ? 'Comprador' : 'Bot' }} · {{ \Carbon\Carbon::parse($m->created_at)->format('H:i:s') }}
                        @if($m->role === 'assistant')
                            · {{ number_format(($m->latency_ms ?? 0) / 1000, 1) }} s · {{ $m->input_tokens + $m->output_tokens }} tokens
                            @if($m->cache_read_tokens) · {{ $m->cache_read_tokens }} caché @endif
                            @if($m->feedback === 1) · 👍 @elseif($m->feedback === -1) · 👎 @endif
                            @if($m->error) · <span class="text-danger">error: {{ $m->error }}</span> @endif
                            @if($m->zero_results) · <span class="text-danger">sin resultado</span> @endif
                        @endif
                        @if($m->context_slug) · viendo <code>{{ $m->context_slug }}</code> @endif
                    </div>
                    <div style="white-space:pre-line">{{ $m->text }}</div>
                    @if($m->tools)
                        <div class="mt-1">
                            @foreach($m->tools as $t)
                                <span class="badge {{ ($t['total'] ?? 0) < 0 ? 'bg-danger' : (($t['total'] ?? 1) === 0 ? 'bg-secondary' : 'bg-light text-dark border') }}" title="{{ json_encode($t['input'] ?? [], JSON_UNESCAPED_UNICODE) }}">
                                    {{ $t['name'] }}@if(isset($t['input']['consulta'])): {{ $t['input']['consulta'] }}@endif @if(isset($t['total']) && $t['total'] >= 0) · {{ $t['total'] }}@endif
                                </span>
                            @endforeach
                        </div>
                    @endif
                    @if($m->products)
                        <div class="text-muted mt-1" style="font-size:11px">Tarjetas: {{ implode(', ', $m->products) }}</div>
                    @endif
                    @if($m->suggestions)
                        <div class="mt-1">@foreach($m->suggestions as $s)<span class="badge rounded-pill bg-primary-subtle text-primary border me-1">{{ $s }}</span>@endforeach</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if($eventos->isNotEmpty())
        <div class="card mt-3">
            <div class="card-header py-2 small fw-bold">Lo que hizo después</div>
            <ul class="list-group list-group-flush small">
                @foreach($eventos as $e)
                    <li class="list-group-item py-1">
                        {{ \Carbon\Carbon::parse($e->created_at)->format('H:i:s') }} ·
                        <strong>{{ ['click' => 'Clic en tarjeta', 'cart' => 'Agregó al carrito', 'handoff' => 'Fue a WhatsApp de la tienda', 'open' => 'Abrió el chat', 'search_rescue' => 'Vino desde «sin resultados»'][$e->type] ?? $e->type }}</strong>
                        @if($e->slug) · <code>{{ $e->slug }}</code> @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
