{{--
    THEME ROPA — Header estilo moda
    Minimalista, tipografía serif, navegación limpia
    Hereda funcionalidad del original, cambia solo el estilo visual
--}}
@php
    $company = \App\Models\Tenant\Company::first();
    $econfig = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
    $logo = ($company && $company->logo) ? asset('storage/uploads/logos/'.$company->logo) : asset('porto-ecommerce/assets/images/logo-black.png');
    $phoneWa = $econfig->phone_whatsapp ?? null;
@endphp

<style>
/* ═══ THEME ROPA — HEADER ═══ */
.ropa-header {
    background: #fff;
    border-bottom: 1px solid #f3f4f6;
    position: sticky;
    top: 0;
    z-index: 100;
}
.ropa-header__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 24px;
    max-width: 1400px;
    margin: 0 auto;
}
.ropa-header__logo img {
    height: 40px;
    width: auto;
}
.ropa-header__search {
    flex: 1;
    max-width: 460px;
    margin: 0 2rem;
    position: relative;
}
.ropa-header__search-input {
    width: 100%;
    padding: 9px 16px 9px 38px;
    border: 1.5px solid #e5e7eb;
    border-radius: 0;
    font-size: 13px;
    background: #fafafa;
    transition: border-color .18s;
    outline: none;
}
.ropa-header__search-input:focus {
    border-color: hsl(var(--primary-h), var(--primary-s), var(--primary-l));
}
.ropa-header__search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
}
.ropa-header__actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.ropa-header__action {
    display: flex;
    align-items: center;
    gap: .3rem;
    text-decoration: none;
    color: #374151;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    position: relative;
    background: none;
    border: none;
    cursor: pointer;
}
.ropa-header__action:hover {
    color: hsl(var(--primary-h), var(--primary-s), var(--primary-l));
    text-decoration: none;
}
.ropa-header__badge {
    position: absolute;
    top: -6px;
    right: -8px;
    background: hsl(var(--primary-h), var(--primary-s), var(--primary-l));
    color: #fff;
    font-size: 9px;
    font-weight: 700;
    min-width: 16px;
    height: 16px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

/* Nav de categorías */
.ropa-header__nav {
    background: #fafafa;
    border-bottom: 1px solid #f3f4f6;
}
.ropa-header__nav-inner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    padding: 8px 24px;
    max-width: 1400px;
    margin: 0 auto;
    overflow-x: auto;
}
.ropa-header__nav-link {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: #374151;
    text-decoration: none;
    padding: 4px 12px;
    white-space: nowrap;
    transition: color .18s;
}
.ropa-header__nav-link:hover,
.ropa-header__nav-link--active {
    color: hsl(var(--primary-h), var(--primary-s), var(--primary-l));
    text-decoration: none;
}

/* Dark mode toggle */
.ropa-header__dark-toggle {
    background: none;
    border: none;
    cursor: pointer;
    color: #6b7280;
    padding: 4px;
}

/* Mobile */
@media (max-width: 767px) {
    .ropa-header__search { display: none; }
    .ropa-header__top { padding: 10px 16px; }
    .ropa-header__actions { gap: .6rem; }
    .ropa-header__action span { display: none; }
    .ropa-header__nav-inner { justify-content: flex-start; }
}
</style>

<header class="ropa-header">
    <div class="ropa-header__top">
        {{-- Logo --}}
        <a href="{{ route('tenant.ecommerce.index') }}" class="ropa-header__logo">
            <img src="{{ $logo }}" alt="{{ $company->trade_name ?? $company->name ?? 'Tienda' }}">
        </a>

        {{-- Buscador --}}
        <div class="ropa-header__search">
            <svg class="ropa-header__search-icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" class="ropa-header__search-input" id="ec-search-input"
                   placeholder="Buscar productos..." autocomplete="off">
        </div>

        {{-- Acciones --}}
        <div class="ropa-header__actions">
            {{-- Dark mode --}}
            <button class="ropa-header__dark-toggle" id="ec-dark-toggle" title="Modo oscuro" aria-label="Cambiar tema">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>

            {{-- Wishlist --}}
            <a href="{{ route('tenant.ecommerce.wishlist') }}" class="ropa-header__action">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                <span class="ec-wishlist-counter ropa-header__badge" style="display:none"></span>
            </a>

            {{-- Carrito --}}
            <a href="{{ route('tenant_detail_cart') }}" class="ropa-header__action" id="ec-cart-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                <span class="badge-custom cart-count ropa-header__badge">0</span>
            </a>

            {{-- Login --}}
            @guest('ecommerce')
            <a href="{{ route('tenant_ecommerce_login') }}" class="ropa-header__action">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>Ingresar</span>
            </a>
            @else
            <a href="{{ route('tenant.ecommerce.profile') }}" class="ropa-header__action">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>{{ \Illuminate\Support\Str::limit(auth('ecommerce')->user()->name, 10) }}</span>
            </a>
            @endguest
        </div>
    </div>

    {{-- Navegación por categorías --}}
    @php
        $headerCategories = \Modules\Item\Models\Category::whereHas('items', function($q){
            $q->where('apply_store', 1);
        })->orderBy('name')->take(8)->get();
    @endphp
    @if($headerCategories->count())
    <nav class="ropa-header__nav">
        <div class="ropa-header__nav-inner">
            <a href="{{ route('tenant.ecommerce.index') }}" class="ropa-header__nav-link">Inicio</a>
            @foreach($headerCategories as $cat)
            <a href="{{ route('tenant.ecommerce.index', \Illuminate\Support\Str::slug($cat->name)) }}"
               class="ropa-header__nav-link">{{ $cat->name }}</a>
            @endforeach
        </div>
    </nav>
    @endif
</header>
