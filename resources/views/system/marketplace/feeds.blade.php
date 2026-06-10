@extends('system.layouts.app')

@section('content')
<div class="container-fluid py-3 mpf">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h3 class="mb-0" style="font-size:19px;font-weight:700">Conectar catálogo</h3>
            <div class="text-muted" style="font-size:12.5px">Facebook · TikTok · Google — un solo feed con todos los productos</div>
        </div>
        <a href="{{ route('system.marketplace.dashboard') }}" class="btn btn-outline-secondary btn-sm">← Volver al dashboard</a>
    </div>

    <div class="mpf-intro">
        Un mismo feed publica <strong>los {{ number_format($productCount) }} productos</strong> del marketplace en Facebook, TikTok y Google.
        Se <strong>actualiza solo</strong> (precios, stock y altas/bajas): pegás la URL una vez en cada plataforma y listo.
    </div>

    {{-- URL principal del feed --}}
    <div class="mpf-card mpf-feed">
        <div class="mpf-feed__label">URL del feed (Facebook y TikTok)</div>
        <div class="mpf-urlrow">
            <input type="text" readonly value="{{ $metaUrl }}" class="mpf-url" id="feedMeta">
            <button type="button" class="btn btn-primary btn-sm mpf-copy" data-target="feedMeta">Copiar</button>
            <a href="{{ $metaUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">Abrir</a>
        </div>
        <div class="mpf-feed__label mt-3">URL del feed (Google Merchant)</div>
        <div class="mpf-urlrow">
            <input type="text" readonly value="{{ $googleUrl }}" class="mpf-url" id="feedGoogle">
            <button type="button" class="btn btn-primary btn-sm mpf-copy" data-target="feedGoogle">Copiar</button>
            <a href="{{ $googleUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">Abrir</a>
        </div>
        <div class="mpf-note">Es el formato estándar de Google Shopping; las tres plataformas lo aceptan. El contenido de ambas URLs es el mismo.</div>
    </div>

    <div class="row g-3 mt-1">
        {{-- Facebook --}}
        <div class="col-lg-4">
            <div class="mpf-card h-100">
                <div class="mpf-plat"><span class="mpf-dot" style="background:#1877f2"></span> Facebook / Instagram</div>
                <ol class="mpf-steps">
                    <li>Entrá a <strong>Meta Commerce Manager</strong> → <em>Catálogos</em>.</li>
                    <li>Creá un catálogo de tipo <strong>Comercio electrónico</strong> (o abrí el existente).</li>
                    <li><em>Orígenes de datos</em> → <strong>Agregar productos</strong> → <strong>Feed de datos</strong>.</li>
                    <li>Elegí <strong>URL programada</strong> y pegá la <strong>URL del feed (Facebook y TikTok)</strong> de arriba.</li>
                    <li>Frecuencia: <strong>diaria</strong>. Guardá y subí.</li>
                </ol>
                <div class="mpf-tip">Para anuncios y Tienda de Instagram, conectá ese catálogo a tu cuenta publicitaria.</div>
            </div>
        </div>

        {{-- TikTok --}}
        <div class="col-lg-4">
            <div class="mpf-card h-100">
                <div class="mpf-plat"><span class="mpf-dot" style="background:#000"></span> TikTok</div>
                <ol class="mpf-steps">
                    <li>Entrá a <strong>TikTok Catalog Manager</strong> (en TikTok Ads Manager → <em>Activos</em> → <em>Catálogos</em>).</li>
                    <li><strong>Crear catálogo</strong> → región y moneda (Perú / PEN).</li>
                    <li><em>Productos</em> → <strong>Agregar por feed</strong> / <strong>Data feed</strong>.</li>
                    <li>Tipo de feed: <strong>Google Shopping</strong>. Pegá la misma <strong>URL del feed</strong> de arriba.</li>
                    <li>Programá actualización <strong>diaria</strong>. Confirmá.</li>
                </ol>
                <div class="mpf-tip">TikTok usa el mismo formato que Facebook, por eso sirve la misma URL.</div>
            </div>
        </div>

        {{-- Google --}}
        <div class="col-lg-4">
            <div class="mpf-card h-100">
                <div class="mpf-plat"><span class="mpf-dot" style="background:#ea4335"></span> Google</div>
                <ol class="mpf-steps">
                    <li>Entrá a <strong>Google Merchant Center</strong>.</li>
                    <li><em>Productos</em> → <strong>Fuentes de datos</strong> → <strong>Agregar</strong>.</li>
                    <li>Elegí <strong>Feed programado por URL</strong>.</li>
                    <li>Pegá la <strong>URL del feed (Google Merchant)</strong> de arriba.</li>
                    <li>Frecuencia <strong>diaria</strong>. Guardá.</li>
                </ol>
                <div class="mpf-tip">Verificá y reclamá el dominio <code>ebaemy.com</code> en Merchant Center.</div>
            </div>
        </div>
    </div>

    <div class="mpf-foot">
        ¿El catálogo rechaza productos? Suele ser por foto faltante, precio en 0 o sin stock. Revisalo en
        <a href="{{ route('system.marketplace.listings') }}">Productos publicados</a>.
    </div>
</div>
@endsection

@push('styles')
<style>
.mpf { --mp-ink:#1e2330; --mp-muted:#6b7280; --mp-line:#e7e9ef; --mp-accent:#4f46e5; --mp-accent-soft:#eef2ff; color:var(--mp-ink); }
.mpf-intro { background:var(--mp-accent-soft); border:1px solid #dfe3ff; color:#3730a3; border-radius:10px; padding:12px 16px; font-size:13.5px; margin-bottom:14px; }
.mpf-card { background:#fff; border:1px solid var(--mp-line); border-radius:12px; padding:16px 18px; }
.mpf-feed { margin-bottom:4px; }
.mpf-feed__label { font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:var(--mp-muted); margin-bottom:6px; }
.mpf-urlrow { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.mpf-url { flex:1 1 320px; min-width:0; height:38px; border:1px solid var(--mp-line); border-radius:8px; padding:0 12px; font-size:13.5px; font-family:ui-monospace,Menlo,Consolas,monospace; color:var(--mp-ink); background:#f8fafc; }
.mpf-copy.copied { background:#16a34a; border-color:#16a34a; }
.mpf-note { font-size:12px; color:var(--mp-muted); margin-top:10px; }
.mpf-plat { display:flex; align-items:center; gap:8px; font-size:14.5px; font-weight:700; margin-bottom:10px; }
.mpf-dot { width:12px; height:12px; border-radius:50%; display:inline-block; }
.mpf-steps { margin:0; padding-left:18px; font-size:13px; line-height:1.6; color:#374151; }
.mpf-steps li { margin-bottom:4px; }
.mpf-steps strong { color:var(--mp-ink); }
.mpf-tip { margin-top:10px; font-size:12px; color:var(--mp-muted); border-top:1px solid #f1f3f7; padding-top:8px; }
.mpf-foot { margin-top:16px; font-size:12.5px; color:var(--mp-muted); }
</style>
@endpush

@push('scripts')
<script>
(function(){
    document.querySelectorAll('.mpf-copy').forEach(function(btn){
        btn.addEventListener('click', function(){
            var input = document.getElementById(btn.dataset.target);
            if(!input) return;
            input.select(); input.setSelectionRange(0, 99999);
            var done = function(){ var t=btn.textContent; btn.textContent='¡Copiado!'; btn.classList.add('copied'); setTimeout(function(){ btn.textContent=t; btn.classList.remove('copied'); }, 1600); };
            if(navigator.clipboard){ navigator.clipboard.writeText(input.value).then(done, function(){ document.execCommand('copy'); done(); }); }
            else { document.execCommand('copy'); done(); }
        });
    });
})();
</script>
@endpush
