{{-- THEME FARMACIA — Header limpio, profesional, colores salud --}}
@php
    $company = \App\Models\Tenant\Company::first();
    $econfig = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
    $logo = ($company && $company->logo) ? asset('storage/uploads/logos/'.$company->logo) : asset('porto-ecommerce/assets/images/logo-black.png');
    $categories = \Modules\Item\Models\Category::whereHas('items', fn($q) => $q->where('apply_store', 1))->orderBy('name')->take(8)->get();
@endphp
<style>
.pharma-header{background:#fff;border-bottom:2px solid #e0f2fe;position:sticky;top:0;z-index:100}
.pharma-header__top{display:flex;align-items:center;justify-content:space-between;padding:10px 24px;max-width:1400px;margin:0 auto}
.pharma-header__logo img{height:40px}
.pharma-header__search{flex:1;max-width:480px;margin:0 2rem;position:relative}
.pharma-header__search input{width:100%;padding:10px 16px 10px 40px;border:2px solid #e0f2fe;border-radius:8px;font-size:13px;outline:none;transition:border-color .18s}
.pharma-header__search input:focus{border-color:hsl(var(--primary-h),var(--primary-s),var(--primary-l))}
.pharma-header__search-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:hsl(var(--primary-h),var(--primary-s),var(--primary-l))}
.pharma-header__actions{display:flex;align-items:center;gap:1rem}
.pharma-header__action{color:#374151;text-decoration:none;display:flex;align-items:center;gap:.3rem;font-size:13px;font-weight:600;position:relative;background:none;border:none;cursor:pointer}
.pharma-header__action:hover{color:hsl(var(--primary-h),var(--primary-s),var(--primary-l));text-decoration:none}
.pharma-header__badge{position:absolute;top:-6px;right:-8px;background:hsl(var(--primary-h),var(--primary-s),var(--primary-l));color:#fff;font-size:9px;font-weight:700;min-width:16px;height:16px;border-radius:8px;display:flex;align-items:center;justify-content:center}
.pharma-header__nav{background:#f0f9ff}
.pharma-header__nav-inner{display:flex;gap:.5rem;padding:8px 24px;max-width:1400px;margin:0 auto;overflow-x:auto}
.pharma-header__nav-link{font-size:12px;font-weight:600;color:#0369a1;padding:5px 14px;border-radius:6px;text-decoration:none;white-space:nowrap;transition:all .15s}
.pharma-header__nav-link:hover{background:hsl(var(--primary-h),var(--primary-s),var(--primary-l));color:#fff;text-decoration:none}
@media(max-width:767px){.pharma-header__search{display:none}.pharma-header__action span{display:none}}
</style>
<header class="pharma-header">
    <div class="pharma-header__top">
        <a href="{{ route('tenant.ecommerce.index') }}" class="pharma-header__logo"><img src="{{ $logo }}" alt="{{ $company->name ?? '' }}"></a>
        <div class="pharma-header__search">
            <svg class="pharma-header__search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="ec-search-input" placeholder="Buscar medicamentos, productos...">
        </div>
        <div class="pharma-header__actions">
            <a href="{{ route('tenant.ecommerce.wishlist') }}" class="pharma-header__action"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg><span class="ec-wishlist-counter pharma-header__badge" style="display:none"></span></a>
            <a href="{{ route('tenant_detail_cart') }}" class="pharma-header__action" id="ec-cart-link"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg><span class="badge-custom cart-count pharma-header__badge">0</span></a>
            @guest('ecommerce')<a href="{{ route('tenant_ecommerce_login') }}" class="pharma-header__action"><span>Ingresar</span></a>@else<a href="{{ route('tenant.ecommerce.profile') }}" class="pharma-header__action"><span>{{ \Illuminate\Support\Str::limit(auth('ecommerce')->user()->name, 10) }}</span></a>@endguest
        </div>
    </div>
    @if($categories->count())
    <nav class="pharma-header__nav"><div class="pharma-header__nav-inner">
        <a href="{{ route('tenant.ecommerce.index') }}" class="pharma-header__nav-link">Todos</a>
        @foreach($categories as $cat)<a href="{{ route('tenant.ecommerce.index', \Illuminate\Support\Str::slug($cat->name)) }}" class="pharma-header__nav-link">{{ $cat->name }}</a>@endforeach
    </div></nav>
    @endif
</header>
