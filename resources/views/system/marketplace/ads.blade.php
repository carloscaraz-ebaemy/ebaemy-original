@extends('system.layouts.app')

@php
    use App\Models\System\MarketplaceAdRequest as AdReq;
    $steps = ['solicitada', 'pagada', 'activa', 'terminada'];
    $next  = ['solicitada' => 'pagada', 'pagada' => 'activa', 'activa' => 'terminada'];
    $nextLabel = ['solicitada' => 'Marcar como pagada', 'pagada' => 'Marcar como activa', 'activa' => 'Dar por terminada'];
    $hint = [
        'solicitada' => ['Confirma con la tienda', 'Escríbele por WhatsApp para confirmar el presupuesto y cómo pagar. Cuando pague, márcala como pagada.'],
        'pagada'     => ['Crea la campaña en Meta', 'Usa los datos de la derecha tal cual: el nombre y los parámetros son los que cuentan los pedidos. Al publicarla, márcala como activa.'],
        'activa'     => ['Campaña en marcha', 'Anota lo gastado de vez en cuando. Los pedidos y consultas se suman solos.'],
        'terminada'  => ['Campaña terminada', 'Envíale el resultado a la tienda por WhatsApp.'],
        'rechazada'  => ['Rechazada', 'La tienda ve este estado en su panel y puede pedir otra.'],
    ];
    $open = AdReq::whereIn('status', AdReq::OPEN)->sum('budget');
@endphp

@push('styles')
<style>
.adr{--ink:#0f172a;--mut:#64748b;--line:#e2e8f0;--soft:#f8fafc;--pri:#2563eb;--ok:#16a34a;--warn:#d97706;--bad:#dc2626;color:var(--ink);max-width:1180px}
.adr *{box-sizing:border-box}
.adr-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:18px}
.adr .adr-head h1{font-size:22px;font-weight:700;margin:0 0 4px}
.adr-head p{color:var(--mut);font-size:13.5px;margin:0;max-width:680px}
.adr-link{border:1px solid var(--line);border-radius:10px;padding:8px 14px;font-size:13px;color:var(--ink);background:#fff;text-decoration:none;white-space:nowrap}
.adr-link:hover{border-color:var(--pri);color:var(--pri);text-decoration:none}
.adr-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}
.adr-kpi{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 16px}
.adr-kpi span{display:block;font-size:12px;color:var(--mut);margin-bottom:2px}
.adr-kpi b{font-size:22px;font-weight:700}
.adr-kpi.is-hot{border-color:#fcd34d;background:#fffbeb}
.adr-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px}
.adr-tab{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink);font-size:13px;text-decoration:none}
.adr-tab:hover{text-decoration:none;border-color:#94a3b8;color:var(--ink)}
.adr-tab.on{background:var(--ink);border-color:var(--ink);color:#fff}
.adr-tab i{font-style:normal;font-size:11px;background:rgba(100,116,139,.15);border-radius:999px;padding:1px 7px}
.adr-tab.on i{background:rgba(255,255,255,.2)}
.adr-card{background:#fff;border:1px solid var(--line);border-radius:16px;margin-bottom:16px;overflow:hidden;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.adr-top{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:18px 20px;flex-wrap:wrap}
.adr-who{display:flex;align-items:center;gap:12px;min-width:0}
.adr-av{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#ec4899);color:#fff;font-weight:700;font-size:18px;display:grid;place-items:center;flex:none}
.adr .adr-who h2{font-size:17px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.adr-who small{display:block;color:var(--mut);font-size:12.5px;margin-top:2px}
.adr-st{font-size:11.5px;font-weight:600;border-radius:999px;padding:3px 10px}
.st-solicitada{background:#fef3c7;color:#92400e}.st-pagada{background:#dbeafe;color:#1e40af}.st-activa{background:#dcfce7;color:#166534}.st-terminada{background:#e2e8f0;color:#334155}.st-rechazada{background:#fee2e2;color:#991b1b}
.adr-money{text-align:right}
.adr-money b{font-size:24px;font-weight:800;display:block;line-height:1.1}
.adr-money small{color:var(--mut);font-size:12.5px}
.adr-steps{display:flex;padding:0 20px 16px;gap:0}
.adr-step{flex:1;position:relative;font-size:12px;color:var(--mut);padding-top:20px;text-align:center}
.adr-step::before{content:"";position:absolute;top:6px;left:50%;width:12px;height:12px;margin-left:-6px;border-radius:50%;background:#fff;border:2px solid #cbd5e1;z-index:1}
.adr-step::after{content:"";position:absolute;top:11px;left:-50%;width:100%;height:2px;background:#e2e8f0}
.adr-step:first-child::after{display:none}
.adr-step.done{color:var(--ink)}
.adr-step.done::before{background:var(--ok);border-color:var(--ok)}
.adr-step.done::after{background:var(--ok)}
.adr-step.now{color:var(--ink);font-weight:700}
.adr-step.now::before{border-color:var(--pri);background:var(--pri);box-shadow:0 0 0 4px rgba(37,99,235,.18)}
.adr-step.now::after{background:var(--ok)}
.adr-body{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.15fr);gap:0;border-top:1px solid var(--line)}
.adr-col{padding:18px 20px}
.adr-col+.adr-col{border-left:1px solid var(--line);background:var(--soft)}
.adr-h{font-size:11.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--mut);margin:0 0 10px}
.adr-next{border-radius:12px;padding:12px 14px;background:#eff6ff;border:1px solid #bfdbfe;margin-bottom:14px}
.adr-next b{display:block;font-size:14px;margin-bottom:2px}
.adr-next p{margin:0;font-size:13px;color:#334155}
.adr-next.is-rechazada{background:#fef2f2;border-color:#fecaca}
.adr-next.is-terminada{background:#f1f5f9;border-color:#e2e8f0}
.adr-acts{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.adr-btn{display:inline-flex;align-items:center;gap:6px;border-radius:10px;padding:8px 14px;font-size:13px;font-weight:600;border:1px solid transparent;cursor:pointer;text-decoration:none;line-height:1.2}
.adr-btn:hover{text-decoration:none}
.adr-btn.wa{background:#16a34a;color:#fff}.adr-btn.wa:hover{background:#15803d;color:#fff}
.adr-btn.go{background:var(--pri);color:#fff}.adr-btn.go:hover{background:#1d4ed8;color:#fff}
.adr-btn.ghost{background:#fff;border-color:var(--line);color:var(--ink)}.adr-btn.ghost:hover{border-color:#94a3b8;color:var(--ink)}
.adr-dl{margin:0;font-size:13.5px}
.adr-dl dt{color:var(--mut);font-weight:500;font-size:12px;margin-top:10px}
.adr-dl dt:first-child{margin-top:0}
.adr-dl dd{margin:2px 0 0}
.adr-meta{list-style:none;margin:0;padding:0;counter-reset:m}
.adr-meta li{counter-increment:m;display:grid;grid-template-columns:24px minmax(0,1fr) auto;gap:10px;align-items:center;padding:9px 0;border-bottom:1px dashed var(--line)}
.adr-meta li:last-child{border-bottom:0}
.adr-meta li::before{content:counter(m);width:22px;height:22px;border-radius:50%;background:#fff;border:1px solid var(--line);font-size:11.5px;font-weight:700;display:grid;place-items:center;color:var(--mut)}
.adr-meta span{display:block;font-size:12px;color:var(--mut)}
.adr-meta code{display:block;font-size:12.5px;color:#0f172a;background:transparent;padding:0;word-break:break-all;font-family:ui-monospace,SFMono-Regular,Consolas,monospace}
.adr-copy{border:1px solid var(--line);background:#fff;border-radius:8px;padding:5px 10px;font-size:12px;cursor:pointer;color:var(--ink);white-space:nowrap}
.adr-copy:hover{border-color:var(--pri);color:var(--pri)}
.adr-copy.ok{border-color:var(--ok);color:var(--ok)}
.adr-res{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;padding:16px 20px;border-top:1px solid var(--line)}
.adr-res div{background:var(--soft);border-radius:12px;padding:10px 12px}
.adr-res span{display:block;font-size:11.5px;color:var(--mut)}
.adr-res b{font-size:18px}
.adr-res.off b{color:#cbd5e1}
.adr-form{display:grid;grid-template-columns:170px 140px minmax(0,1fr) auto;gap:10px;align-items:end;padding:14px 20px;border-top:1px solid var(--line);background:#fff}
.adr-form label{display:block;font-size:12px;color:var(--mut);margin-bottom:4px}
.adr-form select,.adr-form input{width:100%;height:38px;border:1px solid var(--line);border-radius:10px;padding:0 10px;font-size:13.5px;background:#fff;color:var(--ink)}
.adr-form select:focus,.adr-form input:focus{outline:none;border-color:var(--pri);box-shadow:0 0 0 3px rgba(37,99,235,.15)}
.adr-form .adr-btn{height:38px}
.adr-empty{background:#fff;border:1px dashed #cbd5e1;border-radius:16px;padding:40px 20px;text-align:center;color:var(--mut)}
.adr-empty b{display:block;color:var(--ink);font-size:16px;margin-bottom:4px}
.adr-alert{border-radius:12px;padding:10px 14px;margin-bottom:14px;font-size:13.5px}
.adr-alert.ok{background:#dcfce7;color:#166534}.adr-alert.bad{background:#fee2e2;color:#991b1b}
@media (max-width: 991px){
  .adr-body{grid-template-columns:1fr}
  .adr-col+.adr-col{border-left:0;border-top:1px solid var(--line)}
  .adr-form{grid-template-columns:1fr 1fr}
  .adr-form .f-note,.adr-form .f-save{grid-column:1/-1}
}
@media (max-width: 600px){
  .adr-kpis,.adr-res{grid-template-columns:repeat(2,minmax(0,1fr))}
  .adr-money{text-align:left}
  .adr-step{font-size:10.5px}
}
</style>
@endpush

@section('content')
<div class="container-fluid py-3">
<div class="adr">
    <div class="adr-head">
        <div>
            <h1>📣 Solicitudes de publicidad</h1>
            <p>Las tiendas las piden desde su panel (Tienda virtual → Publicidad). Cada estado que cambies aquí lo ve la tienda en su panel.</p>
        </div>
        <a href="{{ route('system.marketplace.seo') }}" class="adr-link">Medición y píxeles →</a>
    </div>

    @if(session('ok'))<div class="adr-alert ok">{{ session('ok') }}</div>@endif
    @if(isset($errors) && $errors->any())<div class="adr-alert bad">{{ $errors->first() }}</div>@endif

    <div class="adr-kpis">
        <div class="adr-kpi {{ ($counts['solicitada'] ?? 0) ? 'is-hot' : '' }}"><span>Por atender</span><b>{{ $counts['solicitada'] ?? 0 }}</b></div>
        <div class="adr-kpi"><span>Pagadas, por lanzar</span><b>{{ $counts['pagada'] ?? 0 }}</b></div>
        <div class="adr-kpi"><span>Activas</span><b>{{ $counts['activa'] ?? 0 }}</b></div>
        <div class="adr-kpi"><span>Presupuesto en curso</span><b>S/ {{ number_format($open, 0) }}</b></div>
    </div>

    <nav class="adr-tabs">
        <a href="{{ route('system.marketplace.ads') }}" class="adr-tab {{ $status ? '' : 'on' }}">Todas</a>
        @foreach(AdReq::STATUSES as $key => $label)
            <a href="{{ route('system.marketplace.ads', ['status' => $key]) }}" class="adr-tab {{ $status === $key ? 'on' : '' }}">{{ $label }} <i>{{ $counts[$key] ?? 0 }}</i></a>
        @endforeach
    </nav>

    @forelse($requests as $r)
        @php
            $res   = in_array($r->status, ['activa', 'terminada'], true) ? $r->results() : null;
            $phone = preg_replace('/\D/', '', $r->contact_phone);
            $scope = AdReq::SCOPES[$r->scope] ?? $r->scope;
            $idx   = array_search($r->status, $steps, true);
            $msg   = [
                'solicitada' => "Hola 👋, te escribe ebaemy. Recibimos tu solicitud de publicidad para {$r->store_name}: S/ " . number_format($r->budget, 0) . " por {$r->days} días ({$scope}). Te confirmo cómo hacer el pago para lanzarla.",
                'pagada'     => "Hola 👋, ya recibimos tu pago. Estamos preparando tu campaña en Facebook e Instagram y te avisamos apenas salga.",
                'activa'     => "Hola 👋, tu campaña ya está activa en Facebook e Instagram. En tu panel (Tienda virtual → Publicidad) ves los pedidos y consultas que te trae.",
                'terminada'  => "Hola 👋, tu campaña terminó." . ($res ? " Trajo {$res['orders']} pedido(s) por S/ " . number_format($res['revenue'], 2) . " y {$res['leads']} consulta(s)." : '') . " ¿Quieres repetirla?",
                'rechazada'  => "Hola 👋, te escribe ebaemy sobre tu solicitud de publicidad.",
            ][$r->status] ?? '';
            $spent = (float) $r->spent;
            $roas  = $res && $spent > 0 ? $res['revenue'] / $spent : null;
        @endphp
        <article class="adr-card">
            <header class="adr-top">
                <div class="adr-who">
                    <div class="adr-av">{{ mb_strtoupper(mb_substr(preg_replace('/^(grupo|tienda)\s+/i', '', $r->store_name), 0, 1)) }}</div>
                    <div>
                        <h2>{{ $r->store_name }} <span class="adr-st st-{{ $r->status }}">{{ $r->statusLabel() }}</span></h2>
                        <small>#{{ $r->id }} · {{ $r->created_at->format('d/m/Y H:i') }} · pidió {{ $r->requested_by ?: '—' }}</small>
                    </div>
                </div>
                <div class="adr-money">
                    <b>S/ {{ number_format($r->budget, 2) }}</b>
                    <small>{{ $r->days }} días · {{ AdReq::ZONES[$r->zone] ?? $r->zone }} · S/ {{ number_format($r->budget / max(1, $r->days), 2) }} al día</small>
                </div>
            </header>

            @if($r->status !== 'rechazada')
                <div class="adr-steps">
                    @foreach($steps as $i => $s)
                        <div class="adr-step {{ $i < $idx || $r->status === 'terminada' ? 'done' : ($i === $idx ? 'now' : '') }}">{{ AdReq::STATUSES[$s] }}</div>
                    @endforeach
                </div>
            @endif

            <div class="adr-body">
                <section class="adr-col">
                    <div class="adr-next is-{{ $r->status }}">
                        <b>{{ $hint[$r->status][0] ?? '' }}</b>
                        <p>{{ $hint[$r->status][1] ?? '' }}</p>
                        <div class="adr-acts">
                            @if($phone)
                                <a class="adr-btn wa" href="https://wa.me/51{{ $phone }}?text={{ rawurlencode($msg) }}" target="_blank" rel="noopener">WhatsApp {{ $r->contact_phone }}</a>
                            @endif
                            <a class="adr-btn ghost" href="{{ $r->shortUrl() }}" target="_blank" rel="noopener">Ver tienda</a>
                        </div>
                    </div>

                    <p class="adr-h">Qué pidió</p>
                    <dl class="adr-dl">
                        <dt>Qué anunciar</dt>
                        <dd>{{ $scope }}@if($r->scope_detail) — <span style="color:var(--mut)">{{ $r->scope_detail }}</span>@endif</dd>
                        @if($r->notes)<dt>Nota de la tienda</dt><dd>{{ $r->notes }}</dd>@endif
                        @if($r->starts_at || $r->ends_at)
                            <dt>Fechas</dt>
                            <dd>@if($r->starts_at)Empezó {{ $r->starts_at->format('d/m/Y') }}@endif @if($r->ends_at) · terminó {{ $r->ends_at->format('d/m/Y') }}@endif</dd>
                        @endif
                        @if($r->admin_notes)<dt>Tu nota interna</dt><dd>{{ $r->admin_notes }}</dd>@endif
                    </dl>
                </section>

                <section class="adr-col">
                    <p class="adr-h">Datos para Meta, en este orden</p>
                    <ol class="adr-meta">
                        <li><div><span>Nombre de la campaña</span><code>{{ $r->utm_campaign }}</code></div><button type="button" class="adr-copy" data-copy="{{ $r->utm_campaign }}">Copiar</button></li>
                        <li><div><span>Conjunto de productos · Etiqueta personalizada 2</span><code>tienda_{{ $r->subdomain }}</code></div><button type="button" class="adr-copy" data-copy="tienda_{{ $r->subdomain }}">Copiar</button></li>
                        <li><div><span>URL del sitio web</span><code>{{ $r->shortUrl() }}</code></div><button type="button" class="adr-copy" data-copy="{{ $r->shortUrl() }}">Copiar</button></li>
                        <li><div><span>Parámetros de URL</span><code>{{ $r->urlParams() }}</code></div><button type="button" class="adr-copy" data-copy="{{ $r->urlParams() }}">Copiar</button></li>
                    </ol>
                </section>
            </div>

            <div class="adr-res {{ $res ? '' : 'off' }}" title="{{ $res ? '' : 'Los resultados aparecen cuando la campaña está activa' }}">
                <div><span>Gastado en Meta</span><b>{{ $spent > 0 ? 'S/ ' . number_format($spent, 2) : '—' }}</b></div>
                <div><span>Pedidos</span><b>{{ $res ? $res['orders'] : '—' }}</b></div>
                <div><span>Ventas</span><b>{{ $res ? 'S/ ' . number_format($res['revenue'], 2) : '—' }}</b></div>
                <div><span>Consultas</span><b>{{ $res ? $res['leads'] : '—' }}</b>@if($roas)<span>Retorno: S/ {{ number_format($roas, 2) }} por sol</span>@endif</div>
            </div>

            <form method="POST" action="{{ route('system.marketplace.ads.update', $r->id) }}" class="adr-form">
                @csrf
                <div>
                    <label>Estado</label>
                    <select name="status">
                        @foreach(AdReq::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected($r->status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Gastado (S/)</label>
                    <input type="number" step="0.01" min="0" name="spent" value="{{ $r->spent }}" placeholder="0.00">
                </div>
                <div class="f-note">
                    <label>Nota interna</label>
                    <input type="text" name="admin_notes" maxlength="500" value="{{ $r->admin_notes }}" placeholder="Ej. pagó por Yape el 27/10">
                </div>
                <div class="f-save adr-acts" style="margin:0">
                    <button class="adr-btn ghost">Guardar</button>
                    @isset($next[$r->status])
                        {{-- Va después del select: al enviar con este botón, su estado es el que cuenta. --}}
                        <button class="adr-btn go" name="status" value="{{ $next[$r->status] }}">{{ $nextLabel[$r->status] }} →</button>
                    @endisset
                </div>
            </form>
        </article>
    @empty
        <div class="adr-empty">
            <b>{{ $status ? 'No hay solicitudes en este estado' : 'Todavía ninguna tienda ha pedido publicidad' }}</b>
            Cuando una tienda la pida desde su panel, te llega un aviso a la campanita y aparece aquí.
        </div>
    @endforelse

    {{ $requests->links() }}
</div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('click', function (e) {
    var b = e.target.closest('.adr-copy');
    if (!b) return;
    var done = function () { b.textContent = 'Copiado ✓'; b.classList.add('ok'); setTimeout(function () { b.textContent = 'Copiar'; b.classList.remove('ok'); }, 1600); };
    var text = b.getAttribute('data-copy');
    if (navigator.clipboard) { navigator.clipboard.writeText(text).then(done); return; }
    var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); done();
});
</script>
@endpush
