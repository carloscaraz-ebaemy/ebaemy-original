{{-- THEME DEPORTES — Footer estilo Nike: negro, bold --}}
@php
    $company = \App\Models\Tenant\Company::first();
    $econfig = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
    $configModel = \App\Models\Tenant\Configuration::firstCached();
    $showWa = ($configModel->enable_whatsapp ?? false) && !empty($econfig->phone_whatsapp);
@endphp

<style>
.sport-footer { background:#000; color:#8a8a8a; padding:3rem 0 0; font-size:13px; }
.sport-footer a { color:#8a8a8a; text-decoration:none; } .sport-footer a:hover { color:#fff; }
.sport-footer__grid { display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:2rem; max-width:1200px; margin:0 auto; padding:0 24px; }
.sport-footer__brand h3 { color:#fff; font-size:20px; font-weight:900; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.5rem; }
.sport-footer__title { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.12em; color:#fff; margin-bottom:.75rem; }
.sport-footer__links { list-style:none; padding:0; margin:0; } .sport-footer__links li { margin-bottom:.4rem; }
.sport-footer__social { display:flex; gap:.75rem; margin-top:1rem; }
.sport-footer__social a { width:34px; height:34px; border:1px solid #333; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#666; transition:all .18s; }
.sport-footer__social a:hover { border-color:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); color:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); }
.sport-footer__bottom { border-top:1px solid #1a1a1a; margin-top:2.5rem; padding:1.25rem 24px; display:flex; justify-content:space-between; align-items:center; max-width:1200px; margin-left:auto; margin-right:auto; font-size:11px; color:#555; }
@media(max-width:767px) { .sport-footer__grid{grid-template-columns:1fr 1fr;} .sport-footer__bottom{flex-direction:column;gap:.5rem;text-align:center;} }
</style>

<div class="sport-footer">
    <div class="sport-footer__grid">
        <div class="sport-footer__brand">
            <h3>{{ $company->trade_name ?? $company->name ?? 'Tienda' }}</h3>
            <p>{{ $econfig->information_contact_address ?? '' }}</p>
            @if($econfig->information_contact_phone)<p>{{ $econfig->information_contact_phone }}</p>@endif
            <div class="sport-footer__social">
                @if($econfig->link_facebook)<a href="{{ $econfig->link_facebook }}" target="_blank" rel="noopener" title="Facebook"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg></a>@endif
                @if($econfig->link_instagram)<a href="{{ $econfig->link_instagram }}" target="_blank" rel="noopener" title="Instagram"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg></a>@endif
                @if($econfig->link_tiktok)<a href="{{ $econfig->link_tiktok }}" target="_blank" rel="noopener" title="TikTok"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1 0-5.78 2.92 2.92 0 0 1 .88.13V9a6.34 6.34 0 1 0 5.45 6.28V9.41a8.16 8.16 0 0 0 4.77 1.52V7.49a4.85 4.85 0 0 1-1-.8z"/></svg></a>@endif
            </div>
        </div>
        <div><h4 class="sport-footer__title">Tienda</h4><ul class="sport-footer__links">
            <li><a href="{{ route('tenant.ecommerce.index') }}">Catálogo</a></li>
            <li><a href="{{ route('tenant.ecommerce.wishlist') }}">Favoritos</a></li>
            <li><a href="{{ route('tenant_detail_cart') }}">Carrito</a></li>
            <li><a href="{{ route('ecommerce.tracking') }}">Rastreo</a></li>
        </ul></div>
        <div><h4 class="sport-footer__title">Ayuda</h4><ul class="sport-footer__links">
            <li><a href="{{ route('tenant.terminos_condiciones') }}">Términos</a></li>
            <li><a href="{{ route('tenant.cambios_devolucion') }}">Devoluciones</a></li>
            <li><a href="{{ route('tenant.politica_envio') }}">Envíos</a></li>
            <li><a href="{{ route('tenant.politica_privacidad') }}">Privacidad</a></li>
        </ul></div>
        <div><h4 class="sport-footer__title">Cuenta</h4><ul class="sport-footer__links">
            @guest('ecommerce')
            <li><a href="{{ route('tenant_ecommerce_login') }}">Iniciar sesión</a></li>
            <li><a href="{{ route('tenant_ecommerce_login') }}">Registrarse</a></li>
            @else
            <li><a href="{{ route('tenant.ecommerce.profile') }}">Mi perfil</a></li>
            <li><a href="{{ route('tenant_orders') }}">Mis pedidos</a></li>
            @endguest
        </ul></div>
    </div>
    <div class="sport-footer__bottom">
        <span>&copy; {{ date('Y') }} {{ $company->trade_name ?? $company->name ?? '' }}</span>
        <span>Todos los derechos reservados</span>
    </div>
</div>
@if($showWa)
@php $waPhone = preg_replace('/\D+/', '', $econfig->phone_whatsapp); if(strlen($waPhone)==9 && str_starts_with($waPhone,'9')) $waPhone='51'.$waPhone; @endphp
<a href="https://wa.me/{{ $waPhone }}" class="btn-whatsapp-floating" target="_blank" rel="noopener" title="WhatsApp" aria-label="WhatsApp"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg></a>
@endif
<div id="ec-cart-toast-container"></div>
@include('ecommerce::layouts.partials_ecommerce.auth_modal')
