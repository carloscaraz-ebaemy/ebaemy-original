{{-- THEME DEPORTES — Header estilo Nike/Adidas: bold, energético --}}
@php
    $company = \App\Models\Tenant\Company::first();
    $econfig = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
    $logo = ($company && $company->logo) ? asset('storage/uploads/logos/'.$company->logo) : asset('porto-ecommerce/assets/images/logo-black.png');
    $categories = \Modules\Item\Models\Category::whereHas('items', fn($q) => $q->where('apply_store', 1))->orderBy('name')->take(8)->get();
@endphp

<style>
.sport-header { background:#000; position:sticky; top:0; z-index:100; }
.sport-header__promo { background:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); text-align:center; padding:5px 10px; font-size:11px; font-weight:700; color:#fff; text-transform:uppercase; letter-spacing:.12em; }
.sport-header__main { display:flex; align-items:center; justify-content:space-between; padding:10px 24px; max-width:1400px; margin:0 auto; }
.sport-header__logo img { height:38px; filter:brightness(0) invert(1); }
.sport-header__nav { display:flex; gap:.25rem; }
.sport-header__nav-link { font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:#fff; text-decoration:none; padding:8px 14px; transition:color .15s; }
.sport-header__nav-link:hover { color:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); text-decoration:none; }
.sport-header__search { position:relative; width:220px; }
.sport-header__search input { width:100%; padding:8px 14px 8px 36px; border:none; border-radius:20px; background:#1a1a1a; color:#fff; font-size:12px; outline:none; }
.sport-header__search input::placeholder { color:#666; }
.sport-header__search input:focus { background:#222; }
.sport-header__search-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#666; }
.sport-header__actions { display:flex; align-items:center; gap:1rem; }
.sport-header__action { color:#fff; text-decoration:none; position:relative; display:flex; align-items:center; background:none; border:none; cursor:pointer; }
.sport-header__action:hover { color:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); text-decoration:none; }
.sport-header__badge { position:absolute; top:-6px; right:-8px; background:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); color:#fff; font-size:9px; font-weight:700; min-width:16px; height:16px; border-radius:8px; display:flex; align-items:center; justify-content:center; }
@media(max-width:991px) { .sport-header__nav{display:none;} .sport-header__search{width:160px;} }
@media(max-width:767px) { .sport-header__search{display:none;} }
</style>

<header class="sport-header">
    <div class="sport-header__promo">Envío gratis en compras mayores a S/ 199</div>
    <div class="sport-header__main">
        <a href="{{ route('tenant.ecommerce.index') }}" class="sport-header__logo">
            <img src="{{ $logo }}" alt="{{ $company->name ?? '' }}">
        </a>

        <nav class="sport-header__nav">
            <a href="{{ route('tenant.ecommerce.index') }}" class="sport-header__nav-link">Inicio</a>
            @foreach($categories->take(5) as $cat)
            <a href="{{ route('tenant.ecommerce.index', \Illuminate\Support\Str::slug($cat->name)) }}" class="sport-header__nav-link">{{ $cat->name }}</a>
            @endforeach
        </nav>

        <div class="sport-header__search">
            <svg class="sport-header__search-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="ec-search-input" placeholder="Buscar">
        </div>

        <div class="sport-header__actions">
            <a href="{{ route('tenant.ecommerce.wishlist') }}" class="sport-header__action">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                <span class="ec-wishlist-counter sport-header__badge" style="display:none"></span>
            </a>
            <a href="{{ route('tenant_detail_cart') }}" class="sport-header__action" id="ec-cart-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                <span class="badge-custom cart-count sport-header__badge">0</span>
            </a>
            @guest('ecommerce')
            <a href="{{ route('tenant_ecommerce_login') }}" class="sport-header__action">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </a>
            @else
            <a href="{{ route('tenant.ecommerce.profile') }}" class="sport-header__action">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </a>
            @endguest
        </div>
    </div>
</header>
