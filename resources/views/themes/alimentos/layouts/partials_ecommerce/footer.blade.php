{{-- THEME ALIMENTOS — Footer con info delivery --}}
@php
    $company = \App\Models\Tenant\Company::first();
    $econfig = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
    $configModel = \App\Models\Tenant\Configuration::firstCached();
    $showWa = ($configModel->enable_whatsapp ?? false) && !empty($econfig->phone_whatsapp);
@endphp

<style>
.food-footer { background:#fef7ed; padding:2.5rem 0 0; font-size:13px; color:#78350f; }
.food-footer a { color:#78350f; text-decoration:none; } .food-footer a:hover { color:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); }
.food-footer__grid { display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:2rem; max-width:1200px; margin:0 auto; padding:0 24px; }
.food-footer__brand h3 { color:#451a03; font-size:20px; margin-bottom:.5rem; }
.food-footer__title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#451a03; margin-bottom:.6rem; }
.food-footer__links { list-style:none; padding:0; margin:0; } .food-footer__links li { margin-bottom:.35rem; }
.food-footer__delivery { background:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); color:#fff; padding:1rem 24px; margin-top:2rem; text-align:center; }
.food-footer__delivery strong { font-size:14px; }
.food-footer__bottom { background:#451a03; color:#d6b899; padding:.75rem; text-align:center; font-size:11px; }
@media(max-width:767px) { .food-footer__grid{grid-template-columns:1fr 1fr;} }
</style>

<div class="food-footer">
    <div class="food-footer__grid">
        <div class="food-footer__brand">
            <h3>{{ $company->trade_name ?? $company->name ?? 'Tienda' }}</h3>
            <p>{{ $econfig->information_contact_address ?? '' }}</p>
            @if($econfig->information_contact_phone)<p>Tel: {{ $econfig->information_contact_phone }}</p>@endif
        </div>
        <div><h4 class="food-footer__title">Menú</h4><ul class="food-footer__links">
            <li><a href="{{ route('tenant.ecommerce.index') }}">Catálogo</a></li>
            <li><a href="{{ route('tenant_detail_cart') }}">Mi pedido</a></li>
            <li><a href="{{ route('ecommerce.tracking') }}">Rastrear pedido</a></li>
        </ul></div>
        <div><h4 class="food-footer__title">Info</h4><ul class="food-footer__links">
            <li><a href="{{ route('tenant.terminos_condiciones') }}">Términos</a></li>
            <li><a href="{{ route('tenant.politica_privacidad') }}">Privacidad</a></li>
            <li><a href="{{ route('tenant.politica_envio') }}">Zonas de envío</a></li>
        </ul></div>
        <div><h4 class="food-footer__title">Contacto</h4><ul class="food-footer__links">
            @if($econfig->information_contact_email)<li>{{ $econfig->information_contact_email }}</li>@endif
            @if($econfig->information_contact_phone)<li>{{ $econfig->information_contact_phone }}</li>@endif
        </ul></div>
    </div>
    <div class="food-footer__delivery">
        <strong>Hacemos envíos a domicilio</strong> — Consulta zonas de cobertura
    </div>
    <div class="food-footer__bottom">&copy; {{ date('Y') }} {{ $company->trade_name ?? $company->name ?? '' }}</div>
</div>
@if($showWa)
@php $waPhone = preg_replace('/\D+/', '', $econfig->phone_whatsapp); if(strlen($waPhone)==9 && str_starts_with($waPhone,'9')) $waPhone='51'.$waPhone; @endphp
<a href="https://wa.me/{{ $waPhone }}" class="btn-whatsapp-floating" target="_blank" rel="noopener" title="WhatsApp"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg></a>
@endif
<div id="ec-cart-toast-container"></div>
@include('ecommerce::layouts.partials_ecommerce.auth_modal')
