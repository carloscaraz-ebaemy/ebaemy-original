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
