{{-- THEME MODA ELEGANTE — Header ultra minimalista estilo Massimo Dutti/COS --}}
@php
    $company = \App\Models\Tenant\Company::first();
    $econfig = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
    $logo = ($company && $company->logo) ? asset('storage/uploads/logos/'.$company->logo) : asset('porto-ecommerce/assets/images/logo-black.png');
    $categories = \Modules\Item\Models\Category::whereHas('items', fn($q) => $q->where('apply_store', 1))->orderBy('name')->take(6)->get();
@endphp
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500&display=swap');
.eleg-header{background:#fff;position:sticky;top:0;z-index:100;font-family:'Inter',sans-serif}
.eleg-header__main{display:flex;align-items:center;justify-content:space-between;padding:20px 40px;max-width:1200px;margin:0 auto}
.eleg-header__left,.eleg-header__right{display:flex;align-items:center;gap:1.5rem;width:200px}
.eleg-header__right{justify-content:flex-end}
.eleg-header__logo{text-align:center;flex:1}
.eleg-header__logo-text{font-size:22px;font-weight:300;color:#111;letter-spacing:.3em;text-transform:uppercase;text-decoration:none}
.eleg-header__logo-text:hover{color:#111;text-decoration:none}
.eleg-header__action{color:#666;text-decoration:none;display:flex;align-items:center;position:relative;background:none;border:none;cursor:pointer;transition:color .2s}
.eleg-header__action:hover{color:#111;text-decoration:none}
.eleg-header__badge{position:absolute;top:-5px;right:-7px;background:#111;color:#fff;font-size:8px;font-weight:500;min-width:14px;height:14px;border-radius:7px;display:flex;align-items:center;justify-content:center}
.eleg-header__nav{border-top:1px solid #f5f5f5;border-bottom:1px solid #f5f5f5}
.eleg-header__nav-inner{display:flex;align-items:center;justify-content:center;gap:2.5rem;padding:12px 40px;max-width:1200px;margin:0 auto}
.eleg-header__nav-link{font-size:11px;font-weight:400;letter-spacing:.2em;text-transform:uppercase;color:#666;text-decoration:none;transition:color .2s}
.eleg-header__nav-link:hover{color:#111;text-decoration:none}
.eleg-header__search-toggle{background:none;border:none;cursor:pointer;color:#666}
.eleg-header__search-toggle:hover{color:#111}
@media(max-width:767px){.eleg-header__main{padding:14px 20px}.eleg-header__left,.eleg-header__right{width:auto;gap:.75rem}.eleg-header__logo-text{font-size:16px;letter-spacing:.2em}.eleg-header__nav-inner{gap:1.25rem;overflow-x:auto}.eleg-header__nav-link{font-size:10px}}
</style>
<header class="eleg-header">
    <div class="eleg-header__main">
        <div class="eleg-header__left">
            <button class="eleg-header__search-toggle" aria-label="Buscar">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </button>
            <input type="text" id="ec-search-input" placeholder="Buscar" style="background:transparent;border:none;border-bottom:1px solid #ddd;font-size:12px;padding:4px 0;width:120px;outline:none;font-family:'Inter',sans-serif;letter-spacing:.05em;color:#333">
        </div>
        <div class="eleg-header__logo">
            <a href="{{ route('tenant.ecommerce.index') }}" class="eleg-header__logo-text">{{ $company->trade_name ?? $company->name ?? 'Studio' }}</a>
        </div>
        <div class="eleg-header__right">
            <a href="{{ route('tenant.ecommerce.wishlist') }}" class="eleg-header__action"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg><span class="ec-wishlist-counter eleg-header__badge" style="display:none"></span></a>
            <a href="{{ route('tenant_detail_cart') }}" class="eleg-header__action" id="ec-cart-link"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg><span class="badge-custom cart-count eleg-header__badge">0</span></a>
            @guest('ecommerce')<a href="{{ route('tenant_ecommerce_login') }}" class="eleg-header__action"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></a>@else<a href="{{ route('tenant.ecommerce.profile') }}" class="eleg-header__action"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></a>@endguest
        </div>
    </div>
    @if($categories->count())
    <nav class="eleg-header__nav"><div class="eleg-header__nav-inner">
        @foreach($categories as $cat)<a href="{{ route('tenant.ecommerce.index', \Illuminate\Support\Str::slug($cat->name)) }}" class="eleg-header__nav-link">{{ $cat->name }}</a>@endforeach
    </div></nav>
    @endif
</header>
