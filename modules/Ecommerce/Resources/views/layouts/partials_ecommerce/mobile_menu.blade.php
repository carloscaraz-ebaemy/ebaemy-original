@php
    $tagid            = Request::segment(3);
    $category_segment = strtolower(Request::segment(2));
    $ecomUser         = auth('ecommerce')->user();
@endphp

<div class="mobile-menu-wrapper ec-mmenu">

    {{-- ── Header del menú ──────────────────────────────── --}}
    <div class="ec-mmenu__head">
        <a href="{{ route('tenant.ecommerce.index') }}" class="ec-mmenu__logo">
            @if(isset($information->logo))
                <img src="{{ asset('storage/uploads/logos/'.$information->logo) }}" alt="{{ $information->name ?? 'Logo' }}">
            @else
                <img src="{{ asset('logo/tulogo.png') }}" alt="Logo">
            @endif
        </a>
        <button type="button" class="ec-mmenu__close mobile-menu-close" aria-label="Cerrar menú">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>

    {{-- ── Sección de usuario ───────────────────────────── --}}
    @if($ecomUser)
    <div class="ec-mmenu__user">
        <div class="ec-mmenu__avatar">{{ strtoupper(substr($ecomUser->name, 0, 1)) }}</div>
        <div class="ec-mmenu__user-info">
            <span class="ec-mmenu__user-name">{{ $ecomUser->name }}</span>
            <span class="ec-mmenu__user-email">{{ $ecomUser->email }}</span>
        </div>
    </div>
    @else
    <div class="ec-mmenu__guest">
        <a href="{{ route('tenant_ecommerce_login') }}" class="ec-mmenu__login-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Iniciar sesión
        </a>
        <a href="{{ route('tenant_ecommerce_login') }}#registro" class="ec-mmenu__register-btn">Registrarse</a>
    </div>
    @endif

    {{-- ── Navegación principal ─────────────────────────── --}}
    <nav class="ec-mmenu__nav">
        <ul class="mobile-menu ec-mmenu__list">

            <li class="{{ (!$tagid && $category_segment !== 'category') ? 'ec-mmenu__item--active' : '' }}">
                <a href="{{ route('tenant.ecommerce.index') }}" class="ec-mmenu__link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    <span>Inicio</span>
                </a>
            </li>

            <li class="{{ $category_segment === 'category' ? 'ec-mmenu__item--active' : '' }}">
                <a href="#" class="ec-mmenu__link ec-mmenu__link--has-sub">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                    <span>Categorías</span>
                    <span class="mmenu-btn ec-mmenu__chevron">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </span>
                </a>
                <ul class="ec-mmenu__sub">
                    @foreach ($items as $item)
                    <li class="{{ ($tagid == $item->id) ? 'ec-mmenu__item--active' : '' }}">
                        <a href="{{ route('tenant.ecommerce.category', ['category' => $item->id]) }}" class="ec-mmenu__sub-link">
                            {{ $item->name }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            </li>

            <li>
                <a href="{{ route('tenant_detail_cart') }}" class="ec-mmenu__link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <span>Mi carrito</span>
                </a>
            </li>

            <li>
                <a href="{{ route('tenant.ecommerce.wishlist') }}" class="ec-mmenu__link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                    <span>Favoritos</span>
                </a>
            </li>

            @auth('ecommerce')
            <li>
                <a href="{{ route('tenant.ecommerce.profile') }}" class="ec-mmenu__link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span>Mi cuenta</span>
                </a>
            </li>
            @endauth

            <li>
                <a href="{{ route('ecommerce.tracking') }}" class="ec-mmenu__link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span>Rastrear pedido</span>
                </a>
            </li>

        </ul>
    </nav>

    {{-- ── Footer del menú ──────────────────────────────── --}}
    <div class="ec-mmenu__footer">
        @if(!empty($information->information_contact_phone))
        <a href="https://wa.me/{{ preg_replace('/\D/', '', $information->information_contact_phone) }}"
           target="_blank" rel="noopener" class="ec-mmenu__wa">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
            </svg>
            {{ $information->information_contact_phone }}
        </a>
        @endif

        <div class="ec-mmenu__social">
            @if(!empty($information->link_facebook))
            <a href="{{ $information->link_facebook }}" target="_blank" rel="noopener" class="ec-mmenu__social-link" aria-label="Facebook">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            </a>
            @endif
            @if(!empty($information->link_twitter))
            <a href="{{ $information->link_twitter }}" target="_blank" rel="noopener" class="ec-mmenu__social-link" aria-label="Twitter/X">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.748l7.73-8.835L1.254 2.25H8.08l4.259 5.63 5.905-5.63zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            </a>
            @endif
            @if(!empty($information->link_youtube))
            <a href="{{ $information->link_youtube }}" target="_blank" rel="noopener" class="ec-mmenu__social-link" aria-label="YouTube">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
            </a>
            @endif
        </div>

        @auth('ecommerce')
        <form action="{{ route('tenant_ecommerce_logout') }}" method="POST" style="margin-top:12px">
            @csrf
            <button type="submit" class="ec-mmenu__logout">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Cerrar sesión
            </button>
        </form>
        @endauth
    </div>

</div><!-- End .mobile-menu-wrapper -->

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Hacer que TODA la fila de categorías sea clickeable para expandir el submenú
    document.querySelectorAll('.ec-mmenu__link--has-sub').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var li = link.closest('li');
            var sub = li.querySelector('.ec-mmenu__sub');
            if (!sub) return;
            var isOpen = li.classList.contains('open');
            // Cerrar todos los demás
            document.querySelectorAll('.ec-mmenu__list > li.open').forEach(function (el) {
                if (el !== li) {
                    el.classList.remove('open');
                    var s = el.querySelector('.ec-mmenu__sub');
                    if (s) s.style.display = 'none';
                }
            });
            if (isOpen) {
                li.classList.remove('open');
                sub.style.display = 'none';
            } else {
                li.classList.add('open');
                sub.style.display = 'block';
            }
        });
    });
});
</script>
@endpush
