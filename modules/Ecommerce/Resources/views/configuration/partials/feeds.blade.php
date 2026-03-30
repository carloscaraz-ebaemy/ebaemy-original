@php
    $domain    = request()->getScheme() . '://' . request()->getHost();
    $feedGoogle   = $domain . '/ecommerce/feed/google';
    $feedFacebook = $domain . '/ecommerce/feed/facebook';
    $feedCsv      = $domain . '/ecommerce/feed/csv';
    $feedSitemap  = $domain . '/ecommerce/sitemap.xml';
@endphp

<div class="col-lg-6 col-md-12">
    <div class="card card-default">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-rss" style="color:#f47b30"></i>
                Feeds de productos
            </h3>
        </div>
        <div class="card-body">
            <p class="text-muted" style="font-size:1.3rem;margin-bottom:1.5rem">
                Usa estas URLs para sincronizar tu catálogo con plataformas de publicidad y búsqueda.
                Los feeds se actualizan automáticamente.
            </p>

            <div class="ec-feeds-list">

                @foreach([
                    ['label' => 'Google Merchant Center (XML)', 'url' => $feedGoogle,   'icon' => 'fab fa-google',    'color' => '#4285F4', 'hint' => 'Google Merchant → Agregar productos → Feed de datos'],
                    ['label' => 'Facebook / Instagram (JSON)',  'url' => $feedFacebook, 'icon' => 'fab fa-facebook',  'color' => '#1877F2', 'hint' => 'Meta Business → Catálogos → Fuente de datos → Feed programado'],
                    ['label' => 'TikTok Shop / CSV genérico',  'url' => $feedCsv,      'icon' => 'fab fa-tiktok',    'color' => '#010101', 'hint' => 'TikTok Seller Center → Productos → Importar → URL del archivo CSV'],
                    ['label' => 'Sitemap XML (SEO)',           'url' => $feedSitemap,  'icon' => 'fas fa-sitemap',   'color' => '#22c55e', 'hint' => 'Google Search Console → Mapas del sitio → Agregar URL'],
                ] as $feed)
                <div class="ec-feed-row">
                    <div class="ec-feed-icon" style="background:{{ $feed['color'] }}15;color:{{ $feed['color'] }}">
                        <i class="{{ $feed['icon'] }}"></i>
                    </div>
                    <div class="ec-feed-info">
                        <span class="ec-feed-label">{{ $feed['label'] }}</span>
                        <small class="ec-feed-hint">{{ $feed['hint'] }}</small>
                    </div>
                    <div class="ec-feed-url-wrap">
                        <input type="text"
                               class="form-control ec-feed-input"
                               value="{{ $feed['url'] }}"
                               readonly
                               onclick="this.select()">
                        <button type="button"
                                class="btn btn-sm ec-feed-copy-btn"
                                data-url="{{ $feed['url'] }}"
                                title="Copiar URL"
                                onclick="ecCopyFeed(this)">
                            <i class="fas fa-copy"></i>
                            <span>Copiar</span>
                        </button>
                        <a href="{{ $feed['url'] }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="btn btn-sm ec-feed-open-btn"
                           title="Abrir en nueva pestaña">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                </div>
                @endforeach

            </div><!-- .ec-feeds-list -->
        </div>
    </div>
</div>

<style>
.ec-feeds-list { display: flex; flex-direction: column; gap: 14px; }
.ec-feed-row {
    display: flex;
    align-items: center;
    gap: 14px;
    background: #fafafa;
    border: 1px solid #eee;
    border-radius: 10px;
    padding: 12px 16px;
}
.ec-feed-icon {
    width: 42px; height: 42px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem;
    flex-shrink: 0;
}
.ec-feed-info {
    display: flex; flex-direction: column; gap: 2px;
    min-width: 180px;
}
.ec-feed-label { font-size: 1.3rem; font-weight: 600; color: #333; }
.ec-feed-hint  { font-size: 1.15rem; color: #888; }
.ec-feed-url-wrap {
    display: flex; align-items: center; gap: 6px;
    flex: 1; min-width: 0;
}
.ec-feed-input {
    flex: 1; min-width: 0;
    font-size: 1.2rem;
    background: #fff;
    border-radius: 6px;
    cursor: text;
}
.ec-feed-copy-btn {
    background: #6c757d; color: #fff;
    display: flex; align-items: center; gap: 4px;
    border-radius: 6px; white-space: nowrap;
    font-size: 1.2rem; padding: 5px 10px;
    transition: background .15s;
}
.ec-feed-copy-btn:hover { background: #495057; color: #fff; }
.ec-feed-copy-btn.copied { background: #22c55e; }
.ec-feed-open-btn {
    background: #f1f3f5; color: #555;
    border-radius: 6px;
    padding: 5px 9px;
    font-size: 1.2rem;
}
.ec-feed-open-btn:hover { background: #e2e6ea; color: #333; }
@media (max-width: 768px) {
    .ec-feed-row { flex-wrap: wrap; }
    .ec-feed-info { min-width: 100%; }
    .ec-feed-url-wrap { width: 100%; }
}
</style>

<script>
function ecCopyFeed(btn) {
    var url = btn.getAttribute('data-url');
    navigator.clipboard.writeText(url).then(function () {
        var icon = btn.querySelector('i');
        var text = btn.querySelector('span');
        btn.classList.add('copied');
        icon.className = 'fas fa-check';
        if (text) text.textContent = '¡Copiado!';
        setTimeout(function () {
            btn.classList.remove('copied');
            icon.className = 'fas fa-copy';
            if (text) text.textContent = 'Copiar';
        }, 2000);
    }).catch(function () {
        // Fallback para navegadores sin clipboard API
        var input = btn.closest('.ec-feed-url-wrap').querySelector('.ec-feed-input');
        input.select();
        document.execCommand('copy');
    });
}
</script>
