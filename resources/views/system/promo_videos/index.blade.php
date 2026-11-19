@extends('system.layouts.app')

@push('styles')
<style>
.pv{--ink:#0f172a;--mut:#64748b;--line:#e2e8f0;--ok:#16a34a;--warn:#d97706;--bad:#dc2626;color:var(--ink);max-width:1150px}
.pv *{box-sizing:border-box}
.pv .pv-head h1{font-size:22px;font-weight:700;margin:0 0 4px}
.pv-head{margin-bottom:14px}
.pv-head p{color:var(--mut);font-size:13.5px;margin:0;max-width:760px}
.pv-alert{border-radius:10px;padding:10px 14px;font-size:13.5px;margin-bottom:12px}
.pv-alert.ok{background:#dcfce7;color:#166534}.pv-alert.bad{background:#fee2e2;color:#991b1b}
.pv-card{background:#fff;border:1px solid var(--line);border-radius:16px;margin-bottom:14px;padding:16px 18px;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.pv .pv-card h2{font-size:15px;font-weight:700;margin:0 0 4px}
.pv-card > p{color:var(--mut);font-size:13px;margin:0 0 12px}
.pv-tabs{display:flex;gap:6px;margin-bottom:12px;flex-wrap:wrap}
.pv-tabs a{border:1px solid var(--line);border-radius:999px;padding:7px 14px;font-size:13px;font-weight:600;color:var(--ink);text-decoration:none;background:#fff}
.pv-tabs a.on{background:var(--ink);border-color:var(--ink);color:#fff}
.pv-tabs b{background:var(--bad);color:#fff;border-radius:999px;padding:0 7px;margin-left:4px;font-size:11.5px}
.pv-row{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap}
.pv-row label{display:flex;flex-direction:column;gap:3px;font-size:12px;color:var(--mut)}
.pv-row input,.pv-row select{border:1px solid var(--line);border-radius:8px;padding:7px 9px;font-size:13px;color:var(--ink);background:#fff;min-width:0}
.pv-btn{border:1px solid var(--line);background:#fff;color:var(--ink);border-radius:8px;padding:7px 12px;font-size:12.5px;font-weight:600;cursor:pointer;white-space:nowrap}
.pv-btn.go{background:var(--ok);border-color:var(--ok);color:#fff}
.pv-btn.no{color:var(--bad);border-color:#fecaca}
.pv-tbl{width:100%;border-collapse:collapse;font-size:13px}
.pv-tbl th{text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.03em;color:var(--mut);padding:6px 8px;border-bottom:1px solid var(--line)}
.pv-tbl td{padding:10px 8px;border-bottom:1px solid #f1f5f9;vertical-align:top}
.pv-pill{display:inline-block;font-size:11.5px;font-weight:700;border-radius:999px;padding:2px 9px;background:#e2e8f0;color:#334155}
.pv-pill.ok{background:#dcfce7;color:#166534}.pv-pill.warn{background:#fef3c7;color:#92400e}.pv-pill.bad{background:#fee2e2;color:#991b1b}
.pv-sub{color:var(--mut);font-size:12px}
.pv-videos{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px}
.pv-video{border:1px solid var(--line);border-radius:14px;overflow:hidden;background:#fff;display:flex;flex-direction:column}
.pv-video video,.pv-video .pv-poster{width:100%;aspect-ratio:9/16;max-height:420px;background:#0f172a;object-fit:contain;display:block}
.pv-video .pv-poster{display:grid;place-items:center;color:#94a3b8;font-size:13px}
.pv-video-body{padding:10px 12px;display:flex;flex-direction:column;gap:8px}
.pv-video-body b{font-size:13.5px}
.pv-empty{color:var(--mut);font-size:13.5px;padding:18px 0}
.pv-stage{position:relative;width:100%;background:#0f172a;overflow:hidden}
.pv-stage.is-vertical{aspect-ratio:9/16;max-height:460px}
.pv-stage.is-vertical.pv-stage--tiktok,.pv-stage.is-vertical.pv-stage--instagram{aspect-ratio:9/17}
.pv-stage.is-horizontal{aspect-ratio:16/9}
.pv-stage iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
.pv-lite{position:absolute;inset:0;width:100%;height:100%;border:0;padding:0;background:#0f172a;cursor:pointer;display:grid;place-items:center}
.pv-lite img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.pv-play{position:relative;display:grid;place-items:center;width:56px;height:56px;border-radius:999px;background:rgba(15,23,42,.75);color:#fff;font-size:20px;padding-left:4px}
.pv-lite:hover .pv-play{background:#16a34a}
.pv-caption{font-style:italic;margin-top:2px;word-break:break-word}
details.pv-edit summary{cursor:pointer;font-size:12.5px;font-weight:600;color:#0f766e}
details.pv-edit[open] summary{margin-bottom:8px}
</style>
@endpush

@section('content')
<div class="container-fluid py-3">
<div class="pv">
    <div class="pv-head">
        <h1>🎬 Videos promocionales</h1>
        <p>Extra de pago por tienda: un video de 5, 15 o 30 segundos por producto, que sale en la ficha, en las páginas de ebaemy que destaques y sirve para anuncios en Google, Meta y TikTok. <b>Ningún video se publica sin tu aprobación</b>, y si la tienda deja de pagar sus videos se ocultan solos (no se borran).</p>
    </div>

    @if(session('pv_ok'))<div class="pv-alert ok">{{ session('pv_ok') }}</div>@endif
    @if(session('pv_error'))<div class="pv-alert bad">{{ session('pv_error') }}</div>@endif
    @if(isset($errors) && $errors->any())<div class="pv-alert bad">{{ $errors->first() }}</div>@endif

    <div class="pv-tabs">
        <a href="{{ route('system.promo_videos', ['tab' => 'bandeja']) }}" class="{{ $tab === 'bandeja' ? 'on' : '' }}">Por aprobar @if($porAprobar)<b>{{ $porAprobar }}</b>@endif</a>
        <a href="{{ route('system.promo_videos', ['tab' => 'publicados']) }}" class="{{ $tab === 'publicados' ? 'on' : '' }}">Publicados</a>
        <a href="{{ route('system.promo_videos', ['tab' => 'tiendas']) }}" class="{{ $tab === 'tiendas' ? 'on' : '' }}">Tiendas y cobro</a>
    </div>

    @if($tab === 'tiendas')
        <div class="pv-card">
            <h2>Precio y cupo por defecto</h2>
            <p>Lo que se propone al activar el extra en una tienda nueva. Cambiarlo no toca a las tiendas que ya lo tienen.</p>
            <form method="POST" action="{{ route('system.promo_videos.defaults') }}" class="pv-row">
                @csrf
                <label>Precio mensual (S/)
                    <input type="number" name="default_monthly_price" step="0.01" min="0" value="{{ $addon->default_monthly_price }}" required>
                </label>
                <label>Videos activos incluidos
                    <input type="number" name="default_quota" min="1" value="{{ $addon->default_quota }}" placeholder="Sin tope">
                </label>
                <button class="pv-btn go">Guardar</button>
            </form>
        </div>

        <div class="pv-card">
            <h2>Tiendas</h2>
            <p>Activa el extra, fija su precio y cupo, y registra cada mes pagado. «Vigente» = activo y pagado hasta hoy.</p>
            <table class="pv-tbl">
                <thead><tr><th>Tienda</th><th>Estado</th><th>Videos</th><th>Pagado hasta</th><th></th></tr></thead>
                <tbody>
                @foreach($tiendas as $t)
                    @php $c = $t->contrato; @endphp
                    <tr>
                        <td><b>{{ $t->nombre }}</b><div class="pv-sub">{{ $t->fqdn }}</div></td>
                        <td>
                            @if(!$c)
                                <span class="pv-pill">Sin extra</span>
                            @elseif($c->isVigente())
                                <span class="pv-pill ok">Vigente</span>
                            @elseif($c->status === 'active')
                                <span class="pv-pill warn">Vencido</span>
                            @else
                                <span class="pv-pill bad">{{ $c->status === 'suspended' ? 'Suspendido' : 'De baja' }}</span>
                            @endif
                            @if($c)<div class="pv-sub">S/ {{ number_format($c->monthly_price, 2) }} al mes</div>@endif
                        </td>
                        <td>{{ $t->usados }}{{ $c && $c->quota ? ' de '.$c->quota : '' }}</td>
                        <td>{{ $c && $c->paid_until ? $c->paid_until->format('d/m/Y') : '—' }}</td>
                        <td style="min-width:290px">
                            @if($c)
                                <form method="POST" action="{{ route('system.promo_videos.payment', $t->id) }}" style="display:inline">
                                    @csrf
                                    <button class="pv-btn go" title="Suma un mes desde el último día pagado, o desde hoy si ya venció">+ 1 mes pagado</button>
                                </form>
                            @endif
                            <details class="pv-edit" style="margin-top:6px">
                                <summary>{{ $c ? 'Editar' : 'Activar extra' }}</summary>
                                <form method="POST" action="{{ route('system.promo_videos.client', $t->id) }}" class="pv-row">
                                    @csrf
                                    <label>Estado
                                        <select name="status">
                                            @foreach(['active' => 'Activo', 'suspended' => 'Suspendido', 'cancelled' => 'De baja'] as $k => $l)
                                                <option value="{{ $k }}" @selected(($c->status ?? 'active') === $k)>{{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>S/ al mes
                                        <input type="number" name="monthly_price" step="0.01" min="0" style="width:90px" value="{{ $c->monthly_price ?? $addon->default_monthly_price }}" required>
                                    </label>
                                    <label>Cupo
                                        <input type="number" name="quota" min="1" style="width:70px" value="{{ $c->quota ?? $addon->default_quota }}">
                                    </label>
                                    <label>Pagado hasta
                                        <input type="date" name="paid_until" value="{{ $c && $c->paid_until ? $c->paid_until->toDateString() : '' }}">
                                    </label>
                                    <input type="hidden" name="notes" value="{{ $c->notes ?? '' }}">
                                    <button class="pv-btn go">Guardar</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="pv-card">
            <h2>{{ $tab === 'bandeja' ? 'Por aprobar' : 'Publicados y ocultos' }}</h2>
            <p>{{ $tab === 'bandeja'
                ? 'Revisa que el video muestre el producto que dice y que no tenga contenido ajeno a la tienda. Al aprobarlo sale en la ficha y queda listo para anuncios.'
                : 'Destaca un video para promocionarlo en la portada del marketplace y en la página de su tienda, con fecha de fin opcional.' }}</p>

            @if($videos->isEmpty())
                <div class="pv-empty">{{ $tab === 'bandeja' ? 'No hay videos esperando aprobación.' : 'Aún no hay videos publicados.' }}</div>
            @else
                <div class="pv-videos">
                    @foreach($videos as $v)
                        <div class="pv-video">
                            @if($v->isLink() && $v->embedSrc())
                                {{-- Por enlace: miniatura → iframe al tocar (src armado
                                     desde el id validado, nunca la URL pegada). --}}
                                <div class="pv-stage {{ $v->isVertical() ? 'is-vertical' : 'is-horizontal' }} pv-stage--{{ $v->provider }}">
                                    <button type="button" class="pv-lite" data-pv-embed="{{ $v->embedSrc() }}" aria-label="Reproducir el video de {{ $v->item_title }}">
                                        @if($v->posterUrl())<img src="{{ $v->posterUrl() }}" alt="" loading="lazy">@endif
                                        <span class="pv-play" aria-hidden="true">▶</span>
                                    </button>
                                </div>
                            @elseif($v->mp4_url)
                                <video src="{{ $v->mp4_url }}" poster="{{ $v->thumbnail_url }}" controls preload="none" playsinline></video>
                            @else
                                <div class="pv-poster">@if($v->thumbnail_url)<img src="{{ $v->thumbnail_url }}" alt="" style="max-width:100%;max-height:100%">@else Sin vista previa @endif</div>
                            @endif
                            <div class="pv-video-body">
                                <div>
                                    <b>{{ $v->item_title ?: 'Producto #'.$v->remote_item_id }}</b>
                                    <div class="pv-sub">{{ $v->client->name ?? '—' }}
                                        @if($v->isLink()) · enlace de {{ $v->providerLabel() ?? $v->provider }}@endif
                                        @if($v->duration_seconds) · {{ $v->duration_seconds }} s @endif
                                        · {{ ['square' => 'cuadrado', 'horizontal' => 'horizontal'][$v->aspect] ?? 'vertical' }}
                                        @if(!empty($v->template['speed']) && $v->template['speed'] > 1) · acelerado ×{{ rtrim(rtrim(number_format($v->template['speed'], 2), '0'), '.') }}@endif
                                    </div>
                                    @if($v->source_title)
                                        <div class="pv-sub pv-caption" title="{{ $v->source_title }}">«{{ \Illuminate\Support\Str::limit($v->source_title, 120) }}»</div>
                                    @endif
                                    @if($v->status === 'rejected' && $v->rejection_reason)
                                        <div class="pv-sub" style="color:var(--bad)">Motivo: {{ $v->rejection_reason }}</div>
                                    @endif
                                    <div style="margin-top:4px">
                                        <span class="pv-pill {{ $v->status === 'approved' ? 'ok' : ($v->status === 'pending_review' ? 'warn' : '') }}">{{ \App\Models\System\PromoVideo::ESTADOS[$v->status] ?? $v->status }}</span>
                                        @if($v->is_featured)<span class="pv-pill ok">★ Destacado{{ $v->featured_until ? ' hasta '.$v->featured_until->format('d/m') : '' }}</span>@endif
                                    </div>
                                    @if($v->isLink() && $v->source_url)
                                        <div class="pv-sub" style="margin-top:4px"><a href="{{ $v->source_url }}" target="_blank" rel="noopener noreferrer nofollow">Abrir en {{ $v->providerLabel() }} ↗</a></div>
                                    @elseif($v->playback_url)
                                        <div class="pv-sub" style="margin-top:4px"><a href="{{ $v->playback_url }}" target="_blank" rel="noopener">Abrir reproductor ↗</a></div>
                                    @endif
                                </div>

                                @if($v->status === 'pending_review')
                                    <form method="POST" action="{{ route('system.promo_videos.review', $v->id) }}">
                                        @csrf <input type="hidden" name="action" value="approve">
                                        <button class="pv-btn go" style="width:100%">✓ Aprobar y publicar</button>
                                    </form>
                                    <form method="POST" action="{{ route('system.promo_videos.review', $v->id) }}" class="pv-row">
                                        @csrf <input type="hidden" name="action" value="reject">
                                        <input type="text" name="reason" placeholder="Motivo (lo verá la tienda)" required style="flex:1">
                                        <button class="pv-btn no">Rechazar</button>
                                    </form>
                                @elseif($v->status === 'approved')
                                    @if($v->is_featured)
                                        <form method="POST" action="{{ route('system.promo_videos.review', $v->id) }}">
                                            @csrf <input type="hidden" name="action" value="unfeature">
                                            <button class="pv-btn">Quitar de destacados</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('system.promo_videos.review', $v->id) }}" class="pv-row">
                                            @csrf <input type="hidden" name="action" value="feature">
                                            <label>Destacar hasta (opcional)<input type="date" name="featured_until"></label>
                                            <button class="pv-btn go">★ Destacar</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('system.promo_videos.review', $v->id) }}">
                                        @csrf <input type="hidden" name="action" value="hide">
                                        <button class="pv-btn no">Ocultar</button>
                                    </form>
                                @elseif($v->status === 'hidden')
                                    <form method="POST" action="{{ route('system.promo_videos.review', $v->id) }}">
                                        @csrf <input type="hidden" name="action" value="approve">
                                        <button class="pv-btn go">Volver a publicar</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
</div>
<script>
// Videos por enlace: el iframe se crea al tocar la miniatura. Solo se aceptan
// los tres reproductores permitidos (el src ya viene armado desde el id).
(function () {
    var OK = [
        /^https:\/\/www\.youtube-nocookie\.com\/embed\/[A-Za-z0-9_-]{11}$/,
        /^https:\/\/www\.tiktok\.com\/embed\/v2\/\d{8,25}$/,
        /^https:\/\/www\.instagram\.com\/reel\/[A-Za-z0-9_-]{5,40}\/embed$/
    ];
    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-pv-embed]');
        if (!btn) return;
        var src = btn.getAttribute('data-pv-embed') || '';
        if (!OK.some(function (re) { return re.test(src); })) return;
        if (src.indexOf('youtube-nocookie.com') !== -1) src += '?autoplay=1&playsinline=1&rel=0';
        var f = document.createElement('iframe');
        f.src = src;
        f.title = 'Video del producto';
        f.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture; fullscreen');
        f.setAttribute('allowfullscreen', '');
        f.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        btn.replaceWith(f);
    });
})();
</script>
@endsection
