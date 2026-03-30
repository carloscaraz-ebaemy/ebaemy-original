{{-- THEME LUJO — Footer elegante con tonos dorados --}}
@php
    $company = \App\Models\Tenant\Company::first();
    $econfig = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
    $configModel = \App\Models\Tenant\Configuration::firstCached();
    $showWa = ($configModel->enable_whatsapp ?? false) && !empty($econfig->phone_whatsapp);
@endphp

<style>
.lux-footer { background:#0c0a09; color:#78716c; padding:3rem 0 0; font-size:13px; }
.lux-footer a { color:#a18248; text-decoration:none; } .lux-footer a:hover { color:#d4a853; }
.lux-footer__grid { display:grid; grid-template-columns:2fr 1fr 1fr; gap:3rem; max-width:1100px; margin:0 auto; padding:0 32px; }
.lux-footer__brand h3 { font-family:'Playfair Display',Georgia,serif; color:#a18248; font-size:22px; font-weight:500; letter-spacing:.1em; margin-bottom:.75rem; text-transform:uppercase; }
.lux-footer__brand p { line-height:1.7; }
.lux-footer__title { font-family:'Playfair Display',Georgia,serif; font-size:12px; font-weight:500; letter-spacing:.15em; text-transform:uppercase; color:#a18248; margin-bottom:.75rem; }
.lux-footer__links { list-style:none; padding:0; margin:0; } .lux-footer__links li { margin-bottom:.5rem; }
.lux-footer__links a { color:#78716c; letter-spacing:.03em; } .lux-footer__links a:hover { color:#d4a853; }
.lux-footer__divider { max-width:1100px; margin:2.5rem auto 0; padding:0 32px; }
.lux-footer__divider hr { border:none; border-top:1px solid #1c1917; }
.lux-footer__bottom { max-width:1100px; margin:0 auto; padding:1.25rem 32px; display:flex; justify-content:space-between; align-items:center; font-size:11px; color:#44403c; letter-spacing:.05em; }
@media(max-width:767px) { .lux-footer__grid{grid-template-columns:1fr;gap:2rem;} .lux-footer__bottom{flex-direction:column;gap:.5rem;text-align:center;} }
</style>

<div class="lux-footer">
    <div class="lux-footer__grid">
        <div class="lux-footer__brand">
            <h3>{{ $company->trade_name ?? $company->name ?? 'Boutique' }}</h3>
            <p>{{ $econfig->information_contact_address ?? '' }}</p>
            @if($econfig->information_contact_email)<p style="margin-top:.5rem">{{ $econfig->information_contact_email }}</p>@endif
            @if($econfig->information_contact_phone)<p>{{ $econfig->information_contact_phone }}</p>@endif
        </div>
        <div><h4 class="lux-footer__title">Boutique</h4><ul class="lux-footer__links">
            <li><a href="{{ route('tenant.ecommerce.index') }}">Colecciones</a></li>
            <li><a href="{{ route('tenant.ecommerce.wishlist') }}">Lista de deseos</a></li>
            <li><a href="{{ route('tenant_detail_cart') }}">Mi bolsa</a></li>
            <li><a href="{{ route('ecommerce.tracking') }}">Seguir pedido</a></li>
        </ul></div>
        <div><h4 class="lux-footer__title">Servicio</h4><ul class="lux-footer__links">
            <li><a href="{{ route('tenant.terminos_condiciones') }}">Condiciones</a></li>
            <li><a href="{{ route('tenant.cambios_devolucion') }}">Devoluciones</a></li>
            <li><a href="{{ route('tenant.politica_envio') }}">Envíos</a></li>
            <li><a href="{{ route('tenant.politica_privacidad') }}">Privacidad</a></li>
        </ul></div>
    </div>
    <div class="lux-footer__divider"><hr></div>
    <div class="lux-footer__bottom">
        <span>&copy; {{ date('Y') }} {{ $company->trade_name ?? $company->name ?? '' }}</span>
        <span>Autenticidad garantizada</span>
    </div>
</div>
@if($showWa)
@php $waPhone = preg_replace('/\D+/', '', $econfig->phone_whatsapp); if(strlen($waPhone)==9 && str_starts_with($waPhone,'9')) $waPhone='51'.$waPhone; @endphp
<a href="https://wa.me/{{ $waPhone }}" class="btn-whatsapp-floating" target="_blank" rel="noopener" title="WhatsApp" aria-label="WhatsApp"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg></a>
@endif
<div id="ec-cart-toast-container"></div>
@include('ecommerce::layouts.partials_ecommerce.auth_modal')
