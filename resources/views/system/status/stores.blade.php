@extends('system.layouts.app')

@php
    $r = $s['resumen'];
    $pill = ['ok' => ['ok', 'Al día'], 'warn' => ['warn', 'Con avisos'], 'bad' => ['bad', 'Atender'], 'off' => ['off', 'Bloqueada']];
    $hace = function ($fecha) {
        if (!$fecha) return '—';
        $d = \Carbon\Carbon::parse($fecha);
        $m = $d->diffInMinutes();
        if ($m < 60) return 'hace ' . $m . ' min';
        if ($m < 2880) return 'hace ' . intdiv($m, 60) . ' h';
        return 'hace ' . $d->diffInDays() . ' días';
    };
    $wa = ['open' => ['ok', 'Conectado'], 'connecting' => ['warn', 'Conectando'], 'disconnected' => ['bad', 'Sin conectar'], 'close' => ['bad', 'Desconectado']];
    $addonNames = [\App\Models\System\Addon::PROMO_VIDEOS => 'Videos'];
@endphp

@push('styles')
<style>
.sh{--ink:#0f172a;--mut:#64748b;--line:#e2e8f0;--ok:#16a34a;--warn:#d97706;--bad:#dc2626;color:var(--ink);max-width:1200px}
.sh *{box-sizing:border-box}
.sh-head{display:flex;gap:12px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;margin-bottom:16px}
.sh .sh-head h1{font-size:22px;font-weight:700;margin:0 0 4px}
.sh-head p{color:var(--mut);font-size:13.5px;margin:0;max-width:720px}
.sh-btn{border:1px solid var(--line);background:#fff;color:var(--ink);border-radius:9px;padding:9px 14px;font-size:13px;font-weight:600;text-decoration:none;white-space:nowrap;min-height:44px;display:inline-flex;align-items:center}
.sh-btn:hover{border-color:#94a3b8;color:var(--ink);text-decoration:none}
.sh-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px}
.sh-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:14px 16px;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.sh-big{font-size:26px;font-weight:700;line-height:1.1}
.sh-sub{color:var(--mut);font-size:12.5px;margin-top:4px}
.sh-pill{display:inline-block;font-size:11.5px;font-weight:700;border-radius:999px;padding:3px 10px;white-space:nowrap}
.sh-pill.ok{background:#dcfce7;color:#166534}.sh-pill.warn{background:#fef3c7;color:#92400e}.sh-pill.bad{background:#fee2e2;color:#991b1b}.sh-pill.off{background:#e2e8f0;color:#334155}
.sh-list{display:grid;gap:12px}
.sh-store{background:#fff;border:1px solid var(--line);border-radius:16px;padding:14px 16px;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.sh-store.is-bad{border-color:#fecaca}.sh-store.is-off{opacity:.75}
.sh-store__head{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:8px}
.sh-store__head h2{font-size:16px;font-weight:700;margin:0;flex:1 1 200px;min-width:0}
.sh-store__head h2 small{display:block;font-size:12px;color:var(--mut);font-weight:500;word-break:break-all}
.sh-facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px 14px;font-size:13px}
.sh-fact b{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--mut);font-weight:700;margin-bottom:2px}
.sh-fact span{color:var(--ink)}
.sh-fact .d{color:var(--mut);font-size:12px}
.sh-avisos{margin:10px 0 0;padding:0;list-style:none;display:flex;flex-direction:column;gap:4px}
.sh-avisos li{font-size:13px;padding-left:18px;position:relative}
.sh-avisos li::before{content:"";position:absolute;left:4px;top:7px;width:7px;height:7px;border-radius:50%;background:var(--warn)}
.sh-avisos li.bad::before{background:var(--bad)}
.sh-links{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}
.sh-links a{font-size:12.5px;font-weight:600;color:#0f766e;text-decoration:none;border:1px solid var(--line);border-radius:8px;padding:6px 10px;min-height:32px;display:inline-flex;align-items:center}
.sh-links a:hover{border-color:#94a3b8;text-decoration:none}
@media (max-width:600px){.sh-facts{grid-template-columns:1fr 1fr}}
</style>
@endpush

@section('content')
<div class="container-fluid py-3">
<div class="sh">
    <div class="sh-head">
        <div>
            <h1>🏪 Salud de las tiendas</h1>
            <p>Lo que cada tienda tiene sincronizado con ebaemy.com: productos publicados, política de envío, cómo le pagan, WhatsApp, extras y pedidos del marketplace sin atender. Medido {{ $s['generado']->format('d/m/Y H:i') }}; se recalcula cada 5 minutos.</p>
        </div>
        <a class="sh-btn" href="{{ route('system.status.stores', ['actualizar' => 1]) }}">↻ Actualizar ahora</a>
    </div>

    <div class="sh-grid">
        <div class="sh-card"><div class="sh-big">{{ $r['total'] }}</div><div class="sh-sub">tiendas</div></div>
        <div class="sh-card"><div class="sh-big" style="color:var(--ok)">{{ $r['ok'] }}</div><div class="sh-sub">al día</div></div>
        <div class="sh-card"><div class="sh-big" style="color:var(--warn)">{{ $r['avisos'] }}</div><div class="sh-sub">con avisos</div></div>
        <div class="sh-card"><div class="sh-big">{{ $r['publicando'] }}</div><div class="sh-sub">publican en el marketplace</div></div>
        <div class="sh-card"><div class="sh-big" style="color:{{ $r['sin_pago'] ? 'var(--warn)' : 'var(--ok)' }}">{{ $r['sin_pago'] }}</div><div class="sh-sub">publican sin datos de pago</div></div>
        <div class="sh-card"><div class="sh-big">{{ $r['bloqueadas'] }}</div><div class="sh-sub">bloqueadas</div></div>
    </div>

    <div class="sh-list">
        @foreach($s['tiendas'] as $t)
            @php [$pc, $pt] = $pill[$t['nivel']]; $w = $t['tienda']['whatsapp'] ? ($wa[$t['tienda']['whatsapp']] ?? ['warn', $t['tienda']['whatsapp']]) : null; @endphp
            <article class="sh-store is-{{ $t['nivel'] }}">
                <div class="sh-store__head">
                    <h2>{{ $t['nombre'] }} <small>{{ $t['fqdn'] }}{{ $t['plan'] ? ' · ' . $t['plan'] : '' }}{{ $t['verificada'] ? ' · verificada' : '' }}</small></h2>
                    <span class="sh-pill {{ $pc }}">{{ $pt }}</span>
                </div>
                <div class="sh-facts">
                    <div class="sh-fact"><b>Publicados</b><span>{{ $t['listings']['activos'] }}</span> <span class="d">{{ $t['listings']['activos'] ? $t['listings']['con_stock'] . ' con stock' : 'no publica' }}</span></div>
                    <div class="sh-fact"><b>Última sincronización</b><span>{{ $hace($t['listings']['ultimo_sync']) }}</span></div>
                    <div class="sh-fact"><b>Envío</b><span class="sh-pill {{ $t['tienda']['envio'] ? 'ok' : 'warn' }}">{{ $t['tienda']['envio'] ? 'Confirmado' : 'Sin confirmar' }}</span></div>
                    <div class="sh-fact"><b>Cómo le pagan</b>@if($t['tienda']['pago'])<span>{{ implode(', ', $t['tienda']['pago_medios']) }}</span>@else<span class="sh-pill warn">Sin datos</span>@endif</div>
                    <div class="sh-fact"><b>WhatsApp</b>@if($w)<span class="sh-pill {{ $w[0] }}">{{ $w[1] }}</span> @if($t['tienda']['whatsapp_phone'])<span class="d">{{ $t['tienda']['whatsapp_phone'] }}</span>@endif @else<span class="d">—</span>@endif</div>
                    <div class="sh-fact"><b>Extras</b><span>{{ $t['addons'] ? implode(', ', array_map(fn ($k) => $addonNames[$k] ?? $k, $t['addons'])) : '—' }}</span></div>
                    <div class="sh-fact"><b>Pedidos MP (30 días)</b><span>{{ $t['pedidos']['mes'] }}</span> <span class="d">último {{ $hace($t['pedidos']['ultimo']) }}</span></div>
                    <div class="sh-fact"><b>Sin atender</b><span style="{{ $t['tienda']['sin_atender'] ? 'color:var(--bad);font-weight:700' : '' }}">{{ $t['tienda']['sin_atender'] }}</span>@if($t['tienda']['sin_atender'] && $t['tienda']['sin_atender_dias'] !== null) <span class="d">el más antiguo hace {{ $t['tienda']['sin_atender_dias'] }} d</span>@endif</div>
                </div>
                @if($t['avisos'])
                    <ul class="sh-avisos">
                        @foreach($t['avisos'] as $a)<li class="{{ $a['nivel'] }}">{{ $a['texto'] }}</li>@endforeach
                    </ul>
                @endif
                <div class="sh-links">
                    <a href="https://{{ $t['fqdn'] }}/" target="_blank" rel="noopener">Panel de la tienda</a>
                    @if($t['listings']['activos'])<a href="{{ route('marketplace.tenant', $t['subdominio']) }}" target="_blank" rel="noopener">Ver en ebaemy.com</a>@endif
                    <a href="{{ route('system.marketplace_orders.index') }}?q={{ urlencode($t['fqdn']) }}">Pedidos del marketplace</a>
                </div>
            </article>
        @endforeach
    </div>
</div>
</div>
@endsection
