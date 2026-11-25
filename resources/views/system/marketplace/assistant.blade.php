@extends('system.layouts.app')

@section('content')
<div class="container-fluid py-3 mpa">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h3 class="mb-0">🤖 Asistente de compras (IA)</h3>
            <div class="text-muted small">Qué pregunta la gente, qué no encuentra y cuánto cuesta</div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <form method="GET" class="d-flex gap-1">
                @foreach([7 => '7 días', 30 => '30 días', 90 => '90 días'] as $d => $label)
                    <button name="dias" value="{{ $d }}" class="btn btn-sm {{ $dias === $d ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</button>
                @endforeach
            </form>
            <a href="{{ route('system.marketplace.dashboard') }}" class="btn btn-outline-secondary btn-sm">← Marketplace</a>
        </div>
    </div>

    {{-- Estado --}}
    <div class="alert {{ $estado['encendido'] ? 'alert-success' : 'alert-warning' }} py-2 small d-flex flex-wrap gap-3 align-items-center">
        <span><strong>{{ $estado['encendido'] ? 'Encendido' : 'Apagado' }}</strong> · modelo <code>{{ $estado['modelo'] }}</code></span>
        <span>Hoy: <strong>{{ number_format($estado['tokens_hoy']) }}</strong> tokens
            @if($estado['tope_diario'] > 0)
                de {{ number_format($estado['tope_diario']) }} ({{ round($estado['tokens_hoy'] * 100 / max(1, $estado['tope_diario'])) }} %)
            @else · sin tope @endif
        </span>
        <span class="text-muted">Precios usados: {{ $estado['precios']['in'] }} / {{ $estado['precios']['out'] }} USD por millón (entrada / salida). Cambia el tope con <code>MARKETPLACE_ASSISTANT_DAILY_TOKENS</code>.</span>
    </div>

    {{-- KPIs --}}
    <div class="row g-2 mb-3">
        @php
        $cards = [
            ['Conversaciones', number_format($kpi['conversaciones']), $kpi['conectados'] . ' con cuenta'],
            ['Mensajes', number_format($kpi['mensajes']), $kpi['conversaciones'] ? round($kpi['mensajes'] / $kpi['conversaciones'], 1) . ' por conversación' : ''],
            ['Sin resultado', $kpi['sin_resultados_pct'] . ' %', number_format($kpi['sin_resultados']) . ' respuestas sin producto'],
            ['Con clic', $kpi['con_clic_pct'] . ' %', number_format($kpi['clics']) . ' clics en tarjetas'],
            ['Al carrito', number_format($kpi['carrito']), number_format($kpi['whatsapp']) . ' a WhatsApp de tienda'],
            ['Satisfacción', $kpi['satisfaccion_pct'] === null ? '—' : $kpi['satisfaccion_pct'] . ' %', $kpi['up'] . ' 👍 · ' . $kpi['down'] . ' 👎'],
            ['Costo estimado', '$ ' . number_format($kpi['costo_usd'], 2), number_format(($kpi['tin'] + $kpi['tout']) / 1000) . 'k tokens · ' . number_format($kpi['tcache'] / 1000) . 'k de caché'],
        ];
        @endphp
        @foreach($cards as [$t, $v, $s])
            <div class="col-6 col-md-4 col-xl">
                <div class="card h-100"><div class="card-body py-2 px-3">
                    <div class="text-muted small">{{ $t }}</div>
                    <div class="fs-4 fw-bold">{{ $v }}</div>
                    <div class="text-muted" style="font-size:11.5px">{{ $s }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        {{-- Por día --}}
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header py-2 small fw-bold">Por día</div>
                <div class="table-responsive" style="max-height:360px">
                    <table class="table table-sm table-striped mb-0 small">
                        <thead class="table-light"><tr><th>Día</th><th class="text-end">Respuestas</th><th class="text-end">Sin resultado</th><th class="text-end">Errores</th><th class="text-end">Latencia</th><th class="text-end">Tokens</th></tr></thead>
                        <tbody>
                        @forelse($porDia->reverse() as $d)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($d->dia)->format('d/m') }}</td>
                                <td class="text-end">{{ $d->mensajes }}</td>
                                <td class="text-end {{ $d->sin_resultados ? 'text-danger' : '' }}">{{ $d->sin_resultados }}</td>
                                <td class="text-end {{ $d->errores ? 'text-danger' : '' }}">{{ $d->errores }}</td>
                                <td class="text-end">{{ number_format($d->latencia / 1000, 1) }} s</td>
                                <td class="text-end">{{ number_format($d->tokens) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted text-center py-3">Todavía no hay conversaciones en este periodo.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Herramientas --}}
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header py-2 small fw-bold">Qué herramientas usa</div>
                <ul class="list-group list-group-flush small">
                    @forelse($herramientas as $nombre => $n)
                        <li class="list-group-item d-flex justify-content-between py-1"><code>{{ $nombre }}</code><span>{{ $n }}</span></li>
                    @empty
                        <li class="list-group-item text-muted">Sin datos.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- Preguntas sin resultado --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header py-2 small fw-bold">Preguntas que acabaron sin producto <span class="text-muted fw-normal">— lo que hay que arreglar en el buscador o en el catálogo</span></div>
                <div class="table-responsive" style="max-height:380px">
                    <table class="table table-sm table-striped mb-0 small">
                        <thead class="table-light"><tr><th>Fecha</th><th>Pregunta del comprador</th><th>Búsquedas que probó el bot</th><th></th></tr></thead>
                        <tbody>
                        @forelse($sinResultado as $s)
                            <tr>
                                <td class="text-nowrap">{{ \Carbon\Carbon::parse($s->created_at)->format('d/m H:i') }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($s->pregunta, 120) }}</td>
                                <td class="text-muted">
                                    @foreach((array) json_decode((string) $s->tools, true) as $t)
                                        @if(($t['name'] ?? '') === 'buscar_productos')
                                            <span class="badge {{ ($t['total'] ?? 0) > 0 ? 'bg-success' : 'bg-secondary' }}">{{ $t['input']['consulta'] ?? '' }} · {{ $t['total'] ?? 0 }}</span>
                                        @endif
                                    @endforeach
                                </td>
                                <td><a href="{{ route('system.marketplace.assistant.show', $s->conversation_id) }}">ver</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted text-center py-3">Ninguna en este periodo.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Últimas conversaciones --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header py-2 small fw-bold">Últimas conversaciones</div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0 small align-middle">
                        <thead class="table-light"><tr><th>Última</th><th>Primer mensaje</th><th>Página</th><th class="text-end">Msgs</th><th class="text-end">Sin res.</th><th class="text-end">Clics</th><th class="text-end">Carrito</th><th>Opinión</th><th class="text-end">Costo</th><th></th></tr></thead>
                        <tbody>
                        @forelse($conversaciones as $c)
                            <tr>
                                <td class="text-nowrap">{{ $c->last_message_at ? \Carbon\Carbon::parse($c->last_message_at)->diffForHumans(null, true) : '—' }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($c->first_message, 70) }} @if($c->buyer_id)<span class="badge bg-info">cuenta</span>@endif</td>
                                <td class="text-muted">{{ $c->first_page ? \Illuminate\Support\Str::limit(parse_url($c->first_page, PHP_URL_PATH), 36) : '' }}</td>
                                <td class="text-end">{{ $c->messages_count }}</td>
                                <td class="text-end {{ $c->zero_results_count ? 'text-danger' : '' }}">{{ $c->zero_results_count }}</td>
                                <td class="text-end">{{ $c->clicks_count }}</td>
                                <td class="text-end {{ $c->cart_adds_count ? 'text-success fw-bold' : '' }}">{{ $c->cart_adds_count }}</td>
                                <td>@if($c->feedback_up) 👍{{ $c->feedback_up }} @endif @if($c->feedback_down) 👎{{ $c->feedback_down }} @endif</td>
                                <td class="text-end">$ {{ number_format(\App\Services\Marketplace\ShoppingAssistantLog::costUsd($c->input_tokens, $c->output_tokens, $c->cache_read_tokens), 3) }}</td>
                                <td><a href="{{ route('system.marketplace.assistant.show', $c->id) }}">leer</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-muted text-center py-3">Todavía nadie ha hablado con el asistente.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
