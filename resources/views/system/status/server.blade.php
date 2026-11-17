@extends('system.layouts.app')

@php
    // Verde / ámbar / rojo según el porcentaje de uso.
    $nivel = fn ($pct, $warn = 70, $bad = 85) => $pct === null ? '' : ($pct >= $bad ? 'bad' : ($pct >= $warn ? 'warn' : 'ok'));
    $d = $s['disco']; $m = $s['memoria']; $c = $s['cpu']; $q = $s['colas']; $p = $s['php']; $v = $s['video'];
    $dias = $c['uptime_s'] ? intdiv($c['uptime_s'], 86400) : null;
@endphp

@push('styles')
<style>
.sv{--ink:#0f172a;--mut:#64748b;--line:#e2e8f0;--ok:#16a34a;--warn:#d97706;--bad:#dc2626;color:var(--ink);max-width:1100px}
.sv *{box-sizing:border-box}
.sv-head{display:flex;gap:12px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;margin-bottom:16px}
.sv .sv-head h1{font-size:22px;font-weight:700;margin:0 0 4px}
.sv-head p{color:var(--mut);font-size:13.5px;margin:0;max-width:680px}
.sv-btn{border:1px solid var(--line);background:#fff;color:var(--ink);border-radius:9px;padding:9px 14px;font-size:13px;font-weight:600;text-decoration:none;white-space:nowrap}
.sv-btn:hover{border-color:#94a3b8;color:var(--ink);text-decoration:none}
.sv-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-bottom:14px}
.sv-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:16px 18px;box-shadow:0 1px 2px rgba(15,23,42,.04);margin-bottom:14px}
.sv-grid .sv-card{margin-bottom:0}
.sv .sv-card h2{font-size:15px;font-weight:700;margin:0 0 10px}
.sv-big{font-size:26px;font-weight:700;line-height:1.1}
.sv-sub{color:var(--mut);font-size:12.5px;margin-top:4px}
.sv-bar{height:8px;background:#f1f5f9;border-radius:99px;overflow:hidden;margin-top:10px}
.sv-bar span{display:block;height:100%;border-radius:99px;background:var(--ok)}
.sv-bar span.warn{background:var(--warn)}.sv-bar span.bad{background:var(--bad)}
.sv-tbl{width:100%;border-collapse:collapse;font-size:13px}
.sv-tbl td{padding:7px 0;border-top:1px solid #f1f5f9}
.sv-tbl tr:first-child td{border-top:0}
.sv-tbl td:last-child{text-align:right;font-variant-numeric:tabular-nums;font-weight:600}
.sv-pill{display:inline-block;font-size:11.5px;font-weight:700;border-radius:999px;padding:3px 10px}
.sv-pill.ok{background:#dcfce7;color:#166534}.sv-pill.warn{background:#fef3c7;color:#92400e}.sv-pill.bad{background:#fee2e2;color:#991b1b}
.sv-check{display:flex;gap:10px;padding:10px 0;border-top:1px solid #f1f5f9}
.sv-check:first-of-type{border-top:0}
.sv-check i{font-style:normal;flex:none;width:22px;height:22px;border-radius:50%;display:grid;place-items:center;font-size:12px;font-weight:700;color:#fff;background:var(--ok)}
.sv-check i.no{background:var(--bad)}
.sv-check b{display:block;font-size:13.5px}
.sv-check span{color:var(--mut);font-size:12.5px}
.sv-note{background:#f8fafc;border:1px solid var(--line);border-radius:12px;padding:12px 14px;font-size:13px;color:#334155;margin-top:12px}
.sv-note b{color:var(--ink)}
</style>
@endpush

@section('content')
<div class="container-fluid py-3">
<div class="sv">
    <div class="sv-head">
        <div>
            <h1>🖥️ Estado del servidor</h1>
            <p>Cuánto espacio queda, cómo va la memoria y el procesador, cuánto ocupan las bases de datos y si las colas trabajan. Medido {{ $s['generado']->format('d/m/Y H:i') }}; el tamaño de carpetas y bases se recalcula cada 10 minutos.</p>
        </div>
        <a class="sv-btn" href="{{ route('system.status.server', ['actualizar' => 1]) }}">↻ Medir ahora</a>
    </div>

    <div class="sv-grid">
        <div class="sv-card">
            <h2>💾 Disco</h2>
            <div class="sv-big">{{ $h->human($d['libre']) }} libres</div>
            <div class="sv-sub">{{ $h->human($d['usado']) }} usados de {{ $h->human($d['total']) }} · {{ $d['pct'] }} %</div>
            <div class="sv-bar"><span class="{{ $nivel($d['pct']) }}" style="width:{{ $d['pct'] ?? 0 }}%"></span></div>
        </div>
        <div class="sv-card">
            <h2>🧠 Memoria RAM</h2>
            @if($m)
                <div class="sv-big">{{ $h->human($m['disponible']) }} disponibles</div>
                <div class="sv-sub">de {{ $h->human($m['total']) }} · swap {{ $h->human($m['swap_usado']) }} de {{ $h->human($m['swap_total']) }}</div>
                <div class="sv-bar"><span class="{{ $nivel($m['pct'], 75, 90) }}" style="width:{{ $m['pct'] ?? 0 }}%"></span></div>
            @else
                <div class="sv-sub">No disponible en este sistema.</div>
            @endif
        </div>
        <div class="sv-card">
            <h2>⚙️ Procesador</h2>
            <div class="sv-big">{{ $c['uso_pct'] ?? '—' }} %</div>
            <div class="sv-sub">{{ $c['nucleos'] ?? '—' }} núcleos · carga {{ $c['carga_1'] }} / {{ $c['carga_5'] }} / {{ $c['carga_15'] }} (1/5/15 min){{ $dias !== null ? ' · encendido hace '.$dias.' días' : '' }}</div>
            <div class="sv-bar"><span class="{{ $nivel($c['uso_pct'], 70, 100) }}" style="width:{{ min(100, $c['uso_pct'] ?? 0) }}%"></span></div>
        </div>
        <div class="sv-card">
            <h2>📨 Colas</h2>
            <div class="sv-big">{{ $q['workers'] ?? '—' }} worker{{ ($q['workers'] ?? 0) == 1 ? '' : 's' }}</div>
            <div class="sv-sub">{{ $q['pendientes'] ?? '—' }} tareas en espera · {{ $q['fallidos_24h'] ?? '—' }} fallidas en 24 h ({{ $q['fallidos'] ?? '—' }} en total)</div>
            @if(($q['workers'] ?? 1) === 0)
                <div class="sv-sub" style="color:var(--bad)">Sin workers: no salen correos, WhatsApp ni se procesan las fotos.</div>
            @endif
        </div>
    </div>

    <div class="sv-card">
        <h2>🎬 ¿Listo para subir videos?
            <span class="sv-pill {{ $v['listo'] ? 'ok' : 'bad' }}">{{ $v['listo'] ? 'Sí' : 'Todavía no' }}</span>
        </h2>
        @foreach($v['checks'] as $ch)
            <div class="sv-check">
                <i class="{{ $ch['ok'] ? '' : 'no' }}">{{ $ch['ok'] ? '✓' : '✕' }}</i>
                <div><b>{{ $ch['titulo'] }}</b><span>{{ $ch['detalle'] }}</span></div>
            </div>
        @endforeach
        <div class="sv-note">
            Con el espacio libre de hoy, dejando un 20 % de colchón, caben unos <b>{{ number_format($v['caben']) }} videos</b> de {{ $v['ref_mb'] }} MB.
            Para videos, lo recomendable es que <b>no pasen por este servidor</b>: que el navegador los suba directo a un servicio de video o a un bucket en la nube, que los comprime y los sirve; aquí solo se guarda el enlace.
        </div>
    </div>

    <div class="sv-grid">
        <div class="sv-card">
            <h2>📁 Qué ocupa el disco</h2>
            <table class="sv-tbl">
                @foreach($s['carpetas'] as $dir)
                    <tr><td>{{ $dir['nombre'] }}</td><td>{{ $dir['bytes'] === null ? '—' : $h->human($dir['bytes']) }}</td></tr>
                @endforeach
            </table>
        </div>
        <div class="sv-card">
            <h2>🗄️ Bases de datos</h2>
            <div class="sv-sub" style="margin:0 0 6px">{{ $s['bases']['cantidad'] ?? '—' }} bases · {{ $s['bases']['total'] !== null ? $h->human($s['bases']['total']) : '—' }} en total</div>
            <table class="sv-tbl">
                @foreach($s['bases']['top'] as $b)
                    <tr><td>{{ $b['nombre'] }}</td><td>{{ $h->human($b['bytes']) }}</td></tr>
                @endforeach
            </table>
        </div>
        <div class="sv-card">
            <h2>🧩 PHP y servicios</h2>
            <table class="sv-tbl">
                <tr><td>PHP</td><td>{{ $p['version'] }}</td></tr>
                <tr><td>Subida máxima por archivo</td><td>{{ $h->human($p['subida_max_bytes']) }}</td></tr>
                <tr><td>Memoria por petición</td><td>{{ $p['memory_limit'] }}</td></tr>
                <tr><td>Tiempo máximo por petición</td><td>{{ $p['max_execution_time'] }} s</td></tr>
                <tr><td>Caché</td><td>{{ $s['servicios']['cache'] }}</td></tr>
                <tr><td>Sesiones</td><td>{{ $s['servicios']['sesiones'] }}</td></tr>
                <tr><td>Colas</td><td>{{ $q['conexion'] }}</td></tr>
                <tr><td>Medios en la nube</td><td>{{ $s['servicios']['nube_medios'] ? 'Sí' : 'No (disco local)' }}</td></tr>
                <tr><td>ffmpeg</td><td>{{ $s['servicios']['ffmpeg'] ? 'Instalado' : 'No instalado' }}</td></tr>
            </table>
        </div>
    </div>
</div>
</div>
@endsection
