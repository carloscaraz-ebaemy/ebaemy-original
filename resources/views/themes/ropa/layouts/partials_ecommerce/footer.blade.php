{{--
    THEME ROPA — Footer minimalista estilo moda
--}}
@php
    $company = \App\Models\Tenant\Company::first();
    $econfig = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
    $configModel = \App\Models\Tenant\Configuration::firstCached();
    $showWa = ($configModel->enable_whatsapp ?? false) && !empty($econfig->phone_whatsapp);
@endphp

<style>
.ropa-footer {
    background: #111827;
    color: #d1d5db;
    padding: 3rem 0 0;
    font-size: 13px;
}
.ropa-footer a { color: #d1d5db; text-decoration: none; transition: color .18s; }
.ropa-footer a:hover { color: #fff; text-decoration: none; }
.ropa-footer__grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 2rem;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px;
}
.ropa-footer__brand h3 {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 22px;
    color: #fff;
    margin-bottom: .5rem;
    font-weight: 500;
}
.ropa-footer__brand p { max-width: 300px; line-height: 1.6; }
.ropa-footer__title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: #fff;
    margin-bottom: .75rem;
}
.ropa-footer__links { list-style: none; padding: 0; margin: 0; }
.ropa-footer__links li { margin-bottom: .4rem; }
.ropa-footer__social {
    display: flex;
    gap: .75rem;
    margin-top: .75rem;
}
.ropa-footer__social a {
    width: 32px;
    height: 32px;
    border: 1px solid #374151;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
}
.ropa-footer__social a:hover { border-color: #fff; color: #fff; }
.ropa-footer__bottom {
    border-top: 1px solid #1f2937;
    margin-top: 2rem;
    padding: 1rem 24px;
    text-align: center;
    font-size: 12px;
    color: #6b7280;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
}
@media (max-width: 767px) {
    .ropa-footer__grid { grid-template-columns: 1fr 1fr; gap: 1.5rem; }
}
@media (max-width: 480px) {
    .ropa-footer__grid { grid-template-columns: 1fr; }
}
</style>

<div class="ropa-footer">
    <div class="ropa-footer__grid">
        {{-- Marca --}}
        <div class="ropa-footer__brand">
            <h3>{{ $company->trade_name ?? $company->name ?? 'Tienda' }}</h3>
            <p>{{ $econfig->information_contact_address ?? '' }}</p>
            @if($econfig->information_contact_phone)
            <p>Tel: {{ $econfig->information_contact_phone }}</p>
            @endif
            @if($econfig->information_contact_email)
            <p>{{ $econfig->information_contact_email }}</p>
            @endif
            <div class="ropa-footer__social">
                @if($econfig->link_facebook)<a href="{{ $econfig->link_facebook }}" target="_blank" rel="noopener" title="Facebook"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg></a>@endif
                @if($econfig->link_instagram)<a href="{{ $econfig->link_instagram }}" target="_blank" rel="noopener" title="Instagram"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg></a>@endif
                @if($econfig->link_tiktok)<a href="{{ $econfig->link_tiktok }}" target="_blank" rel="noopener" title="TikTok"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1 0-5.78 2.92 2.92 0 0 1 .88.13V9a6.34 6.34 0 1 0 5.45 6.28V9.41a8.16 8.16 0 0 0 4.77 1.52V7.49a4.85 4.85 0 0 1-1-.8z"/></svg></a>@endif
            </div>
        </div>

        {{-- Tienda --}}
        <div>
            <h4 class="ropa-footer__title">Tienda</h4>
            <ul class="ropa-footer__links">
                <li><a href="{{ route('tenant.ecommerce.index') }}">Catálogo</a></li>
                <li><a href="{{ route('tenant.ecommerce.wishlist') }}">Favoritos</a></li>
                <li><a href="{{ route('tenant_detail_cart') }}">Mi carrito</a></li>
                <li><a href="{{ route('ecommerce.tracking') }}">Rastrear pedido</a></li>
            </ul>
        </div>

        {{-- Legal --}}
        <div>
            <h4 class="ropa-footer__title">Legal</h4>
            <ul class="ropa-footer__links">
                <li><a href="{{ route('tenant.terminos_condiciones') }}">Términos</a></li>
                <li><a href="{{ route('tenant.politica_privacidad') }}">Privacidad</a></li>
                <li><a href="{{ route('tenant.cambios_devolucion') }}">Cambios</a></li>
                <li><a href="{{ route('tenant.politica_envio') }}">Envíos</a></li>
            </ul>
        </div>

        {{-- Cuenta --}}
        <div>
            <h4 class="ropa-footer__title">Mi Cuenta</h4>
            <ul class="ropa-footer__links">
                @guest('ecommerce')
                <li><a href="{{ route('tenant_ecommerce_login') }}">Iniciar sesión</a></li>
                <li><a href="{{ route('tenant_ecommerce_login') }}">Crear cuenta</a></li>
                @else
                <li><a href="{{ route('tenant.ecommerce.profile') }}">Mi perfil</a></li>
                <li><a href="{{ route('tenant_orders') }}">Mis pedidos</a></li>
                @endguest
            </ul>
        </div>
    </div>

    <div class="ropa-footer__bottom">
        &copy; {{ date('Y') }} {{ $company->trade_name ?? $company->name ?? 'Tienda' }}. Todos los derechos reservados.
    </div>
</div>

{{-- WhatsApp flotante --}}
@if($showWa)
@php
    $waPhone = preg_replace('/\D+/', '', $econfig->phone_whatsapp);
    if(strlen($waPhone) == 9 && str_starts_with($waPhone, '9')) $waPhone = '51' . $waPhone;
@endphp
<a href="https://wa.me/{{ $waPhone }}" class="btn-whatsapp-floating" target="_blank" rel="noopener" title="WhatsApp" aria-label="Contactar por WhatsApp">
    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
</a>
@endif

{{-- Cart toast container --}}
<div id="ec-cart-toast-container"></div>

{{-- Auth modal (reutiliza el original) --}}
@include('ecommerce::layouts.partials_ecommerce.auth_modal')
