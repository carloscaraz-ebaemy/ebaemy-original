{{--
    Drawer «Categorías» del marketplace (raíces + hijas). Lo usa el layout:
    en las páginas de una tienda (categorías propias) va dentro de un
    <template>; en el resto lo pide el navegador al primer toque a
    MarketplaceController::categoryMenu (2026-11-19), porque eran ~80 KB y
    ~490 nodos en el HTML de CADA página, ocultos, y su análisis costaba
    ~1,3 s de CPU en un móvil medio.

    Vars: $marketplaceNavCategories, $navScopedToSubdomain (opcional)
--}}
@php
    $isScoped = !empty($navScopedToSubdomain ?? null);
    $scopedBase = $isScoped ? route('marketplace.tenant', ['subdomain' => $navScopedToSubdomain]) : null;
@endphp
<div id="mpMegaPanel" class="mp-cat-drawer" role="dialog" aria-label="Categorías" aria-hidden="true" hidden>
    <div class="mp-cat-drawer__panel">
        {{-- Header del drawer --}}
        <div class="mp-cat-drawer__head">
            {{-- Mobile: boton volver cuando estamos en vista de children --}}
            <button type="button" class="mp-cat-drawer__back" id="mpCatBack" aria-label="Volver" style="display:none">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <h3 class="mp-cat-drawer__title" id="mpCatTitle">Categorías</h3>
            <button type="button" class="mp-cat-drawer__close" aria-label="Cerrar" data-mega-close>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        {{-- 2 paneles: roots (izquierda) y children (derecha).
             En mobile se ve uno a la vez, en desktop ambos. --}}
        <div class="mp-cat-drawer__panes" id="mpCatPanes">
            <ul class="mp-cat-drawer__roots" id="mpCatRoots">
                @foreach($marketplaceNavCategories as $root)
                    @php
                        $rootHref = $isScoped
                            ? $scopedBase . '?category=' . urlencode($root->full_slug)
                            : route('marketplace.category_official', ['fullSlug' => $root->full_slug]);
                    @endphp
                    <li>
                        <button type="button"
                                class="mp-cat-drawer__root {{ $loop->first ? 'is-active' : '' }}"
                                data-cat-id="{{ $root->id }}"
                                data-cat-href="{{ $rootHref }}"
                                data-cat-name="{{ $root->name }}">
                            <span class="mp-cat-drawer__root-icon">{{ $root->icon ?: '📦' }}</span>
                            <span class="mp-cat-drawer__root-name">{{ $root->name }}</span>
                            <svg class="mp-cat-drawer__root-chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="mp-cat-drawer__children" id="mpCatChildren">
                @foreach($marketplaceNavCategories as $root)
                    @php
                        $rootHref = $isScoped
                            ? $scopedBase . '?category=' . urlencode($root->full_slug)
                            : route('marketplace.category_official', ['fullSlug' => $root->full_slug]);
                    @endphp
                    <div class="mp-cat-drawer__child-pane {{ $loop->first ? 'is-active' : '' }}"
                         data-cat-id="{{ $root->id }}">
                        <a href="{{ $rootHref }}" class="mp-cat-drawer__child-all">
                            <span style="font-size:16px">{{ $root->icon ?: '📦' }}</span>
                            Ver todo en <strong>{{ $root->name }}</strong>
                        </a>
                        @if($root->children && $root->children->count())
                            <ul class="mp-cat-drawer__child-list">
                                @foreach($root->children as $child)
                                    @php
                                        $childHref = $isScoped
                                            ? $scopedBase . '?category=' . urlencode($child->full_slug)
                                            : route('marketplace.category_official', ['fullSlug' => $child->full_slug]);
                                    @endphp
                                    <li>
                                        <a href="{{ $childHref }}" class="mp-cat-drawer__child">
                                            @if($child->icon)<span class="mp-cat-drawer__child-icon">{{ $child->icon }}</span>@endif
                                            <span>{{ $child->name }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="mp-cat-drawer__backdrop" data-mega-close></div>
</div>
