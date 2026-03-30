{{-- THEME FERRETERÍA — Footer industrial --}}
@php
    $company = \App\Models\Tenant\Company::first();
    $econfig = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
    $configModel = \App\Models\Tenant\Configuration::firstCached();
    $showWa = ($configModel->enable_whatsapp ?? false) && !empty($econfig->phone_whatsapp);
@endphp
<style>
.hw-footer{background:#1c1917;color:#a8a29e;padding:2.5rem 0 0;font-size:13px}
.hw-footer a{color:#d6d3d1;text-decoration:none}.hw-footer a:hover{color:hsl(var(--primary-h),var(--primary-s),var(--primary-l))}
.hw-footer__grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:2rem;max-width:1200px;margin:0 auto;padding:0 24px}
.hw-footer__brand h3{color:#e7e5e4;font-size:18px;font-weight:700;text-transform:uppercase;margin-bottom:.5rem}
.hw-footer__title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:hsl(var(--primary-h),var(--primary-s),var(--primary-l));margin-bottom:.6rem}
.hw-footer__links{list-style:none;padding:0;margin:0}.hw-footer__links li{margin-bottom:.35rem}
.hw-footer__bottom{border-top:1px solid #292524;margin-top:2rem;padding:1rem 24px;text-align:center;font-size:11px;color:#57534e;max-width:1200px;margin-left:auto;margin-right:auto}
@media(max-width:767px){.hw-footer__grid{grid-template-columns:1fr 1fr}}
</style>
<div class="hw-footer">
    <div class="hw-footer__grid">
        <div class="hw-footer__brand"><h3>{{ $company->trade_name ?? $company->name ?? 'Ferretería' }}</h3><p>{{ $econfig->information_contact_address ?? '' }}</p>@if($econfig->information_contact_phone)<p>Tel: {{ $econfig->information_contact_phone }}</p>@endif</div>
        <div><h4 class="hw-footer__title">Productos</h4><ul class="hw-footer__links"><li><a href="{{ route('tenant.ecommerce.index') }}">Catálogo</a></li><li><a href="{{ route('tenant.ecommerce.wishlist') }}">Favoritos</a></li><li><a href="{{ route('tenant_detail_cart') }}">Carrito</a></li></ul></div>
        <div><h4 class="hw-footer__title">Servicio</h4><ul class="hw-footer__links"><li><a href="{{ route('tenant.terminos_condiciones') }}">Términos</a></li><li><a href="{{ route('tenant.cambios_devolucion') }}">Devoluciones</a></li><li><a href="{{ route('tenant.politica_envio') }}">Envíos</a></li></ul></div>
        <div><h4 class="hw-footer__title">Cuenta</h4><ul class="hw-footer__links">@guest('ecommerce')<li><a href="{{ route('tenant_ecommerce_login') }}">Ingresar</a></li>@else<li><a href="{{ route('tenant.ecommerce.profile') }}">Mi perfil</a></li><li><a href="{{ route('tenant_orders') }}">Pedidos</a></li>@endguest</ul></div>
    </div>
    <div class="hw-footer__bottom">&copy; {{ date('Y') }} {{ $company->trade_name ?? $company->name ?? '' }}. Herramientas y materiales.</div>
</div>
@if($showWa)@php $waPhone=preg_replace('/\D+/','',$econfig->phone_whatsapp);if(strlen($waPhone)==9&&str_starts_with($waPhone,'9'))$waPhone='51'.$waPhone;@endphp<a href="https://wa.me/{{ $waPhone }}" class="btn-whatsapp-floating" target="_blank" rel="noopener" title="WhatsApp"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg></a>@endif
<div id="ec-cart-toast-container"></div>
@include('ecommerce::layouts.partials_ecommerce.auth_modal')
