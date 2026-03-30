{{-- THEME MODA ELEGANTE — Footer ultra minimalista --}}
@php
    $company = \App\Models\Tenant\Company::first();
    $econfig = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
    $configModel = \App\Models\Tenant\Configuration::firstCached();
    $showWa = ($configModel->enable_whatsapp ?? false) && !empty($econfig->phone_whatsapp);
@endphp
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400&display=swap');
.eleg-footer{background:#fafafa;font-family:'Inter',sans-serif;padding:3rem 0 0;font-size:12px;color:#999;letter-spacing:.02em}
.eleg-footer a{color:#666;text-decoration:none}.eleg-footer a:hover{color:#111}
.eleg-footer__inner{max-width:900px;margin:0 auto;padding:0 40px;text-align:center}
.eleg-footer__brand{font-size:18px;font-weight:300;color:#333;letter-spacing:.25em;text-transform:uppercase;margin-bottom:1.5rem}
.eleg-footer__links{display:flex;justify-content:center;gap:2rem;flex-wrap:wrap;margin-bottom:1.5rem}
.eleg-footer__link{font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#999}
.eleg-footer__link:hover{color:#111;text-decoration:none}
.eleg-footer__contact{margin-bottom:1.5rem;line-height:1.8}
.eleg-footer__bottom{border-top:1px solid #eee;padding:1.25rem 0;font-size:10px;color:#bbb;letter-spacing:.06em}
</style>
<div class="eleg-footer">
    <div class="eleg-footer__inner">
        <div class="eleg-footer__brand">{{ $company->trade_name ?? $company->name ?? 'Studio' }}</div>
        <div class="eleg-footer__links">
            <a href="{{ route('tenant.ecommerce.index') }}" class="eleg-footer__link">Colección</a>
            <a href="{{ route('tenant.ecommerce.wishlist') }}" class="eleg-footer__link">Favoritos</a>
            <a href="{{ route('ecommerce.tracking') }}" class="eleg-footer__link">Seguimiento</a>
            <a href="{{ route('tenant.terminos_condiciones') }}" class="eleg-footer__link">Términos</a>
            <a href="{{ route('tenant.politica_privacidad') }}" class="eleg-footer__link">Privacidad</a>
            <a href="{{ route('tenant.politica_envio') }}" class="eleg-footer__link">Envíos</a>
        </div>
        <div class="eleg-footer__contact">
            @if($econfig->information_contact_email){{ $econfig->information_contact_email }}<br>@endif
            @if($econfig->information_contact_phone){{ $econfig->information_contact_phone }}@endif
        </div>
        <div class="eleg-footer__bottom">&copy; {{ date('Y') }} {{ $company->trade_name ?? $company->name ?? '' }}</div>
    </div>
</div>
@if($showWa)@php $waPhone=preg_replace('/\D+/','',$econfig->phone_whatsapp);if(strlen($waPhone)==9&&str_starts_with($waPhone,'9'))$waPhone='51'.$waPhone;@endphp<a href="https://wa.me/{{ $waPhone }}" class="btn-whatsapp-floating" target="_blank" rel="noopener" title="WhatsApp"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg></a>@endif
<div id="ec-cart-toast-container"></div>@include('ecommerce::layouts.partials_ecommerce.auth_modal')
