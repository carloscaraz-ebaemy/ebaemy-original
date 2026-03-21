@php
    $configurationModel = \App\Models\Tenant\Configuration::first();
    $defaultImage = $configurationModel->product_default_image ?? 'imagen-no-disponible.jpg';
    $defaultImagePath = $defaultImage === 'imagen-no-disponible.jpg'
        ? asset('logo/imagen-no-disponible.jpg')
        : asset('storage/defaults/' . $defaultImage);
@endphp

<div class="dropdown cart-dropdown" style="position:relative;z-index:9999;">
    <a href="#" class="dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" data-display="static">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-bag"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M6.331 8h11.339a2 2 0 0 1 1.977 2.304l-1.255 8.152a3 3 0 0 1 -2.966 2.544h-6.852a3 3 0 0 1 -2.965 -2.544l-1.255 -8.152a2 2 0 0 1 1.977 -2.304z"></path><path d="M9 11v-5a3 3 0 0 1 6 0v5"></path></svg>
    </a>
    <span class="cart-count" style="position:absolute;top:-6px;right:-6px;background:#e53e3e;color:#fff;font-size:11px;font-weight:700;min-width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;padding:0 3px;pointer-events:none;">0</span>
    <div class="dropdown-menu ec-minicart-dropdown">
        <div class="ec-minicart-header">
            <span>Mi carrito</span>
            <span class="ec-minicart-count-label"><span class="cart-count-label">0</span> producto(s)</span>
        </div>
        <div class="dropdown-cart-products ec-minicart-list"></div>
        <div class="ec-minicart-empty" style="display:none;">
            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            <p>Tu carrito está vacío</p>
        </div>
        <div class="ec-minicart-footer">
            <div class="ec-minicart-total">
                <span>Total</span>
                <strong class="cart-total-price">S/ 0.00</strong>
            </div>
            <a href="{{ route('tenant_detail_cart') }}" class="ec-minicart-btn">Ver carrito y pagar</a>
        </div>
    </div>
</div><!-- End .dropdown -->


{{-- Cart JS handled by cart.js (productsCartDropDown / calculateTotalCart / cartRemove) --}}