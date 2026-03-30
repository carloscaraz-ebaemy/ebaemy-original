{{-- THEME LUJO — Header estilo Gucci/Louis Vuitton: elegante, serif, dorado --}}
@php
    $company = \App\Models\Tenant\Company::first();
    $econfig = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
    $logo = ($company && $company->logo) ? asset('storage/uploads/logos/'.$company->logo) : asset('porto-ecommerce/assets/images/logo-black.png');
    $categories = \Modules\Item\Models\Category::whereHas('items', fn($q) => $q->where('apply_store', 1))->orderBy('name')->take(6)->get();
@endphp

<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&display=swap');
.lux-header { background:#0c0a09; position:sticky; top:0; z-index:100; }
.lux-header__top { text-align:center; padding:6px; font-size:10px; letter-spacing:.2em; text-transform:uppercase; color:#a18248; border-bottom:1px solid #1c1917; }
.lux-header__main { display:flex; align-items:center; justify-content:space-between; padding:16px 32px; max-width:1300px; margin:0 auto; }
.lux-header__left,.lux-header__right { display:flex; align-items:center; gap:1.5rem; width:200px; }
.lux-header__right { justify-content:flex-end; }
.lux-header__logo { text-align:center; flex:1; }
.lux-header__logo img { height:42px; filter:brightness(0) invert(1); }
.lux-header__logo-text { font-family:'Playfair Display',Georgia,serif; font-size:24px; font-weight:500; color:#fff; letter-spacing:.15em; text-transform:uppercase; text-decoration:none; }
.lux-header__logo-text:hover { color:#fff; text-decoration:none; }
.lux-header__action { color:#a18248; text-decoration:none; display:flex; align-items:center; position:relative; background:none; border:none; cursor:pointer; transition:color .18s; }
.lux-header__action:hover { color:#d4a853; text-decoration:none; }
.lux-header__badge { position:absolute; top:-6px; right:-8px; background:#a18248; color:#0c0a09; font-size:8px; font-weight:700; min-width:15px; height:15px; border-radius:8px; display:flex; align-items:center; justify-content:center; }
.lux-header__nav { border-top:1px solid #1c1917; }
.lux-header__nav-inner { display:flex; align-items:center; justify-content:center; gap:2rem; padding:10px 24px; max-width:1300px; margin:0 auto; }
.lux-header__nav-link { font-family:'Playfair Display',Georgia,serif; font-size:12px; font-weight:500; letter-spacing:.15em; text-transform:uppercase; color:#a18248; text-decoration:none; transition:color .18s; }
.lux-header__nav-link:hover { color:#fff; text-decoration:none; }
@media(max-width:767px) {
    .lux-header__main{padding:12px 16px;}
    .lux-header__left,.lux-header__right{width:auto;gap:.75rem;}
    .lux-header__logo-text{font-size:18px;letter-spacing:.1em;}
    .lux-header__nav-inner{gap:1rem;overflow-x:auto;}
    .lux-header__nav-link{font-size:10px;}
}
</style>

<header class="lux-header">
    <div class="lux-header__top">Envío express disponible &middot; Garantía de autenticidad</div>
    <div class="lux-header__main">
        <div class="lux-header__left">
            <button class="lux-header__action" id="ec-dark-toggle" title="Tema" aria-label="Cambiar tema">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
            <div style="position:relative">
                <input type="text" id="ec-search-input" placeholder="Buscar" style="background:transparent;border:none;border-bottom:1px solid #333;color:#a18248;font-size:12px;padding:4px 0;width:120px;outline:none;font-family:'Playfair Display',serif;letter-spacing:.05em">
            </div>
        </div>

        <div class="lux-header__logo">
            <a href="{{ route('tenant.ecommerce.index') }}" class="lux-header__logo-text">
                {{ $company->trade_name ?? $company->name ?? 'Boutique' }}
            </a>
        </div>

        <div class="lux-header__right">
            <a href="{{ route('tenant.ecommerce.wishlist') }}" class="lux-header__action">
                <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                <span class="ec-wishlist-counter lux-header__badge" style="display:none"></span>
            </a>
            <a href="{{ route('tenant_detail_cart') }}" class="lux-header__action" id="ec-cart-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                <span class="badge-custom cart-count lux-header__badge">0</span>
            </a>
            @guest('ecommerce')
            <a href="{{ route('tenant_ecommerce_login') }}" class="lux-header__action">
                <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </a>
            @else
            <a href="{{ route('tenant.ecommerce.profile') }}" class="lux-header__action">
                <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </a>
            @endguest
        </div>
    </div>

    @if($categories->count())
    <nav class="lux-header__nav"><div class="lux-header__nav-inner">
        @foreach($categories as $cat)
        <a href="{{ route('tenant.ecommerce.index', \Illuminate\Support\Str::slug($cat->name)) }}" class="lux-header__nav-link">{{ $cat->name }}</a>
        @endforeach
    </div></nav>
    @endif
</header>
