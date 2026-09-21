{{-- THEME TECNOLOGÍA — Header con mega-menú tech --}}
@php
    $company = \App\Models\Tenant\Company::first();
    $econfig = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
    $logo = ($company && $company->logo) ? asset('storage/uploads/logos/'.$company->logo) : asset('porto-ecommerce/assets/images/logo-black.png');

    // El logo se invierte a blanco solo si el header es oscuro. Con la paleta
    // configurable un tenant puede poner header claro, y el invert fijo dejaba
    // el logo blanco sobre blanco: invisible.
    $__headerHex  = \App\Services\EcommerceThemeTokens::palette($econfig)['header'];
    $__headerDark = \App\Services\EcommerceThemeTokens::readableOn($__headerHex) === '#ffffff';
@endphp

<style>
/* ── Header del tema tecnología ──────────────────────────────────────
   Tres zonas: marca · buscador · acciones. El buscador es la zona que
   crece, porque es el punto de entrada real al catálogo. */
.tech-header {
    background: var(--theme-header,#0f172a);
    color: var(--theme-header-text,#fff);
    position: sticky; top: 0; z-index: 100;
    box-shadow: 0 1px 0 var(--theme-header-line, rgba(148,163,184,.18));
}
.tech-header__top {
    display: flex; align-items: center; gap: 14px;
    padding: 7px 20px; max-width: 1340px; margin: 0 auto;
}
@media (min-width: 1700px) { .tech-header__top { max-width: 1600px; } }
.tech-header__logo { flex: 0 0 auto; display: flex; align-items: center; }
.tech-header__logo img { height: 30px; width: auto; display: block; {{ $__headerDark ? 'filter:brightness(0) invert(1);' : '' }} }

/* Buscador: elemento principal de la barra. */
.tech-header__search { flex: 1 1 auto; max-width: 680px; margin: 0 auto; position: relative; }
.tech-header__search form { display: block; }
.tech-header__search input {
    width: 100%; height: 36px;
    padding: 0 40px 0 38px;
    border: 1px solid var(--theme-header-line, rgba(148,163,184,.28));
    border-radius: 10px;
    background: var(--theme-header-soft-2,#1e293b);
    color: var(--theme-header-text,#e2e8f0);
    font-size: 13.5px; line-height: 36px; outline: none;
    transition: border-color .15s, background .15s, box-shadow .15s;
}
.tech-header__search input::placeholder { color: var(--theme-header-text,#64748b); opacity: .6; }
.tech-header__search input:hover { border-color: var(--theme-primary, hsl(var(--primary-h),var(--primary-s),65%)); }
.tech-header__search input:focus {
    background: var(--theme-header-line,#334155);
    border-color: var(--theme-primary, hsl(var(--primary-h),var(--primary-s),var(--primary-l)));
    box-shadow: 0 0 0 3px hsla(var(--primary-h),var(--primary-s),var(--primary-l),.18);
}
.tech-header__search-icon {
    position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
    color: var(--theme-header-text,#64748b); opacity: .6; pointer-events: none;
}
.tech-header__search-go {
    position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
    width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;
    border: 0; border-radius: 8px; cursor: pointer;
    background: transparent; color: var(--theme-header-text,#cbd5e1); opacity: .7;
}
.tech-header__search-go:hover {
    opacity: 1; color: var(--theme-primary-contrast,#fff);
    background: var(--theme-primary, hsl(var(--primary-h),var(--primary-s),var(--primary-l)));
}

/* Acciones: iconos con área táctil de 44px. */
.tech-header__actions { flex: 0 0 auto; display: flex; align-items: center; gap: 4px; }
.tech-header__action {
    color: var(--theme-header-text,#cbd5e1); opacity: .9; text-decoration: none;
    display: inline-flex; align-items: center; gap: .45rem;
    min-height: 38px; padding: 0 9px; border-radius: 8px;
    font-size: 12.5px; font-weight: 500; position: relative;
    background: none; border: none; cursor: pointer;
    transition: background .15s, color .15s, opacity .15s;
}
.tech-header__action:hover {
    color: var(--theme-primary,hsl(var(--primary-h),var(--primary-s),var(--primary-l)));
    background: rgba(128,128,128,.12);
    background: color-mix(in srgb, currentColor 10%, transparent);
    opacity: 1; text-decoration: none;
}
.tech-header__action:focus-visible { outline: 2px solid currentColor; outline-offset: -2px; }
.tech-header__badge {
    position: absolute; top: 1px; right: 0;
    background: var(--theme-primary,hsl(var(--primary-h),var(--primary-s),var(--primary-l)));
    color: var(--theme-primary-contrast,#fff);
    font-size: 10px; font-weight: 700; min-width: 17px; height: 17px; padding: 0 4px;
    border-radius: 9px; display: flex; align-items: center; justify-content: center;
    font-variant-numeric: tabular-nums;
}

.tech-header__nav { background: var(--theme-header-soft,#1e293b); }
.tech-header__nav-inner { display: flex; align-items: center; gap: .25rem; padding: 6px 24px; max-width: 1400px; margin: 0 auto; overflow-x: auto; }
.tech-header__nav-link { font-size: 12px; font-weight: 600; color: var(--theme-header-text,#94a3b8); opacity: .75; padding: 5px 12px; border-radius: 4px; text-decoration: none; white-space: nowrap; transition: all .15s; }
.tech-header__nav-link:hover,.tech-header__nav-link--active { background: var(--theme-primary,hsl(var(--primary-h),var(--primary-s),var(--primary-l))); color: var(--theme-primary-contrast,#fff); opacity: 1; text-decoration: none; }

/* Móvil: el buscador no se esconde, baja a su propia línea. Antes era
   display:none y en un teléfono la tienda se quedaba sin buscador. */
@media (max-width: 767px) {
    .tech-header__top { flex-wrap: wrap; gap: 6px; padding: 7px 14px 9px; }
    .tech-header__action { min-height: 42px; }
    .tech-header__logo { margin-right: auto; }
    .tech-header__logo img { height: 32px; }
    .tech-header__actions { gap: 0; }
    .tech-header__action { padding: 0 8px; }
    .tech-header__action span:not(.tech-header__badge) { display: none; }
    .tech-header__search {
        order: 3; flex: 1 0 100%; max-width: none; margin: 2px 0 0;
    }
    .tech-header__search input { height: 38px; line-height: 38px; font-size: 15px; }
}
</style>

<header class="tech-header">
    <div class="tech-header__top">
        <a href="{{ route('tenant.ecommerce.index') }}" class="tech-header__logo"><img src="{{ $logo }}" alt="{{ $company->name ?? '' }}"></a>
        {{-- El buscador estaba suelto: sin form y sin name, escribir y pulsar
             Enter no hacia nada. Ahora es un GET al propio listado, que ya
             lee ?q= en el controlador. Sin JS: funciona igual con o sin el. --}}
        <div class="tech-header__search">
            <form action="{{ route('tenant.ecommerce.index') }}" method="GET" role="search">
                <label for="ec-search-input" class="sr-only">Buscar productos</label>
                <svg class="tech-header__search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="ec-search-input" name="q" value="{{ request('q') }}"
                       placeholder="Buscar productos, marcas, categorías..."
                       autocomplete="off" enterkeyhint="search">
                <button type="submit" class="tech-header__search-go" aria-label="Buscar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </form>
        </div>
        <div class="tech-header__actions">
            <a href="{{ route('tenant.ecommerce.wishlist') }}" class="tech-header__action">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                <span class="ec-wishlist-counter tech-header__badge" style="display:none"></span>
            </a>
            <a href="{{ route('tenant_detail_cart') }}" class="tech-header__action" id="ec-cart-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
                <span class="badge-custom cart-count tech-header__badge">0</span>
            </a>
            @guest('ecommerce')
            <a href="{{ route('tenant_ecommerce_login') }}" class="tech-header__action"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>Ingresar</span></a>
            @else
            <a href="{{ route('tenant.ecommerce.profile') }}" class="tech-header__action"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>{{ \Illuminate\Support\Str::limit(auth('ecommerce')->user()->name, 10) }}</span></a>
            @endguest
        </div>
    </div>
@include('ecommerce::layouts.partials_ecommerce.category_menu')
</header>
