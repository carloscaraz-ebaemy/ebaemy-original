@extends('system.layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">🔗 SEO y Open Graph del marketplace</h3>
        <a href="{{ route('system.marketplace.dashboard') }}" class="btn btn-outline-secondary btn-sm">← Volver al dashboard</a>
    </div>

    <div class="alert alert-info py-2 mb-3 small">
        <strong>💡</strong> Estos datos aparecen cuando alguien comparte
        <code>ebaemy.com/marketplace</code> por WhatsApp, Facebook, Twitter, etc.
        Una imagen llamativa + título y descripción claras aumentan el CTR hasta 3×.
    </div>

    @if(session('mp_seo_message'))
        <div class="alert alert-success py-2 small">{{ session('mp_seo_message') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger py-2 small">
            <ul class="mb-0">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('system.marketplace.seo.update') }}" enctype="multipart/form-data" class="row g-3">
        @csrf

        {{-- ═══════════════════ Formulario ═══════════════════ --}}
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Datos a mostrar</h5>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Título <small class="text-muted fw-normal">(máx 60 chars recomendado)</small></label>
                        <input type="text" name="marketplace_og_title"
                               class="form-control" maxlength="120"
                               value="{{ old('marketplace_og_title', $config->marketplace_og_title ?? '') }}"
                               placeholder="Marketplace ebaemy — Compra de tiendas verificadas">
                        <small class="text-muted">Si lo dejas vacío, se usa un default profesional.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Descripción <small class="text-muted fw-normal">(máx 160 chars recomendado)</small></label>
                        <textarea name="marketplace_og_description"
                                  class="form-control" rows="3" maxlength="250"
                                  placeholder="Descubre productos de tiendas peruanas verificadas en un solo lugar.">{{ old('marketplace_og_description', $config->marketplace_og_description ?? '') }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Imagen Open Graph <small class="text-muted fw-normal">(1200×630 ideal, máx 2 MB)</small></label>
                        <input type="file" name="og_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <small class="text-muted">Formatos: JPG, PNG, WebP. Mínimo 600×300. Recomendado 1200×630.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Meta keywords <small class="text-muted fw-normal">(separados por coma)</small></label>
                        <input type="text" name="marketplace_meta_keywords"
                               class="form-control" maxlength="500"
                               value="{{ old('marketplace_meta_keywords', $config->marketplace_meta_keywords ?? '') }}"
                               placeholder="marketplace peru, ebaemy, tiendas online, compra segura">
                    </div>

                    <hr class="my-4">
                    <h6 class="text-muted mb-3">📱 Redes sociales del footer</h6>
                    <small class="text-muted d-block mb-3">
                        Los iconos solo aparecen en el footer si tienen URL configurada.
                        Para WhatsApp usa el formato <code>https://wa.me/51XXXXXXXXX</code>.
                    </small>

                    <div class="mb-3">
                        <label class="form-label">Facebook URL</label>
                        <input type="url" name="marketplace_facebook_url"
                               class="form-control"
                               value="{{ old('marketplace_facebook_url', $config->marketplace_facebook_url ?? '') }}"
                               placeholder="https://www.facebook.com/ebaemy">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Instagram URL</label>
                        <input type="url" name="marketplace_instagram_url"
                               class="form-control"
                               value="{{ old('marketplace_instagram_url', $config->marketplace_instagram_url ?? '') }}"
                               placeholder="https://www.instagram.com/ebaemy">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">WhatsApp URL <small class="text-muted">(wa.me/51XXXXXXXXX)</small></label>
                        <input type="url" name="marketplace_whatsapp_url"
                               class="form-control"
                               value="{{ old('marketplace_whatsapp_url', $config->marketplace_whatsapp_url ?? '') }}"
                               placeholder="https://wa.me/51999999999">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">TikTok URL</label>
                        <input type="url" name="marketplace_tiktok_url"
                               class="form-control"
                               value="{{ old('marketplace_tiktok_url', $config->marketplace_tiktok_url ?? '') }}"
                               placeholder="https://www.tiktok.com/@ebaemy">
                    </div>

                    <button type="submit" class="btn btn-primary">💾 Guardar</button>
                </div>
            </div>

            {{-- ═════════ Medición de publicidad ═════════ --}}
            <div class="card mt-3">
                <div class="card-body">
                    <h6 class="mb-1" style="font-weight:700">📡 Medición de publicidad</h6>
                    <p class="small text-muted">
                        Sin esto, TikTok y Meta no saben qué anuncio generó la venta: optimizan hacia
                        clics en vez de hacia compradores. Los campos vacíos no emiten nada.
                    </p>

                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="marketplace_ads_enabled" value="0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="adsEnabled" name="marketplace_ads_enabled" value="1"
                               {{ old('marketplace_ads_enabled', $config->marketplace_ads_enabled ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="adsEnabled">
                            <strong>Medición activa</strong>
                            <small class="text-muted d-block">
                                Interruptor maestro. Apagado no se emite nada, aunque los IDs estén puestos.
                            </small>
                        </label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ID del píxel de Meta <small class="text-muted">(Facebook / Instagram)</small></label>
                        <input type="text" name="marketplace_meta_pixel_id" class="form-control"
                               value="{{ old('marketplace_meta_pixel_id', $config->marketplace_meta_pixel_id ?? '') }}"
                               placeholder="1234567890123456">
                        <small class="text-muted">Commerce Manager → Orígenes de datos → tu píxel.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Token de la Conversions API de Meta</label>
                        <input type="password" name="marketplace_meta_capi_token" class="form-control"
                               autocomplete="new-password"
                               value="{{ old('marketplace_meta_capi_token', $config->marketplace_meta_capi_token ?? '') }}"
                               placeholder="EAAG...">
                        <small class="text-muted">
                            Es lo que permite medir la compra cuando el navegador no la reporta (iOS, bloqueadores).
                            Sin token, se pierde parte de las ventas en el informe.
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ID del píxel de TikTok</label>
                        <input type="text" name="marketplace_tiktok_pixel_id" class="form-control"
                               value="{{ old('marketplace_tiktok_pixel_id', $config->marketplace_tiktok_pixel_id ?? '') }}"
                               placeholder="CXXXXXXXXXXXXXXXXXXX">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Access Token de la Events API de TikTok</label>
                        <input type="password" name="marketplace_tiktok_capi_token" class="form-control"
                               autocomplete="new-password"
                               value="{{ old('marketplace_tiktok_capi_token', $config->marketplace_tiktok_capi_token ?? '') }}"
                               placeholder="—">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ID de Google Analytics 4 <small class="text-muted">(opcional)</small></label>
                        <input type="text" name="marketplace_ga4_id" class="form-control"
                               value="{{ old('marketplace_ga4_id', $config->marketplace_ga4_id ?? '') }}"
                               placeholder="G-XXXXXXXXXX">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Código de prueba de Meta <small class="text-muted">(test_event_code)</small></label>
                        <input type="text" name="marketplace_ads_test_code" class="form-control"
                               value="{{ old('marketplace_ads_test_code', $config->marketplace_ads_test_code ?? '') }}"
                               placeholder="TEST12345">
                        <small class="text-danger">
                            Mientras esté puesto, los eventos aparecen en «Eventos de prueba» pero
                            <strong>NO cuentan como conversiones</strong>. Vaciar al terminar de probar.
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary">💾 Guardar</button>

                    <div class="alert alert-light border mt-3 mb-0 small">
                        <strong>Para comprobar que funciona:</strong> instalá la extensión
                        Meta Pixel Helper o TikTok Pixel Helper y abrí <em>tres</em> páginas — la ficha
                        de un producto, el carrito y la confirmación de un pedido. En el servidor,
                        <code>php artisan ads:check</code> valida la configuración y manda un evento
                        de prueba verificando que la plataforma lo recibe.
                    </div>
                </div>
            </div>

            <div class="alert alert-warning mt-3">
                <h6 class="mb-2">⚠️ Importante: caché de WhatsApp/Facebook</h6>
                <p class="small mb-2">
                    WhatsApp y Facebook <strong>cachean la preview hasta 24h</strong>. Después de guardar acá,
                    el link compartido puede seguir mostrando la versión vieja un buen rato.
                </p>
                <p class="small mb-2"><strong>Para forzar refresh AHORA</strong>:</p>
                <ol class="small mb-2">
                    <li>Click en <strong>"Forzar refresh en Facebook"</strong> abajo</li>
                    <li>En la página de FB, click <strong>"Scrape Again"</strong> (botón gris)</li>
                    <li>Verifica que abajo aparezca la imagen/título/descripción nuevos</li>
                    <li>WhatsApp usa la misma caché que FB — al refrescar uno, refrescan los dos</li>
                </ol>
                <div class="d-flex gap-2 mt-2">
                    <a href="https://developers.facebook.com/tools/debug/?q={{ urlencode(url('/marketplace')) }}"
                       target="_blank" rel="noopener" class="btn btn-primary btn-sm">
                        🔄 Forzar refresh en Facebook (y WhatsApp)
                    </a>
                    <a href="https://www.linkedin.com/post-inspector/inspect/{{ urlencode(url('/marketplace')) }}"
                       target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                        🔄 Forzar refresh en LinkedIn
                    </a>
                </div>
                <p class="small text-muted mt-2 mb-0">
                    💡 <strong>Tip extra para WhatsApp PC</strong>: Eliminá el chat donde compartiste el link viejo,
                    o compartilo en un grupo nuevo. WhatsApp Desktop a veces guarda la preview localmente.
                </p>
            </div>
        </div>

        {{-- ═══════════════════ Preview en vivo ═══════════════════ --}}
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">📱 Preview WhatsApp</h5>
                    <div class="mp-seo-preview">
                        @if($config && $config->marketplace_og_image)
                            <img src="{{ $config->marketplace_og_image_url }}" alt="Preview">
                        @else
                            <div class="mp-seo-preview__noimg">
                                <span>Sin imagen<br><small>Sube una para que se vea profesional</small></span>
                            </div>
                        @endif
                        <div class="mp-seo-preview__body">
                            <div class="mp-seo-preview__title">
                                {{ $config->marketplace_og_title ?? 'Marketplace ebaemy — Compra de tiendas verificadas' }}
                            </div>
                            <div class="mp-seo-preview__desc">
                                {{ $config->marketplace_og_description ?? 'Descubre productos de tiendas peruanas verificadas en un solo lugar. Envío a todo Perú, contacto directo con el vendedor.' }}
                            </div>
                            <div class="mp-seo-preview__url">ebaemy.com/marketplace</div>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">
                        Esta es una previsualización aproximada. WhatsApp/Facebook pueden
                        recortar la imagen distinto según el cliente.
                    </small>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
.mp-seo-preview {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
    background: #fff;
    max-width: 380px;
}
.mp-seo-preview img {
    width: 100%; height: 180px; object-fit: cover; display: block;
}
.mp-seo-preview__noimg {
    width: 100%; height: 180px;
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    display: flex; align-items: center; justify-content: center;
    color: #9ca3af; text-align: center; font-size: 13px;
}
.mp-seo-preview__body { padding: 10px 12px; }
.mp-seo-preview__title { font-weight: 700; color: #111827; font-size: 14px; line-height: 1.3; }
.mp-seo-preview__desc  { font-size: 12.5px; color: #4b5563; margin-top: 4px; line-height: 1.4; max-height: 3.2em; overflow: hidden; }
.mp-seo-preview__url   { font-size: 11px; color: #9ca3af; margin-top: 6px; text-transform: uppercase; letter-spacing: .3px; }
</style>
@endpush
