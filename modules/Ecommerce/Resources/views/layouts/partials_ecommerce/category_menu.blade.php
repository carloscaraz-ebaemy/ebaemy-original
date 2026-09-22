{{--
    Menú de categorías — padre → hijas.

    Un solo partial para los 9 temas. Antes cada header hacía su propia
    consulta (`Category::…->take(10)`) y pintaba una fila plana: en una tienda
    con 63 categorías salían las diez primeras por orden alfabético, que
    resultaban ser «Accesorios de decoración», «Accesorios de flash para
    cámaras»… Ni eran las importantes ni se entendía por qué estaban ahí.

    Hereda el color del header en el que se incruste (`currentColor` y las
    variables `--theme-*`), así que no pelea con la paleta de cada tema.

    Variables opcionales:
      $categoryTree  árbol ya calculado por el controlador
      $menuLimit     cuántos padres se ven antes del «Más categorías»
--}}
@php
    use Illuminate\Support\Str;
    use Modules\Item\Models\Category;

    $__tree = $categoryTree
        ?? \Illuminate\Support\Facades\Cache::remember(
            'ec_' . (app(\Hyn\Tenancy\Environment::class)->tenant()?->uuid ?? 'default') . '_category_tree_v1',
            1800,
            fn () => Category::tree(true, true)
        );

    $__limit   = $menuLimit ?? 6;
    $__visible = $__tree->take($__limit);
    $__rest    = $__tree->slice($__limit);
    $__current = request('category_id');
@endphp

@if($__tree->count())
<nav class="ec-catmenu" aria-label="Categorías de productos">
    <div class="ec-catmenu__inner">

        <a href="{{ route('tenant.ecommerce.index') }}"
           class="ec-catmenu__item {{ $__current ? '' : 'is-active' }}"><span class="ec-catmenu__label">Todos</span></a>

        @foreach($__visible as $parent)
            <div class="ec-catmenu__group">
                <a href="{{ route('tenant.ecommerce.index', ['category_id' => $parent->id]) }}"
                   class="ec-catmenu__item {{ (string) $__current === (string) $parent->id ? 'is-active' : '' }}"
                   @if($parent->children_list->count())
                       aria-haspopup="true" aria-expanded="false"
                   @endif>
                    <span class="ec-catmenu__label">{{ $parent->name }}</span>
                    @if($parent->children_list->count())
                        <svg class="ec-catmenu__caret" width="11" height="11" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    @endif
                </a>

                @if($parent->children_list->count())
                    <div class="ec-catmenu__panel" role="menu">
                        <div class="ec-catmenu__panel-head">
                            <a href="{{ route('tenant.ecommerce.index', ['category_id' => $parent->id]) }}">
                                Ver todo en {{ $parent->name }}
                                <span class="ec-catmenu__count">{{ $parent->items_count }}</span>
                            </a>
                        </div>
                        <ul class="ec-catmenu__list">
                            @foreach($parent->children_list as $child)
                                <li>
                                    <a href="{{ route('tenant.ecommerce.index', ['category_id' => $child->id]) }}" role="menuitem">
                                        {{ $child->name }}
                                        <span class="ec-catmenu__count">{{ $child->items_count }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endforeach

        {{-- El resto de grupos no desaparece: vive detrás de un solo botón. --}}
        @if($__rest->count())
            <div class="ec-catmenu__group">
                <button type="button" class="ec-catmenu__item ec-catmenu__item--more"
                        aria-haspopup="true" aria-expanded="false">
                    <span class="ec-catmenu__label">Más categorías</span>
                    <svg class="ec-catmenu__caret" width="11" height="11" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </button>
                <div class="ec-catmenu__panel ec-catmenu__panel--right" role="menu">
                    <ul class="ec-catmenu__list ec-catmenu__list--cols">
                        @foreach($__rest as $parent)
                            <li>
                                <a href="{{ route('tenant.ecommerce.index', ['category_id' => $parent->id]) }}" role="menuitem">
                                    {{ $parent->name }}
                                    <span class="ec-catmenu__count">{{ $parent->items_count }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>
    <div class="ec-catmenu__scrim" aria-hidden="true"></div>
</nav>

<style>
/* ── Barra de categorías ──────────────────────────────────────────
   Hereda el color del header (currentColor). El único sitio donde el
   menú impone colores propios es el panel: cuelga sobre el contenido
   de la página, no sobre la barra. */
.ec-catmenu {
    position: relative;
    z-index: 900;                      /* por encima de .ec-filter-sticky-zone */
    border-top: 1px solid rgba(255,255,255,.10);

    /* En el header heredado de Porto esta barra es hija de un
       `.container.d-flex` de 960px, y un elemento flex NO baja de la anchura
       de su contenido mientras conserve `min-width:auto`. Con muchas
       categorias la barra crecia hasta 1195px dentro de un padre de 960 y se
       llevaba por delante el ancho de la PAGINA: scroll horizontal en todo el
       sitio, no solo en el menu. Medido a 1024px: el documento sobraba 218px. */
    min-width: 0;
}
.ec-catmenu__inner {
    /* Mismos escalones que .ec-shop en el listado: si el menú y la
       grilla no comparten ancho, los productos arrancan más a la
       izquierda que la navegación que los filtra. */
    max-width: 1340px; margin: 0 auto; padding: 0 20px;
    display: flex; align-items: stretch; gap: 2px;
    overflow: visible;
}
@media (min-width: 1700px) { .ec-catmenu__inner { max-width: 1600px; } }
/* La misma regla de `min-width:auto`, un nivel mas abajo: sin esto los items
   no ceden ni un pixel y la fila desborda entre 901px --donde termina el
   scroll horizontal de movil-- y el ancho en que las categorias ya caben. Esa
   franja es justo la de tablet apaisada y portatil pequeno.

   Se eligio que los items ENCOJAN y no que la fila haga scroll: `overflow-x`
   recorta los paneles desplegables --se comprobo, el mega-menu deja de verse--
   porque cuelgan de esta misma caja. */
.ec-catmenu__inner { min-width: 0; }
.ec-catmenu__group { position: relative; display: flex; min-width: 0; }

/* El acceso al resto de categorias es la salida para todo lo que no cabe, asi
   que cede el ultimo y nunca por debajo de lo legible. Con `flex:none` a secas
   quedaba un resto de 6px de desbordamiento cuando los demas items ya estaban
   en su minimo: era el unico que no daba nada. */
.ec-catmenu__item--more { flex: 0 1 auto; min-width: 5.5rem; }

/* El nombre se recorta con puntos suspensivos en vez de cortarse en seco. */
.ec-catmenu__label {
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0;
}

.ec-catmenu__item {
    display: inline-flex; align-items: center; gap: 6px;
    min-width: 0;
    padding: 7px 11px; font-size: 13px; font-weight: 500;
    color: inherit; text-decoration: none; white-space: nowrap;
    background: none; border: 0; cursor: pointer; font-family: inherit;
    border-bottom: 2px solid transparent;
    border-radius: 6px 6px 0 0;
    transition: background-color .14s ease, opacity .14s ease;
}
/* Sin opacidad en el texto: cambia el fondo, que se lee mejor y no
   depende de si el header es claro u oscuro. */
.ec-catmenu__item:hover,
.ec-catmenu__group:hover > .ec-catmenu__item,
.ec-catmenu__group:focus-within > .ec-catmenu__item {
    color: inherit;
    background-color: rgba(128,128,128,.14);  /* fallback si no hay color-mix */
    background-color: color-mix(in srgb, currentColor 11%, transparent);
}
.ec-catmenu__item:focus-visible {
    outline: 2px solid currentColor; outline-offset: -3px;
}
.ec-catmenu__item.is-active {
    border-bottom-color: currentColor; font-weight: 700;
    background-color: rgba(128,128,128,.10);
    background-color: color-mix(in srgb, currentColor 8%, transparent);
}
.ec-catmenu__caret { flex: none; opacity: .6; transition: transform .16s ease; }
.ec-catmenu__group:hover .ec-catmenu__caret,
.ec-catmenu__group:focus-within .ec-catmenu__caret { transform: rotate(180deg); }

/* ── Panel ─────────────────────────────────────────────────────────
   Capa independiente: fondo 100% opaco SIEMPRE. Antes el panel se
   revelaba con una transición de opacity, así que durante el fade
   (y en cualquier captura o equipo lento) se leía el contenido de la
   página a través de él. Ahora solo se anima el desplazamiento: el
   fondo nunca es traslúcido en ningún fotograma. */
.ec-catmenu__panel {
    position: absolute; top: 100%; left: 0;
    z-index: 1200;
    min-width: 258px; max-width: 460px;
    background-color: #fff;            /* opaco, sin alpha ni blur */
    color: #1f2430;
    border: 1px solid #e5e9f0;
    border-top: 0;
    border-radius: 0 0 12px 12px;
    box-shadow: 0 16px 34px -8px rgba(15,23,42,.22), 0 2px 6px rgba(15,23,42,.06);
    padding: 8px 0 10px;
    visibility: hidden;
    transform: translateY(-6px);
    transition: transform .15s ease, visibility 0s linear .15s;
}
.ec-catmenu__panel--right { left: auto; right: 0; }
.ec-catmenu__group:hover .ec-catmenu__panel,
.ec-catmenu__group:focus-within .ec-catmenu__panel {
    visibility: visible;
    transform: translateY(0);
    transition: transform .15s ease, visibility 0s;
}

.ec-catmenu__panel-head {
    padding: 2px 18px 8px; margin-bottom: 5px;
    border-bottom: 1px solid #eef1f6;
}
.ec-catmenu__panel-head a {
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    font-size: 13px; font-weight: 700; text-decoration: none;
    color: hsl(var(--primary-h, 210), var(--primary-s, 90%), 38%);
}
.ec-catmenu__panel-head a:hover { text-decoration: underline; }

.ec-catmenu__list {
    list-style: none; margin: 0; padding: 0;
    max-height: 58vh; overflow-y: auto; overscroll-behavior: contain;
}
.ec-catmenu__list--cols { column-count: 2; column-gap: 0; }
.ec-catmenu__list a {
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    padding: 7px 18px; font-size: 13px; color: #39414f; text-decoration: none;
    break-inside: avoid;
}
.ec-catmenu__list a:hover,
.ec-catmenu__list a:focus-visible {
    background: #f4f6fb;
    color: hsl(var(--primary-h, 210), var(--primary-s, 90%), 38%);
    outline: none;
}
.ec-catmenu__count { font-size: 11.5px; color: #98a0ae; font-variant-numeric: tabular-nums; }

/* Modo oscuro: el panel sigue siendo una superficie opaca. */
[data-theme="dark"] .ec-catmenu__panel {
    background-color: #1e293b; color: #e2e8f0; border-color: #334155;
    box-shadow: 0 16px 34px -8px rgba(0,0,0,.6);
}
[data-theme="dark"] .ec-catmenu__panel-head { border-bottom-color: #334155; }
[data-theme="dark"] .ec-catmenu__list a { color: #cbd5e1; }
[data-theme="dark"] .ec-catmenu__list a:hover { background: #0f172a; }

/* Móvil: la fila se vuelve deslizable y los paneles se abren por toque.
   Un mega-menú por hover no existe en un teléfono. */
@media (max-width: 900px) {
    .ec-catmenu__inner { overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
    .ec-catmenu__inner::-webkit-scrollbar { display: none; }
    .ec-catmenu__item { padding: 9px 10px; font-size: 13px; }
    .ec-catmenu__panel {
        position: fixed; left: 0; right: 0; top: auto; bottom: 0; max-width: none;
        border: 0; border-radius: 16px 16px 0 0; padding-bottom: calc(20px + env(safe-area-inset-bottom));
        max-height: 70vh; overflow-y: auto;
        transform: translateY(100%); transition: transform .22s ease, visibility 0s linear .22s;
    }
    .ec-catmenu__group.is-open .ec-catmenu__panel {
        visibility: visible; transform: translateY(0); transition: transform .22s ease, visibility 0s;
    }
    .ec-catmenu__group:hover .ec-catmenu__panel,
    .ec-catmenu__group:focus-within .ec-catmenu__panel {
        visibility: hidden; transform: translateY(100%);
    }
    .ec-catmenu__group.is-open:hover .ec-catmenu__panel,
    .ec-catmenu__group.is-open:focus-within .ec-catmenu__panel {
        visibility: visible; transform: translateY(0);
    }
    .ec-catmenu__list--cols { column-count: 1; }
    .ec-catmenu__list a { padding: 13px 18px; font-size: 14.5px; }
}

/* Fondo que oscurece la página detrás de la hoja en móvil: deja claro
   que el menú es una capa y no parte del listado. */
.ec-catmenu__scrim { display: none; }
@media (max-width: 900px) {
    .ec-catmenu__scrim {
        display: block; position: fixed; inset: 0; z-index: 1199;
        background: rgba(15,23,42,.45);
        opacity: 0; pointer-events: none; transition: opacity .22s ease;
    }
    .ec-catmenu.has-open .ec-catmenu__scrim { opacity: 1; pointer-events: auto; }
}
</style>

<script>
(function () {
    var menu = document.querySelector('.ec-catmenu');
    if (!menu || menu.dataset.ready) { return; }
    menu.dataset.ready = '1';

    var isTouch = window.matchMedia('(max-width: 900px)');

    function setExpanded(group, state) {
        var trigger = group.querySelector('.ec-catmenu__item');
        if (trigger && trigger.hasAttribute('aria-haspopup')) {
            trigger.setAttribute('aria-expanded', state ? 'true' : 'false');
        }
    }

    function closeAll() {
        menu.querySelectorAll('.ec-catmenu__group.is-open').forEach(function (g) {
            g.classList.remove('is-open');
            setExpanded(g, false);
        });
        menu.classList.remove('has-open');
    }

    /* Escritorio: el panel se abre por CSS (:hover / :focus-within); aquí
       solo se mantiene aria-expanded al día para los lectores de pantalla. */
    menu.querySelectorAll('.ec-catmenu__group').forEach(function (group) {
        if (!group.querySelector('.ec-catmenu__panel')) { return; }
        group.addEventListener('mouseenter', function () {
            if (!isTouch.matches) { setExpanded(group, true); }
        });
        group.addEventListener('mouseleave', function () {
            if (!isTouch.matches) { setExpanded(group, false); }
        });
        group.addEventListener('focusin',  function () { setExpanded(group, true); });
        group.addEventListener('focusout', function () {
            if (!group.contains(document.activeElement)) { setExpanded(group, false); }
        });
    });

    /* En móvil el primer toque sobre un grupo abre su panel en vez de
       navegar; el enlace «Ver todo en …» de dentro es el que navega. */
    menu.addEventListener('click', function (e) {
        if (!isTouch.matches) { return; }

        var trigger = e.target.closest('.ec-catmenu__item');
        if (!trigger) { return; }

        var group = trigger.closest('.ec-catmenu__group');
        if (!group || !group.querySelector('.ec-catmenu__panel')) { return; }

        if (!group.classList.contains('is-open')) {
            e.preventDefault();
            closeAll();
            group.classList.add('is-open');
            setExpanded(group, true);
            menu.classList.add('has-open');
        }
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.ec-catmenu') || e.target.closest('.ec-catmenu__scrim')) {
            closeAll();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeAll(); }
    });
})();
</script>
@endif
