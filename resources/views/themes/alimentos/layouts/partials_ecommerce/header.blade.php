{{-- THEME ALIMENTOS — Header estilo delivery/fresh --}}
@php
    $company = \App\Models\Tenant\Company::first();
    $econfig = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
    $logo = ($company && $company->logo) ? asset('storage/uploads/logos/'.$company->logo) : asset('porto-ecommerce/assets/images/logo-black.png');
    $categories = \Modules\Item\Models\Category::whereHas('items', fn($q) => $q->where('apply_store', 1))->orderBy('name')->take(8)->get();
@endphp

<style>
.food-header { background:#fff; border-bottom:2px solid hsl(var(--primary-h),var(--primary-s),92%); position:sticky; top:0; z-index:100; }
.food-header__top { display:flex; align-items:center; justify-content:space-between; padding:10px 20px; max-width:1300px; margin:0 auto; }
.food-header__logo img { height:40px; }
.food-header__search { flex:1; max-width:440px; margin:0 1.5rem; position:relative; }
.food-header__search input { width:100%; padding:10px 16px 10px 40px; border:2px solid hsl(var(--primary-h),var(--primary-s),90%); border-radius:24px; font-size:14px; outline:none; transition:border-color .18s; }
.food-header__search input:focus { border-color:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); }
.food-header__search-icon { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); }
.food-header__actions { display:flex; align-items:center; gap:1rem; }
.food-header__action { color:#374151; text-decoration:none; display:flex; align-items:center; gap:.3rem; font-size:13px; font-weight:600; position:relative; background:none; border:none; cursor:pointer; }
.food-header__action:hover { color:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); text-decoration:none; }
.food-header__badge { position:absolute; top:-6px; right:-8px; background:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); color:#fff; font-size:9px; font-weight:700; min-width:16px; height:16px; border-radius:8px; display:flex; align-items:center; justify-content:center; }
.food-header__cats { background:hsl(var(--primary-h),var(--primary-s),96%); }
.food-header__cats-inner { display:flex; gap:.5rem; padding:8px 20px; max-width:1300px; margin:0 auto; overflow-x:auto; }
.food-header__cat-pill { font-size:12px; font-weight:600; color:#374151; padding:6px 14px; border-radius:20px; text-decoration:none; white-space:nowrap; transition:all .15s; background:#fff; border:1px solid #e5e7eb; }
.food-header__cat-pill:hover,.food-header__cat-pill--active { background:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); color:#fff; border-color:transparent; text-decoration:none; }
@media(max-width:767px) { .food-header__search{display:none;} .food-header__action span{display:none;} }
</style>

<header class="food-header">
    <div class="food-header__top">
        <a href="{{ route('tenant.ecommerce.index') }}" class="food-header__logo"><img src="{{ $logo }}" alt="{{ $company->name ?? '' }}"></a>
        <div class="food-header__search">
            <svg class="food-header__search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="ec-search-input" placeholder="¿Qué estás buscando hoy?">
        </div>
        <div class="food-header__actions">
            <a href="{{ route('tenant.ecommerce.wishlist') }}" class="food-header__action">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                <span class="ec-wishlist-counter food-header__badge" style="display:none"></span>
            </a>
            <a href="{{ route('tenant_detail_cart') }}" class="food-header__action" id="ec-cart-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                <span class="badge-custom cart-count food-header__badge">0</span>
            </a>
            @guest('ecommerce')
            <a href="{{ route('tenant_ecommerce_login') }}" class="food-header__action"><span>Ingresar</span></a>
            @else
            <a href="{{ route('tenant.ecommerce.profile') }}" class="food-header__action"><span>{{ \Illuminate\Support\Str::limit(auth('ecommerce')->user()->name, 10) }}</span></a>
            @endguest
        </div>
    </div>
    @if($categories->count())
    <div class="food-header__cats"><div class="food-header__cats-inner">
        <a href="{{ route('tenant.ecommerce.index') }}" class="food-header__cat-pill food-header__cat-pill--active">Todos</a>
        @foreach($categories as $cat)
        <a href="{{ route('tenant.ecommerce.index', \Illuminate\Support\Str::slug($cat->name)) }}" class="food-header__cat-pill">{{ $cat->name }}</a>
        @endforeach
    </div></div>
    @endif
</header>
