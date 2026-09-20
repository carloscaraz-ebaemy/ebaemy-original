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
           class="ec-catmenu__item {{ $__current ? '' : 'is-active' }}">Todos</a>

        @foreach($__visible as $parent)
            <div class="ec-catmenu__group">
                <a href="{{ route('tenant.ecommerce.index', ['category_id' => $parent->id]) }}"
                   class="ec-catmenu__item {{ (string) $__current === (string) $parent->id ? 'is-active' : '' }}"
                   @if($parent->children_list->count())
                       aria-haspopup="true" aria-expanded="false"
                   @endif>
                    {{ $parent->name }}
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
                    Más categorías
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
</nav>

<style>
.ec-catmenu { border-top: 1px solid rgba(255,255,255,.10); }
.ec-catmenu__inner {
    max-width: 1400px; margin: 0 auto; padding: 0 16px;
    display: flex; align-items: stretch; gap: 2px;
    overflow: visible;
}
.ec-catmenu__group { position: relative; display: flex; }

.ec-catmenu__item {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 11px 13px; font-size: 13.5px; font-weight: 500;
    color: inherit; text-decoration: none; white-space: nowrap;
    background: none; border: 0; cursor: pointer; font-family: inherit;
    border-bottom: 2px solid transparent;
}
.ec-catmenu__item:hover,
.ec-catmenu__group:hover > .ec-catmenu__item { color: inherit; opacity: .78; }
.ec-catmenu__item.is-active { border-bottom-color: currentColor; opacity: 1; font-weight: 700; }
.ec-catmenu__caret { flex: none; opacity: .65; transition: transform .16s ease; }
.ec-catmenu__group:hover .ec-catmenu__caret { transform: rotate(180deg); }

/* El panel es el único sitio donde el menú impone sus propios colores:
   cuelga sobre el contenido de la página, no sobre la barra del header. */
.ec-catmenu__panel {
    position: absolute; top: 100%; left: 0; z-index: 200;
    min-width: 260px; max-width: 460px;
    background: #fff; color: #1f2430;
    border-radius: 0 0 10px 10px;
    box-shadow: 0 14px 38px rgba(0,0,0,.17);
    padding: 8px 0 10px;
    opacity: 0; visibility: hidden; transform: translateY(-6px);
    transition: opacity .15s ease, transform .15s ease, visibility .15s;
}
.ec-catmenu__panel--right { left: auto; right: 0; }
.ec-catmenu__group:hover .ec-catmenu__panel,
.ec-catmenu__group:focus-within .ec-catmenu__panel {
    opacity: 1; visibility: visible; transform: translateY(0);
}

.ec-catmenu__panel-head { padding: 4px 16px 9px; margin-bottom: 4px; border-bottom: 1px solid #eceef2; }
.ec-catmenu__panel-head a { font-size: 13px; font-weight: 700; color: #1f5eff; text-decoration: none; }

.ec-catmenu__list { list-style: none; margin: 0; padding: 0; max-height: 58vh; overflow-y: auto; }
.ec-catmenu__list--cols { column-count: 2; column-gap: 0; }
.ec-catmenu__list a {
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    padding: 8px 16px; font-size: 13.5px; color: #39414f; text-decoration: none;
}
.ec-catmenu__list a:hover { background: #f4f6fb; color: #1f5eff; }
.ec-catmenu__count { font-size: 11.5px; color: #98a0ae; font-variant-numeric: tabular-nums; }

/* Móvil: la fila se vuelve deslizable y los paneles se abren por toque.
   Un mega-menú por hover no existe en un teléfono. */
@media (max-width: 900px) {
    .ec-catmenu__inner { overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
    .ec-catmenu__inner::-webkit-scrollbar { display: none; }
    .ec-catmenu__item { padding: 10px; font-size: 13px; }
    .ec-catmenu__panel {
        position: fixed; left: 0; right: 0; top: auto; bottom: 0; max-width: none;
        border-radius: 14px 14px 0 0; padding-bottom: 20px;
        max-height: 70vh; overflow-y: auto;
        transform: translateY(100%); transition: transform .2s ease, visibility .2s;
    }
    .ec-catmenu__group.is-open .ec-catmenu__panel { opacity: 1; visibility: visible; transform: translateY(0); }
    .ec-catmenu__group:hover .ec-catmenu__panel { opacity: 0; visibility: hidden; transform: translateY(100%); }
    .ec-catmenu__group.is-open:hover .ec-catmenu__panel { opacity: 1; visibility: visible; transform: translateY(0); }
    .ec-catmenu__list--cols { column-count: 1; }
    .ec-catmenu__list a { padding: 12px 18px; font-size: 14.5px; }
}
</style>

<script>
(function () {
    var menu = document.querySelector('.ec-catmenu');
    if (!menu || menu.dataset.ready) { return; }
    menu.dataset.ready = '1';

    var isTouch = window.matchMedia('(max-width: 900px)');

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
            menu.querySelectorAll('.ec-catmenu__group.is-open').forEach(function (g) {
                g.classList.remove('is-open');
            });
            group.classList.add('is-open');
        }
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.ec-catmenu')) {
            menu.querySelectorAll('.ec-catmenu__group.is-open').forEach(function (g) {
                g.classList.remove('is-open');
            });
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            menu.querySelectorAll('.ec-catmenu__group.is-open').forEach(function (g) {
                g.classList.remove('is-open');
            });
        }
    });
})();
</script>
@endif
