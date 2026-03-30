{{-- THEME TECNOLOGÍA — Header con mega-menú tech --}}
@php
    $company = \App\Models\Tenant\Company::first();
    $econfig = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
    $logo = ($company && $company->logo) ? asset('storage/uploads/logos/'.$company->logo) : asset('porto-ecommerce/assets/images/logo-black.png');
    $categories = \Modules\Item\Models\Category::whereHas('items', fn($q) => $q->where('apply_store', 1))->orderBy('name')->take(10)->get();
@endphp

<style>
.tech-header { background:#0f172a; color:#fff; position:sticky; top:0; z-index:100; }
.tech-header__top { display:flex; align-items:center; justify-content:space-between; padding:10px 24px; max-width:1400px; margin:0 auto; }
.tech-header__logo img { height:36px; filter:brightness(0) invert(1); }
.tech-header__search { flex:1; max-width:500px; margin:0 2rem; position:relative; }
.tech-header__search input { width:100%; padding:9px 16px 9px 38px; border:none; border-radius:6px; background:#1e293b; color:#e2e8f0; font-size:13px; outline:none; }
.tech-header__search input::placeholder { color:#64748b; }
.tech-header__search input:focus { background:#334155; }
.tech-header__search-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#64748b; }
.tech-header__actions { display:flex; align-items:center; gap:1rem; }
.tech-header__action { color:#cbd5e1; text-decoration:none; display:flex; align-items:center; gap:.3rem; font-size:12px; position:relative; background:none; border:none; cursor:pointer; }
.tech-header__action:hover { color:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); text-decoration:none; }
.tech-header__badge { position:absolute; top:-6px; right:-8px; background:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); color:#fff; font-size:9px; font-weight:700; min-width:16px; height:16px; border-radius:8px; display:flex; align-items:center; justify-content:center; }
.tech-header__nav { background:#1e293b; }
.tech-header__nav-inner { display:flex; align-items:center; gap:.25rem; padding:6px 24px; max-width:1400px; margin:0 auto; overflow-x:auto; }
.tech-header__nav-link { font-size:12px; font-weight:600; color:#94a3b8; padding:5px 12px; border-radius:4px; text-decoration:none; white-space:nowrap; transition:all .15s; }
.tech-header__nav-link:hover,.tech-header__nav-link--active { background:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); color:#fff; text-decoration:none; }
@media(max-width:767px) { .tech-header__search{display:none;} .tech-header__action span{display:none;} }
</style>

<header class="tech-header">
    <div class="tech-header__top">
        <a href="{{ route('tenant.ecommerce.index') }}" class="tech-header__logo"><img src="{{ $logo }}" alt="{{ $company->name ?? '' }}"></a>
        <div class="tech-header__search">
            <svg class="tech-header__search-icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="ec-search-input" placeholder="Buscar productos, marcas, categorías...">
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
    @if($categories->count())
    <nav class="tech-header__nav"><div class="tech-header__nav-inner">
        <a href="{{ route('tenant.ecommerce.index') }}" class="tech-header__nav-link tech-header__nav-link--active">Todo</a>
        @foreach($categories as $cat)
        <a href="{{ route('tenant.ecommerce.index', \Illuminate\Support\Str::slug($cat->name)) }}" class="tech-header__nav-link">{{ $cat->name }}</a>
        @endforeach
    </div></nav>
    @endif
</header>
