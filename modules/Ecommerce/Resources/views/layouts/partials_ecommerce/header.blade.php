
<style>
#header_bar .header-menu {
    max-height: 300px !important;
    overflow:auto;
    overflow-y: auto;
}

#header_bar .header-menu::-webkit-scrollbar-track {
    -webkit-box-shadow: inset 0 0 6px rgba(0,0,0,0.1);
    background-color: #fdfdfd;
}

#header_bar .header-menu::-webkit-scrollbar {
    width: 6px;
    background-color: #fdfdfd;
}

#header_bar .header-menu::-webkit-scrollbar-thumb {
    background-color: #0187cc;
}

.header-dropdown a img {
    border-radius: 8px;
    padding: 4px;
}


.header-menu ul a {
    padding: 3px 6px;
}

.header-menu {
    box-shadow: 0 0 2px rgba(0,0,0,0.1);
    padding: 0 !important;
    border: none;
}

.header-menu a:hover, .header-menu a:focus {
    color: #0187cc;
    background-color: #f4f4f4;
}

.header-menu ul a {
    text-transform: capitalize !important;
}

/* Fix #4: search bar adaptable según viewport */
.ec-search-wrap {
    min-width: 400px;
    flex: 1;
    max-width: 600px;
}
@media (min-width: 992px) and (max-width: 1199px) {
    .ec-search-wrap { min-width: 260px; }
}
@media (min-width: 1200px) {
    .ec-search-wrap { min-width: 460px; }
}

.search_input {
    margin-bottom: 0.1rem;
    border-radius: 20px !important;
}

.search_input:focus {
    background-color: #fff;
    border-color: #fff;
    box-shadow: none;
}

.header-contact span {
    font-weight: normal;
}

div.cart-dropdown {
    display: flex;
    justify-content: center;
    align-items: center;
    background-color: transparent;
}

.header .dropdown-toggle {
    color: #fff;
    font-size: 10px;
    background-color: #1f1f39;
    height: 35px;
    display: flex;
    justify-content: center;
    align-items: center;
    border-radius: 20px;
    padding: 0 10px;
}

/* cart-count handled as absolute badge in cart_dropdown partial */

.search_input:focus {
    border: 1px solid var(--background-color) !important;
    background-color: transparent !important;
}

.search_input {
    width: 100%;
    height: 38px !important;
    border-radius: 20px !important;
    background-color: #eff0f6 !important;
}

.header-dropdown-inside {
    position: relative; 
}

.header-dropdown-inside .search-icon {
    position: absolute;
    left: 10px; 
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.header-dropdown-inside .search_input {
    padding-left: 40px !important; 
    padding-right: 40px !important;
    width: 100%;
}

.header-dropdown-inside .clear-icon {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    cursor: pointer;
    display: none;
}
.header-dropdown-inside input:focus + .clear-icon,
.header-dropdown-inside input:not(:placeholder-shown) + .clear-icon {
    display: inline-block; /* Muestra el ícono */
}
/* -------------------------------------------------*/


 #header_bar .mobile-search-btn { display: none; }

@media (max-width: 768px) {
    .session-text { display: none; }
    .header-contact img { width: 25px !important; height: 25px !important; }
    .header-dropdown { min-width: 100px !important; }
}
@media (max-width: 991px) {
    #header_bar .web-search-btn { display: none; }
    #header_bar .mobile-search-btn { display: flex; align-items: center; justify-content: center; cursor: pointer; }
    .header-contact-info-text { display: none !important; }
}

/* ═══════════════════════════════════════════════════
   MOBILE SEARCH OVERLAY — full-screen profesional
   ═══════════════════════════════════════════════════ */
#ec-mob-search {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 10000;
    flex-direction: column;
    background: #fff;
}
[data-theme="dark"] #ec-mob-search { background: #1e293b; }

#ec-mob-search.ec-mob-search--open {
    display: flex;
    animation: ec-mob-search-in .2s cubic-bezier(.4,0,.2,1);
}
@keyframes ec-mob-search-in {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Top bar ── */
.ec-mob-search__bar {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 10px;
    border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
}
[data-theme="dark"] .ec-mob-search__bar { border-color: #334155; }

.ec-mob-search__back {
    width: 40px; height: 40px;
    display: inline-flex; align-items: center; justify-content: center;
    border: none; background: none; border-radius: 50%;
    cursor: pointer; color: #475569; flex-shrink: 0;
    transition: background .15s;
}
.ec-mob-search__back:hover { background: #f1f5f9; }
[data-theme="dark"] .ec-mob-search__back { color: #94a3b8; }
[data-theme="dark"] .ec-mob-search__back:hover { background: #334155; }

.ec-mob-search__input-wrap {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f1f5f9;
    border-radius: 24px;
    padding: 0 12px 0 14px;
    height: 46px;
    transition: box-shadow .2s, background .15s;
    border: 2px solid transparent;
}
.ec-mob-search__input-wrap:focus-within {
    background: #fff;
    border-color: hsl(var(--primary-h),var(--primary-s),65%);
    box-shadow: 0 0 0 3px hsl(var(--primary-h),var(--primary-s),92%);
}
[data-theme="dark"] .ec-mob-search__input-wrap { background: #334155; }
[data-theme="dark"] .ec-mob-search__input-wrap:focus-within { background: #1e293b; border-color: hsl(var(--primary-h),var(--primary-s),55%); box-shadow: none; }

.ec-mob-search__icon { color: #94a3b8; flex-shrink: 0; }
.ec-mob-search__icon--primary { color: hsl(var(--primary-h),var(--primary-s),45%); }

.ec-mob-search__input {
    flex: 1;
    border: none;
    background: transparent;
    font-size: 1.55rem;
    color: #1e293b;
    outline: none;
    min-width: 0;
}
[data-theme="dark"] .ec-mob-search__input { color: #f1f5f9; }
.ec-mob-search__input::placeholder { color: #94a3b8; }

.ec-mob-search__clear {
    width: 26px; height: 26px;
    display: none;
    align-items: center; justify-content: center;
    border: none; background: #cbd5e1;
    border-radius: 50%; cursor: pointer; color: #fff;
    transition: background .15s; flex-shrink: 0;
}
.ec-mob-search__clear:hover { background: #94a3b8; }
.ec-mob-search__input:not(:placeholder-shown) + .ec-mob-search__clear { display: inline-flex; }

.ec-mob-search__voice {
    width: 36px; height: 36px;
    display: none;
    align-items: center; justify-content: center;
    border: none; background: none; border-radius: 50%;
    cursor: pointer; color: #64748b; flex-shrink: 0;
    transition: background .15s, color .15s;
}
.ec-mob-search__voice:hover { background: #f1f5f9; }
.ec-mob-search__voice.ec-voice-btn--listening { color: #ef4444; animation: ec-pulse 1s ease infinite; }
@keyframes ec-pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

/* ── Results body ── */
.ec-mob-search__body {
    flex: 1;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

/* Result item */
.ec-mob-result {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    text-decoration: none;
    color: inherit;
    transition: background .1s;
    border-bottom: 1px solid #f8fafc;
}
.ec-mob-result:hover, .ec-mob-result:active { background: #f8fafc; }
.ec-mob-result.ec-mob-result--active { background: #f0fdf4; }
[data-theme="dark"] .ec-mob-result:hover { background: #334155; }
[data-theme="dark"] .ec-mob-result { border-color: #1e293b; }

.ec-mob-result__img {
    width: 48px; height: 48px;
    border-radius: 10px;
    object-fit: cover;
    flex-shrink: 0;
    background: #f1f5f9;
}
.ec-mob-result__info { flex: 1; min-width: 0; }
.ec-mob-result__name {
    font-size: 1.35rem; font-weight: 600; color: #1e293b;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;
}
[data-theme="dark"] .ec-mob-result__name { color: #f1f5f9; }
.ec-mob-result__cat { font-size: 1.1rem; color: #94a3b8; display: block; margin-top: 2px; }
.ec-mob-result__price { font-size: 1.35rem; font-weight: 700; color: #1e293b; flex-shrink: 0; white-space: nowrap; }
[data-theme="dark"] .ec-mob-result__price { color: #f1f5f9; }

/* See-all button */
.ec-mob-search__see-all {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    padding: 14px 16px;
    font-size: 1.35rem; font-weight: 600;
    color: hsl(var(--primary-h),var(--primary-s),40%);
    border-top: 2px solid #f1f5f9;
    border-bottom: none; border-left: none; border-right: none;
    width: 100%; background: none; cursor: pointer; text-decoration: none;
    transition: background .12s;
}
.ec-mob-search__see-all:hover { background: #f8fafc; }

/* Empty state */
.ec-mob-search__empty {
    display: flex; flex-direction: column; align-items: center;
    padding: 56px 24px; color: #94a3b8; gap: 12px; text-align: center;
}
.ec-mob-search__empty span { font-size: 1.4rem; line-height: 1.5; }

/* History */
.ec-mob-hist-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 14px 16px 8px;
}
.ec-mob-hist-header span { font-size: 1.1rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .06em; }
.ec-mob-hist-clear { font-size: 1.25rem; color: hsl(var(--primary-h),var(--primary-s),45%); background: none; border: none; cursor: pointer; font-weight: 600; }
.ec-mob-hist-item {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px; border-bottom: 1px solid #f8fafc;
    cursor: pointer; text-decoration: none; color: #1e293b;
    transition: background .1s;
}
.ec-mob-hist-item:hover { background: #f8fafc; }
[data-theme="dark"] .ec-mob-hist-item { color: #f1f5f9; border-color: #334155; }
[data-theme="dark"] .ec-mob-hist-item:hover { background: #334155; }
.ec-mob-hist-icon { color: #cbd5e1; flex-shrink: 0; }
.ec-mob-hist-text { flex: 1; font-size: 1.4rem; }
.ec-mob-hist-del {
    background: none; border: none; cursor: pointer;
    color: #cbd5e1; padding: 4px; border-radius: 4px;
    display: inline-flex; align-items: center; transition: color .15s;
}
.ec-mob-hist-del:hover { color: #94a3b8; }
mark.ec-search-hl { background: hsl(var(--primary-h),90%,88%); color: inherit; border-radius: 2px; padding: 0 1px; }



 </style>

 <header class="header">

     <div class="header-middle">
         <div   class="container">
             <div class="header-left">
                <button class="mobile-menu-toggler text-dark" type="button">
                     <i class="icon-menu"></i>
                 </button>
                 <a href="{{ route("tenant.ecommerce.index") }}" class="logo" style="max-width: 180px">
                    @if(isset($information->logo))
                        <img src="{{ asset('storage/uploads/logos/'.$information->logo) }}" alt="Logo" />
                    @else
                        <img src="{{asset('logo/tulogo.png')}}" alt="Logo" />
                    @endif
                 </a>
             </div><!-- End .header-left -->
             
             
             <div id="header_bar" class="header-center header-dropdowns">

                {{-- Botón de búsqueda mobile (solo < 991px) --}}
                <button type="button" id="ec-mob-search-btn" class="mobile-search-btn" aria-label="Buscar" style="min-width:40px;background:none;border:none;padding:4px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                </button>

                 <div class="web-search-btn header-dropdown header-dropdown-inside ec-search-wrap">
                    <img src="{{ asset('images/search.svg') }}" alt="search" class="search-icon" style="width: 18px; height: 18px;">
                    {{-- <input placeholder="Buscar..." type="text" class="search_input form-control form-control-lg" v-model="value" v-on:keyup="autoComplete" @focus="isFocused = true" @blur="isFocused = false"/> --}}
                     <input placeholder="Buscar..." type="text"
                     class="search_input form-control form-control-lg"
                      v-model="value" @input="autoComplete" @focus="onFocus" @blur="onBlur"
                      @keydown.enter.prevent="selectOrSearch"
                      @keydown.down.prevent="moveDown"
                      @keydown.up.prevent="moveUp"
                      @keydown.esc="clearInput"
                      ref="searchInput" />
                    <img src="{{ asset('images/circle-xmark.svg') }}" alt="Clear" class="clear-icon" @click="clearInput">
                    {{-- Voice search button --}}
                    <button type="button" id="ec-voice-btn" class="ec-voice-btn" aria-label="Buscar por voz" title="Buscar por voz" style="display:none">
                        <svg class="ec-voice-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
                            <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                            <line x1="12" y1="19" x2="12" y2="23"/>
                            <line x1="8" y1="23" x2="16" y2="23"/>
                        </svg>
                    </button>

                    {{-- Dropdown: resultados de búsqueda --}}
                    <div class="header-menu" v-if="value.trim().length > 0">
                        <template v-if="filteredResults.length > 0">
                            <ul>
                                <li v-for="(result, idx) in visibleResults" :key="result.id"
                                    :class="{'ec-search-item--active': idx === activeIndex}"
                                    @mouseenter="activeIndex = idx">
                                    <a :href="'/ecommerce/item/' + result.slug"
                                       class="d-flex align-items-center ec-search-item"
                                       @mousedown.prevent="saveHistory(value); navigate('/ecommerce/item/' + result.slug)">
                                        <img class="ec-search-img" :src="result.image_url_small"
                                             :alt="result.description"
                                             onerror="this.src='/logo/imagen-no-disponible.jpg'">
                                        <div class="ec-search-info">
                                            <span class="ec-search-name" v-html="highlight(result.description)"></span>
                                            <span v-if="result.category" class="ec-search-cat">@{{ result.category }}</span>
                                        </div>
                                        <span class="ec-search-price">@{{ result.sale_unit_price }}</span>
                                    </a>
                                </li>
                            </ul>
                            <div v-if="filteredResults.length > 6" class="ec-search-more">
                                <a :href="'/ecommerce?q=' + encodeURIComponent(value)"
                                   @mousedown.prevent="saveHistory(value); navigate('/ecommerce?q=' + encodeURIComponent(value))">
                                    Ver los @{{ filteredResults.length }} resultados para "<strong>@{{ value }}</strong>"
                                </a>
                            </div>
                        </template>
                        <div v-else class="ec-search-empty">
                            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"
                                 fill="none" stroke="#ccc" stroke-width="1.5" aria-hidden="true">
                                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                            </svg>
                            <span>Sin resultados para "<strong>@{{ value }}</strong>"</span>
                        </div>
                    </div>

                    {{-- Dropdown: historial (input vacío y enfocado) --}}
                    <div class="header-menu ec-search-history-panel" v-if="value.trim().length === 0 && isFocused && searchHistory.length > 0">
                        <div class="ec-search-history-header">
                            <span>Búsquedas recientes</span>
                            <button type="button" class="ec-search-history-clear" @mousedown.prevent="clearHistory">
                                Borrar todo
                            </button>
                        </div>
                        <ul>
                            <li v-for="(term, idx) in searchHistory" :key="idx" class="ec-search-history-item">
                                <a href="#" class="d-flex align-items-center ec-search-item"
                                   @mousedown.prevent="applyHistory(term)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="2" class="ec-search-history-icon" aria-hidden="true">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polyline points="12 6 12 12 16 14"/>
                                    </svg>
                                    <span class="ec-search-name">@{{ term }}</span>
                                </a>
                                <button type="button" class="ec-search-history-remove" aria-label="Eliminar búsqueda"
                                        @mousedown.prevent="removeHistory(idx)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                    </svg>
                                </button>
                            </li>
                        </ul>
                    </div><!-- End historial -->
                 </div><!-- End .header-dropown -->


             </div><!-- End .headeer-center -->

             <div class="header-right">
                
                 
                 <div class="header-contact">
                     <span> Atención al</span>
                     <i class="fab fa-whatsapp"></i> <a href="#"><strong>{{$information->information_contact_phone}}</strong></a>
                 </div><!-- End .header-contact -->
                {{-- Dark mode toggle --}}
                <button type="button" id="ec-theme-toggle" class="ec-theme-toggle" aria-label="Cambiar tema"
                        title="Modo oscuro / claro">
                    <svg class="ec-theme-icon ec-theme-icon--moon" xmlns="http://www.w3.org/2000/svg"
                         width="18" height="18" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                    <svg class="ec-theme-icon ec-theme-icon--sun" xmlns="http://www.w3.org/2000/svg"
                         width="18" height="18" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="5"/>
                        <line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                        <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                    </svg>
                </button>

               {{-- Wishlist icon --}}
                <a href="{{ route('tenant.ecommerce.wishlist') }}"
                   class="ec-header-wishlist"
                   title="Mis favoritos"
                   aria-label="Ver lista de favoritos">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                    <span class="ec-header-wishlist__count" id="ec-wishlist-count" style="display:none">0</span>
                </a>
                @include('ecommerce::layouts.partials_ecommerce.cart_dropdown')
                @include('ecommerce::partials.headers.session')

             </div><!-- End .header-right -->
         </div><!-- End .container -->
     </div><!-- End .header-middle -->

     <div class="header-bottom sticky-header">
        <div class="container d-flex">
            <nav class="main-nav flex-grow-1">

             </nav>
         </div><!-- End .header-bottom -->
     </div><!-- End .header-bottom -->
 </header><!-- End .header -->

{{-- ══ MOBILE SEARCH OVERLAY ══ --}}
<div id="ec-mob-search" role="dialog" aria-modal="true" aria-label="Buscador">
    {{-- Top bar --}}
    <div class="ec-mob-search__bar">
        <button type="button" id="ec-mob-search-close" class="ec-mob-search__back" aria-label="Cerrar búsqueda">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
            </svg>
        </button>
        <div class="ec-mob-search__input-wrap">
            <svg class="ec-mob-search__icon" xmlns="http://www.w3.org/2000/svg" width="17" height="17"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
            <input id="ec-mob-search-input" type="search" class="ec-mob-search__input"
                   placeholder="Buscar productos..." autocomplete="off" enterkeyhint="search">
            <button type="button" class="ec-mob-search__clear" id="ec-mob-search-clear" aria-label="Borrar">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <button type="button" id="ec-mob-voice-btn" class="ec-mob-search__voice" aria-label="Buscar por voz">
            <svg class="ec-voice-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
                <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                <line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/>
            </svg>
        </button>
    </div>

    {{-- Resultados / historial --}}
    <div class="ec-mob-search__body" id="ec-mob-search-body">
        {{-- Llenado por JS --}}
    </div>
</div>

 @push('scripts')
 <script>

document.addEventListener("DOMContentLoaded", function () {

    // ── Mobile Search Overlay ─────────────────────────────────────────────
    var overlay   = document.getElementById('ec-mob-search');
    var openBtn   = document.getElementById('ec-mob-search-btn');
    var closeBtn  = document.getElementById('ec-mob-search-close');
    var input     = document.getElementById('ec-mob-search-input');
    var clearBtn  = document.getElementById('ec-mob-search-clear');
    var body      = document.getElementById('ec-mob-search-body');
    var voiceBtn  = document.getElementById('ec-mob-voice-btn');
    if (!overlay || !openBtn) return;

    var activeIndex = -1;

    function openOverlay() {
        overlay.classList.add('ec-mob-search--open');
        document.body.style.overflow = 'hidden';
        setTimeout(function () { input.focus(); }, 80);
        renderBody('', true); // show history
    }

    function closeOverlay() {
        overlay.classList.remove('ec-mob-search--open');
        document.body.style.overflow = '';
        input.value = '';
        activeIndex = -1;
    }

    openBtn.addEventListener('click', openOverlay);
    closeBtn.addEventListener('click', closeOverlay);
    clearBtn.addEventListener('click', function () {
        input.value = '';
        input.focus();
        activeIndex = -1;
        renderBody('', true);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('ec-mob-search--open')) closeOverlay();
    });

    input.addEventListener('input', function () {
        activeIndex = -1;
        renderBody(input.value.trim(), false);
    });

    input.addEventListener('focus', function () {
        renderBody(input.value.trim(), true);
    });

    input.addEventListener('keydown', function (e) {
        var items = body.querySelectorAll('.ec-mob-result');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = Math.min(activeIndex + 1, items.length - 1);
            highlightItem(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = Math.max(activeIndex - 1, -1);
            highlightItem(items);
        } else if (e.key === 'Enter') {
            if (activeIndex >= 0 && items[activeIndex]) {
                items[activeIndex].click();
            } else {
                var q = input.value.trim();
                if (q) { saveHistory(q); window.location = '/ecommerce?q=' + encodeURIComponent(q); }
            }
        }
    });

    function highlightItem(items) {
        items.forEach(function (el, i) {
            el.classList.toggle('ec-mob-result--active', i === activeIndex);
        });
    }

    // ── Renderizar cuerpo ─────────────────────────────────────────────────
    function renderBody(query, showHistory) {
        var vue = window.headerVue;
        if (!query) {
            // Mostrar historial
            var hist = loadHistory();
            if (hist.length === 0) {
                body.innerHTML = '';
                return;
            }
            var html = '<div class="ec-mob-hist-header">' +
                '<span>Recientes</span>' +
                '<button class="ec-mob-hist-clear" id="ec-mob-hist-clear-btn">Borrar</button>' +
                '</div>';
            hist.forEach(function (term, idx) {
                html += '<div class="ec-mob-hist-item" data-term="' + esc(term) + '">' +
                    '<svg class="ec-mob-hist-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' +
                    '<span class="ec-mob-hist-text">' + esc(term) + '</span>' +
                    '<button class="ec-mob-hist-del" data-idx="' + idx + '" aria-label="Eliminar">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
                    '</button></div>';
            });
            body.innerHTML = html;

            body.querySelector('#ec-mob-hist-clear-btn') &&
                body.querySelector('#ec-mob-hist-clear-btn').addEventListener('click', function () {
                    clearHistory(); body.innerHTML = '';
                });
            body.querySelectorAll('.ec-mob-hist-item').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    if (e.target.closest('.ec-mob-hist-del')) return;
                    var term = el.dataset.term;
                    saveHistory(term);
                    window.location = '/ecommerce?q=' + encodeURIComponent(term);
                });
            });
            body.querySelectorAll('.ec-mob-hist-del').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    removeHistory(parseInt(btn.dataset.idx));
                    renderBody('', true);
                });
            });
            return;
        }

        // Buscar en sugerencias de headerVue
        var suggestions = vue ? vue.suggestions : [];
        var lq = query.toLowerCase();
        var results = suggestions.filter(function (item) {
            return (item.description || '').toLowerCase().includes(lq) ||
                   (item.internal_id || '').toLowerCase().includes(lq) ||
                   (item.category    || '').toLowerCase().includes(lq);
        });

        if (results.length === 0) {
            body.innerHTML = '<div class="ec-mob-search__empty">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#e2e8f0" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>' +
                '<span>Sin resultados para<br><strong>' + esc(query) + '</strong></span></div>';
            return;
        }

        var visible = results.slice(0, 8);
        var html = '';
        visible.forEach(function (item) {
            var imgSrc = item.image_url_small || '/logo/imagen-no-disponible.jpg';
            html += '<a href="/ecommerce/item/' + esc(item.slug) + '" class="ec-mob-result" data-slug="' + esc(item.slug) + '">' +
                '<img class="ec-mob-result__img" src="' + esc(imgSrc) + '" alt="' + esc(item.description) + '" onerror="this.src=\'/logo/imagen-no-disponible.jpg\'">' +
                '<div class="ec-mob-result__info">' +
                '<span class="ec-mob-result__name">' + highlight(item.description, query) + '</span>' +
                (item.category ? '<span class="ec-mob-result__cat">' + esc(item.category) + '</span>' : '') +
                '</div>' +
                '<span class="ec-mob-result__price">S/ ' + esc(String(item.sale_unit_price)) + '</span>' +
                '</a>';
        });

        if (results.length > 8) {
            html += '<a href="/ecommerce?q=' + encodeURIComponent(query) + '" class="ec-mob-search__see-all">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>' +
                'Ver los ' + results.length + ' resultados para <strong>&nbsp;"' + esc(query) + '"</strong>' +
                '</a>';
        }
        body.innerHTML = html;
        body.querySelectorAll('.ec-mob-result').forEach(function (el) {
            el.addEventListener('click', function () { saveHistory(query); });
        });
    }

    // ── Helpers ────────────────────────────────────────────────────────────
    function esc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function highlight(text, q) {
        if (!q) return esc(text);
        var re = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + ')', 'gi');
        return esc(text).replace(re, '<mark class="ec-search-hl">$1</mark>');
    }
    function loadHistory() {
        try { return JSON.parse(localStorage.getItem('ec_search_history')) || []; } catch(e) { return []; }
    }
    function saveHistory(term) {
        if (!term) return;
        var h = loadHistory().filter(function(i){ return i.toLowerCase() !== term.toLowerCase(); });
        h.unshift(term);
        if (h.length > 8) h = h.slice(0, 8);
        try { localStorage.setItem('ec_search_history', JSON.stringify(h)); } catch(e) {}
        if (window.headerVue) window.headerVue.searchHistory = h;
    }
    function removeHistory(idx) {
        var h = loadHistory(); h.splice(idx, 1);
        try { localStorage.setItem('ec_search_history', JSON.stringify(h)); } catch(e) {}
        if (window.headerVue) window.headerVue.searchHistory = h;
    }
    function clearHistory() {
        try { localStorage.removeItem('ec_search_history'); } catch(e) {}
        if (window.headerVue) window.headerVue.searchHistory = [];
    }

    // ── Voice en overlay ──────────────────────────────────────────────────
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SR && voiceBtn) {
        voiceBtn.style.display = 'flex';
        var rec = new SR();
        rec.lang = 'es-PE'; rec.continuous = false; rec.interimResults = true;
        var recListening = false;
        voiceBtn.addEventListener('click', function () {
            if (recListening) { rec.stop(); } else { try { rec.start(); voiceBtn.classList.add('ec-voice-btn--listening'); recListening = true; } catch(e) {} }
        });
        rec.addEventListener('result', function (e) {
            var t = e.results[0][0].transcript;
            input.value = t;
            renderBody(t, false);
            if (e.results[0].isFinal) {
                setTimeout(function () { voiceBtn.classList.remove('ec-voice-btn--listening'); recListening = false; saveHistory(t); window.location = '/ecommerce?q=' + encodeURIComponent(t); }, 700);
            }
        });
        rec.addEventListener('end', function () { voiceBtn.classList.remove('ec-voice-btn--listening'); recListening = false; });
        rec.addEventListener('error', function () { voiceBtn.classList.remove('ec-voice-btn--listening'); recListening = false; });
    }

});


window.headerVue = new Vue({
    el: '#header_bar',

    data: {
        value: '',
        suggestions: [],
        resource: 'ecommerce',
        isFocused: false,
        searchHistory: [],
        activeIndex: -1
    },

    created() {
        this.getItems();
        this.loadHistory();
    },

    computed: {
        filteredResults() {
            if (!this.value.trim()) return [];
            const search = this.value.toLowerCase();
            return this.suggestions.filter(item => {
                const desc = (item.description || '').toLowerCase();
                const code = (item.internal_id || '').toLowerCase();
                const cat  = (item.category    || '').toLowerCase();
                return desc.includes(search) || code.includes(search) || cat.includes(search);
            });
        },
        visibleResults() {
            return this.filteredResults.slice(0, 6);
        }
    },

    methods: {
        getItems() {
            fetch(`/${this.resource}/items_bar`)
                .then(res => res.json())
                .then(data => { this.suggestions = data.data || []; })
                .catch(() => {});
        },

        autoComplete() { this.activeIndex = -1; },

        clearInput() { this.value = ''; this.activeIndex = -1; },

        onFocus() {
            this.isFocused = true;
            // Pre-fill from ?q= param if search bar is empty
            if (!this.value) {
                var qParam = new URLSearchParams(window.location.search).get('q');
                if (qParam) this.value = qParam;
            }
        },

        onBlur() {
            setTimeout(() => { this.isFocused = false; this.activeIndex = -1; }, 150);
        },

        goSearch() {
            const q = this.value.trim();
            if (!q) return;
            this.saveHistory(q);
            window.location = '/ecommerce?q=' + encodeURIComponent(q);
        },

        selectOrSearch() {
            if (this.activeIndex >= 0 && this.activeIndex < this.visibleResults.length) {
                const item = this.visibleResults[this.activeIndex];
                this.saveHistory(this.value);
                window.location = '/ecommerce/item/' + item.slug;
            } else {
                this.goSearch();
            }
        },

        moveDown() {
            if (this.activeIndex < this.visibleResults.length - 1) this.activeIndex++;
        },

        moveUp() {
            if (this.activeIndex > 0) this.activeIndex--;
        },

        highlight(text) {
            if (!this.value.trim()) return this.esc(text);
            const re = new RegExp('(' + this.escRe(this.value.trim()) + ')', 'gi');
            return this.esc(text).replace(re, '<mark class="ec-search-hl">$1</mark>');
        },

        esc(str) {
            return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        },

        escRe(str) {
            return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        },

        navigate(url) { window.location = url; },

        // ── Historial ─────────────────────────────────────────────────────
        loadHistory() {
            try { this.searchHistory = JSON.parse(localStorage.getItem('ec_search_history')) || []; }
            catch(e) { this.searchHistory = []; }
        },

        saveHistory(term) {
            const t = (term || '').trim();
            if (!t) return;
            let h = this.searchHistory.filter(i => i.toLowerCase() !== t.toLowerCase());
            h.unshift(t);
            if (h.length > 8) h = h.slice(0, 8);
            this.searchHistory = h;
            try { localStorage.setItem('ec_search_history', JSON.stringify(h)); } catch(e) {}
        },

        removeHistory(idx) {
            this.searchHistory.splice(idx, 1);
            try { localStorage.setItem('ec_search_history', JSON.stringify(this.searchHistory)); } catch(e) {}
        },

        clearHistory() {
            this.searchHistory = [];
            try { localStorage.removeItem('ec_search_history'); } catch(e) {}
        },

        applyHistory(term) {
            this.value = term;
            this.isFocused = false;
            this.saveHistory(term);
            window.location = '/ecommerce?q=' + encodeURIComponent(term);
        },

        closeMobileSearch() {
            // handled by overlay JS
        }
    }
});

// ── Voice Search ─────────────────────────────────────────────────────────────
(function () {
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) return; // Browser doesn't support — button stays hidden

    var btn       = document.getElementById('ec-voice-btn');
    if (!btn) return;
    btn.style.display = 'flex';

    var recognition = new SR();
    recognition.lang        = 'es-PE';
    recognition.continuous  = false;
    recognition.interimResults = true;
    recognition.maxAlternatives = 1;

    var listening  = false;
    var autoTimer  = null;

    function setListening(val) {
        listening = val;
        btn.classList.toggle('ec-voice-btn--listening', val);
        btn.setAttribute('aria-pressed', val ? 'true' : 'false');
    }

    btn.addEventListener('click', function () {
        if (listening) {
            recognition.stop();
            setListening(false);
        } else {
            try { recognition.start(); setListening(true); }
            catch(e) { /* already started */ }
        }
    });

    recognition.addEventListener('result', function (e) {
        var transcript = e.results[0][0].transcript;
        // Push into Vue
        if (window.headerVue) {
            window.headerVue.value = transcript;
            window.headerVue.autoComplete();
        } else {
            // fallback: set native input value
            var input = document.querySelector('#header_bar .search_input');
            if (input) {
                input.value = transcript;
                input.dispatchEvent(new Event('input'));
            }
        }

        // If final result, auto-search after short pause
        if (e.results[0].isFinal) {
            clearTimeout(autoTimer);
            autoTimer = setTimeout(function () {
                setListening(false);
                if (window.headerVue && transcript.trim()) {
                    window.headerVue.saveHistory(transcript.trim());
                    window.location = '/ecommerce?q=' + encodeURIComponent(transcript.trim());
                }
            }, 800);
        }
    });

    recognition.addEventListener('end', function () { setListening(false); });
    recognition.addEventListener('error', function (e) {
        setListening(false);
        if (e.error === 'no-speech') return; // silently ignore
        console.warn('Voice search error:', e.error);
    });
}());

// ── Dark mode toggle ─────────────────────────────────────────────────────────
(function () {
    function applyTheme(dark) {
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
        var btn = document.getElementById('ec-theme-toggle');
        if (btn) btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
        try { localStorage.setItem('ec_theme', dark ? 'dark' : 'light'); } catch(e) {}
    }

    document.addEventListener('DOMContentLoaded', function () {
        var isDark = localStorage.getItem('ec_theme') === 'dark';
        applyTheme(isDark);

        var btn = document.getElementById('ec-theme-toggle');
        if (btn) {
            btn.addEventListener('click', function () {
                var nowDark = document.documentElement.getAttribute('data-theme') !== 'dark';
                applyTheme(nowDark);
            });
        }
    });
}());
</script>


 @endpush