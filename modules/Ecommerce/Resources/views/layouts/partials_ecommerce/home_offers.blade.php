{{-- Sección del home. La incluye el renderizador de secciones:
     ver App\Services\EcommerceHomeSections y ecommerce::index. --}}
            {{-- ── OFERTAS / SPOTS ──────────────────────────────────── --}}
            @if(!$hasCategoryFilter)
            @php
                $spotsHasImages = isset($spots) && $spots->whereNotNull('image_url')->where('image_url', '!=', '')->count() > 0;
            @endphp
            @if($spotsHasImages)
            <section class="ec-home-section ec-home-section--offers" aria-label="Ofertas y promociones">
                <div class="ec-section-header">
                    <h2 class="ec-section-title">Ofertas especiales</h2>
                </div>
                @include('ecommerce::layouts.partials_ecommerce.offers')
            </section>
            @endif
            @endif
