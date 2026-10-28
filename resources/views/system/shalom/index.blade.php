@extends('system.layouts.app')

@push('styles')
<style>
.sh{--ink:#0f172a;--mut:#64748b;--line:#e2e8f0;--ok:#16a34a;--bad:#dc2626;color:var(--ink);max-width:900px}
.sh *{box-sizing:border-box}
.sh .sh-head h1{font-size:22px;font-weight:700;margin:0 0 4px}
.sh-head{margin-bottom:18px}
.sh-head p{color:var(--mut);font-size:13.5px;margin:0;max-width:720px}
.sh-alert{border-radius:10px;padding:10px 14px;font-size:13.5px;margin-bottom:14px}
.sh-alert.ok{background:#dcfce7;color:#166534}.sh-alert.bad{background:#fee2e2;color:#991b1b}
.sh-card{background:#fff;border:1px solid var(--line);border-radius:16px;margin-bottom:14px;padding:18px 20px;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.sh .sh-card h2{font-size:16px;font-weight:700;margin:0 0 4px}
.sh-card > p{color:var(--mut);font-size:13px;margin:0 0 12px}
.sh-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.sh-row input{flex:1;min-width:220px;border:1px solid var(--line);border-radius:9px;padding:9px 11px;font-size:13px;font-family:ui-monospace,monospace}
.sh-btn{border:1px solid var(--line);background:#fff;color:var(--ink);border-radius:9px;padding:9px 14px;font-size:13px;font-weight:600;cursor:pointer}
.sh-btn.go{background:var(--ok);border-color:var(--ok);color:#fff}
.sh-kv{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:12px}
.sh-kv div{border:1px solid var(--line);border-radius:12px;padding:10px 12px;font-size:12.5px;color:var(--mut)}
.sh-kv b{display:block;font-size:20px;color:var(--ink);line-height:1.2}
.sh-key{font-family:ui-monospace,monospace;background:#f1f5f9;border-radius:6px;padding:2px 7px}
.sh-pill{font-size:11.5px;font-weight:600;border-radius:999px;padding:3px 10px;background:#e2e8f0;color:#334155}
.sh-pill.warn{background:#fef3c7;color:#92400e}
</style>
@endpush

@section('content')
<div class="container-fluid py-3">
<div class="sh">
    <div class="sh-head">
        <h1>📦 Shalom</h1>
        <p>Una sola llave de <b>shalom-api.lat</b> para todas las tiendas. Con ella copiamos el catálogo de oficinas de Shalom cada 3 horas; el cliente elige su oficina en el formulario de envío leyendo <b>nuestra copia</b>, así que si la llave se vence el formulario sigue funcionando con la última lista guardada.</p>
    </div>

    @if(session('shalom_ok'))<div class="sh-alert ok">{{ session('shalom_ok') }}</div>@endif
    @if(session('shalom_error'))<div class="sh-alert bad">{{ session('shalom_error') }}</div>@endif
    @if(isset($errors) && $errors->any())<div class="sh-alert bad">{{ $errors->first() }}</div>@endif

    <div class="sh-card">
        <h2>Llave del API</h2>
        <p>
            @if($keyPreview)
                En uso: <span class="sh-key">{{ $keyPreview }}</span>
                @if($keySource === 'env')
                    <span class="sh-pill warn">desde el .env del servidor</span> — guarda una aquí para poder cambiarla sin tocar el servidor.
                @else
                    <span class="sh-pill">guardada en el panel</span>
                @endif
            @else
                <span class="sh-pill warn">Sin llave</span> — el formulario usa la última lista guardada, pero no se actualizará.
            @endif
        </p>
        <form method="POST" action="{{ route('system.shalom.key') }}" class="sh-row" autocomplete="off">
            @csrf @method('PUT')
            <input type="password" name="shalom_api_key" placeholder="sk_… (nueva llave)" required minlength="10" maxlength="200">
            <button class="sh-btn go" type="submit">Verificar y guardar</button>
        </form>
        <p style="margin:8px 0 0;font-size:12px;">La llave se prueba contra Shalom antes de guardarse: si no sirve, se queda la anterior.</p>
        <form method="POST" action="{{ route('system.shalom.check') }}" style="margin-top:10px">
            @csrf
            <button class="sh-btn" type="submit" @disabled(!$keyPreview)>Probar la llave en uso</button>
        </form>
    </div>

    <div class="sh-card">
        <h2>Plan contratado</h2>
        <p>El API no informa el plan: indícalo aquí. Decide qué se enciende en las tiendas.</p>
        <form method="POST" action="{{ route('system.shalom.plan') }}" class="sh-row">
            @csrf @method('PUT')
            <select name="shalom_plan" style="flex:1;min-width:200px;border:1px solid var(--line);border-radius:9px;padding:9px 11px;font-size:13px;">
                @foreach(\App\Services\System\ShalomPlan::PLANS as $k => $p)
                    <option value="{{ $k }}" @selected($plan === $k)>{{ $p['label'] }} · {{ $p['instances'] === null ? 'instancias ilimitadas' : ($p['instances'] . ' ' . ($p['instances'] === 1 ? 'instancia' : 'instancias')) }}</option>
                @endforeach
            </select>
            <button class="sh-btn go" type="submit">Guardar plan</button>
        </form>
        @php $pi = \App\Services\System\ShalomPlan::info($plan); @endphp
        <ul style="margin:12px 0 0;padding-left:18px;font-size:13px;color:var(--mut);line-height:1.7">
            <li>Oficinas, cotización de flete y tiempos, seguimiento de guías: <b style="color:var(--ok)">activo</b></li>
            <li>Crear guías e imprimir el rótulo oficial de Shalom desde Pedidos (instancias):
                <b style="color:{{ $pi['instances'] === 0 ? 'var(--bad)' : 'var(--ok)' }}">{{ $pi['instances'] === 0 ? 'no incluido' : 'activo' }}</b></li>
            <li>Avisos automáticos de cambio de estado (webhooks):
                <b style="color:{{ $pi['webhooks'] ? 'var(--ok)' : 'var(--bad)' }}">{{ $pi['webhooks'] ? 'incluido (pendiente de conectar)' : 'no incluido' }}</b></li>
        </ul>
        <p style="margin:10px 0 0;font-size:12.5px">Cada tienda guarda su usuario y contraseña de Shalom Pro en su política de envío. Con un plan con instancias, la tienda toca «Guardar y conectar».</p>
    </div>

    @if($pi['webhooks'])
    <div class="sh-card">
        <h2>Avisos de Shalom (webhook)</h2>
        <p>
            Shalom avisa a ebaemy cada cambio de estado de las guías ({{ number_format($guides) }} registradas) y el cliente
            recibe un WhatsApp cuando su paquete llega a la oficina.
            Estado: <b style="color:{{ $webhookOn ? 'var(--ok)' : 'var(--bad)' }}">{{ $webhookOn ? 'activos' : 'sin activar' }}</b>
        </p>
        <form method="POST" action="{{ route('system.shalom.webhook') }}">
            @csrf
            <button class="sh-btn {{ $webhookOn ? '' : 'go' }}" type="submit">{{ $webhookOn ? 'Volver a registrar (nuevo secreto)' : 'Activar avisos' }}</button>
        </form>
    </div>
    @endif

    <div class="sh-card">
        <h2>Catálogo de oficinas guardado</h2>
        <div class="sh-kv">
            <div><b>{{ number_format($stats['selectable']) }}</b>elegibles por el cliente</div>
            <div><b>{{ number_format($stats['total']) }}</b>guardadas en total</div>
            <div><b>{{ number_format($stats['delisted']) }}</b>ya no aparecen en Shalom (se conservan)</div>
        </div>
        <p>
            Última sincronización:
            <b>{{ optional($config)->shalom_synced_at ? $config->shalom_synced_at->format('d/m/Y H:i') . ' (' . $config->shalom_synced_at->diffForHumans() . ')' : 'nunca' }}</b>
            @if(optional($config)->shalom_last_error)
                <br><span style="color:#991b1b">Último error ({{ optional($config->shalom_last_error_at)->format('d/m/Y H:i') }}): {{ $config->shalom_last_error }}</span>
            @endif
        </p>
        <form method="POST" action="{{ route('system.shalom.sync') }}">
            @csrf
            <button class="sh-btn" type="submit" @disabled(!$keyPreview)>Sincronizar ahora</button>
        </form>
    </div>
</div>
</div>
@endsection
