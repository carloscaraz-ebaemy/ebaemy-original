{{-- THEME MODA URBANA — Footer colorido con newsletter --}}
@php
    $company = \App\Models\Tenant\Company::first();
    $econfig = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
    $configModel = \App\Models\Tenant\Configuration::firstCached();
    $showWa = ($configModel->enable_whatsapp ?? false) && !empty($econfig->phone_whatsapp);
@endphp
<style>
.urban-footer__top{background:hsl(var(--primary-h),var(--primary-s),var(--primary-l));padding:2rem;text-align:center;color:#fff}
.urban-footer__top h3{font-size:20px;font-weight:800;margin-bottom:4px}
.urban-footer__top p{font-size:13px;opacity:.9;margin-bottom:12px}
.urban-footer__top form{display:flex;max-width:400px;margin:0 auto;gap:0}
.urban-footer__top input{flex:1;padding:10px 14px;border:none;border-radius:8px 0 0 8px;font-size:13px;outline:none}
.urban-footer__top button{padding:10px 20px;background:#111;color:#fff;border:none;border-radius:0 8px 8px 0;font-size:12px;font-weight:700;cursor:pointer;text-transform:uppercase}
.urban-footer{background:#1f2937;color:#9ca3af;padding:2.5rem 0 0;font-size:13px}
.urban-footer a{color:#d1d5db;text-decoration:none}.urban-footer a:hover{color:hsl(var(--primary-h),var(--primary-s),var(--primary-l))}
.urban-footer__grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:2rem;max-width:1200px;margin:0 auto;padding:0 24px}
.urban-footer__brand h3{color:#fff;font-size:18px;font-weight:800;margin-bottom:.5rem}
.urban-footer__title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#fff;margin-bottom:.6rem}
.urban-footer__links{list-style:none;padding:0;margin:0}.urban-footer__links li{margin-bottom:.35rem}
.urban-footer__bottom{border-top:1px solid #374151;margin-top:2rem;padding:1rem 24px;text-align:center;font-size:11px;color:#6b7280;max-width:1200px;margin-left:auto;margin-right:auto}
@media(max-width:767px){.urban-footer__grid{grid-template-columns:1fr 1fr}}
</style>
<div class="urban-footer__top">
    <h3>Únete al club urbano</h3>
    <p>Suscríbete y obtén 20% OFF en tu primera compra</p>
    <form onsubmit="return false"><input type="email" placeholder="Tu correo electrónico"><button type="button">Suscribirse</button></form>
</div>
<div class="urban-footer">
    <div class="urban-footer__grid">
        <div class="urban-footer__brand"><h3>{{ $company->trade_name ?? $company->name ?? 'Tienda' }}</h3><p>{{ $econfig->information_contact_address ?? '' }}</p>@if($econfig->information_contact_phone)<p>{{ $econfig->information_contact_phone }}</p>@endif</div>
        <div><h4 class="urban-footer__title">Tienda</h4><ul class="urban-footer__links"><li><a href="{{ route('tenant.ecommerce.index') }}">Catálogo</a></li><li><a href="{{ route('tenant.ecommerce.wishlist') }}">Favoritos</a></li><li><a href="{{ route('tenant_detail_cart') }}">Carrito</a></li><li><a href="{{ route('ecommerce.tracking') }}">Rastrear</a></li></ul></div>
        <div><h4 class="urban-footer__title">Ayuda</h4><ul class="urban-footer__links"><li><a href="{{ route('tenant.terminos_condiciones') }}">Términos</a></li><li><a href="{{ route('tenant.cambios_devolucion') }}">Cambios</a></li><li><a href="{{ route('tenant.politica_envio') }}">Envíos</a></li></ul></div>
        <div><h4 class="urban-footer__title">Cuenta</h4><ul class="urban-footer__links">@guest('ecommerce')<li><a href="{{ route('tenant_ecommerce_login') }}">Ingresar</a></li>@else<li><a href="{{ route('tenant.ecommerce.profile') }}">Mi perfil</a></li><li><a href="{{ route('tenant_orders') }}">Pedidos</a></li>@endguest</ul></div>
    </div>
    <div class="urban-footer__bottom">&copy; {{ date('Y') }} {{ $company->trade_name ?? $company->name ?? '' }}</div>
</div>
@if($showWa)@php $waPhone=preg_replace('/\D+/','',$econfig->phone_whatsapp);if(strlen($waPhone)==9&&str_starts_with($waPhone,'9'))$waPhone='51'.$waPhone;@endphp<a href="https://wa.me/{{ $waPhone }}" class="btn-whatsapp-floating" target="_blank" rel="noopener" title="WhatsApp"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg></a>@endif
<div id="ec-cart-toast-container"></div>@include('ecommerce::layouts.partials_ecommerce.auth_modal')
