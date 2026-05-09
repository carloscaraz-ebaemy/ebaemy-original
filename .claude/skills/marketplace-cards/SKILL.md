---
name: marketplace-cards
description: Cómo modificar las cards del marketplace ebaemy.com manteniendo consistencia entre las 4 vistas que las muestran (home, categoría oficial, categoría legacy, página por tienda). Invocar cuando el usuario pida tocar la card del listado, agregar info nueva (badges, dots, thumbs, hover), o cuando se reporte que un producto se ve distinto en /marketplace vs otra página del marketplace.
---

# Cards del marketplace ebaemy

## Vistas que renderizan cards

Hay **4 vistas** que muestran grids de productos, y todas usan el MISMO partial:

| Vista                     | URL                              | Controller action |
|---------------------------|----------------------------------|-------------------|
| Home + listado general    | `/marketplace`                   | `index()`         |
| Categoría oficial         | `/marketplace/c/{full_slug}`     | `categoryOfficial()` |
| Categoría legacy (string) | `/marketplace/categoria/{slug}`  | `category()`      |
| Página por tienda         | `/marketplace/tienda/{subdomain}`| `tenantPage()`    |

## Archivos que importan

```
resources/views/marketplace/
├── partials/
│   ├── listing-card.blade.php          ← markup de la card (única fuente)
│   ├── listing-card-styles.blade.php   ← CSS de card/grid/paginador
│   └── listing-card-script.blade.php   ← JS (hover dots + shop link click)
├── layout.blade.php                    ← incluye styles en <head> + script al final
├── index.blade.php                     ← @include('marketplace.partials.listing-card', [...])
├── category.blade.php                  ← idem
├── category_official.blade.php         ← idem
└── tenant.blade.php                    ← idem

app/Http/Controllers/MarketplaceController.php
└── decorateListingsWithVariantData($listings)  ← invocar en TODA acción que liste productos
```

## Reglas duras

❌ **NUNCA** edites el bloque `<a class="mp-card">` inline de un blade — siempre va vía `@include('marketplace.partials.listing-card', ['listing' => $listing])`. Si lo encuentras inline en algún blade, refactorízalo al partial.

❌ **NUNCA** dupliques el JS de hover de dots o shop-link en una vista — vive en `listing-card-script` y el layout lo carga.

❌ **NUNCA** pongas CSS de `.mp-card`, `.mp-grid`, `.mp-card-*` o `.mp-pag` inline en una vista — todo eso vive en `listing-card-styles` cargado por el layout. Si lo pones inline, las otras vistas se quedan sin estilo y la card sale rota (imagen mal escalada, dots invisibles, etc.).

❌ **NUNCA** crees una acción nueva que paginate listings sin invocar `decorateListingsWithVariantData($listings)`. Si no, las cards salen sin color dots, sin imagen primaria heredada, sin variant thumbs — el bug "este producto se ve diferente aquí".

✅ **SIEMPRE** que agregues data nueva a la card (un campo, un badge, etc.):
   1. Modifica `partials/listing-card.blade.php` SOLO.
   2. Si el dato necesita query, agrégalo a `decorateListingsWithVariantData()` para que llegue a las 4 vistas.
   3. Si necesita JS, agrégalo a `partials/listing-card-script.blade.php`.
   4. Verifica en las 4 URLs (home, /c/X, /categoria/X, /tienda/X) que se ve igual.

## Variables que la card consume del $listing

Las setea `decorateListingsWithVariantData()`:

| Atributo                        | Origen                                    | Uso en la card |
|---------------------------------|-------------------------------------------|----------------|
| `primary_image_url`             | variante `is_primary=1` o fallback        | imagen principal de la card |
| `secondary_image_url`           | columna en `marketplace_listings`         | imagen alternativa al hover |
| `color_dots` (collection)       | option_values con color_hex + stock>0     | círculos de color (estilo Falabella) |
| `variant_thumbs` (collection)   | variantes con image_url, sin opción color | thumbs cuadrados con foto |
| `active_color_hex` / `_value`   | color de la variante is_primary            | dot resaltado por defecto |

Cada `$cd` de `color_dots` tiene: `value`, `color_hex`, `image_url` (de la variante asociada).

## Flujo de variante "principal"

1. Tenant: el seller marca una variante como principal (`item_variants.is_primary=1`) en el form (radio en Productos > Variantes).
2. Backend: `ItemVariantController::setPrimary` → exclusivo por item en transacción + `triggerMarketplaceSync($item)`.
3. Sync: `MarketplaceListingSyncService::syncVariants` propaga el flag a `marketplace_listing_variants.is_primary`.
4. Render: `decorateListingsWithVariantData` lee, ordena por `is_primary DESC, stock>0 DESC, id ASC`, expone `primary_image_url` + `active_color_hex`.
5. Blade: `partials/listing-card` usa `$listing->primary_image_url ?? $listing->image_url` y marca el dot `is-active` cuando `cd.color_hex == listing.active_color_hex`.
6. JS: hover en otro dot mueve `is-active` (sticky, no restaura al mouseleave).

## Filtro por tienda (sidebar)

- `?shop=<subdomain>` filtra el grid del home — el subdomain es la primera parte del FQDN del tenant (`alasitas.ebaemy.com` → `alasitas`).
- El listado de tiendas viene de `MarketplaceListing::published()->groupBy('tenant_fqdn', 'tenant_name')` (cache 30 min, key `mp_shops_top_v1`).
- Click en el nombre de la tienda en una card navega a `/marketplace/tienda/{subdomain}` vía `js-shop-link` (data-href + stopPropagation, porque la card es `<a>`).
- Click en una tienda del sidebar limpia `q` automáticamente (el seller suele teclear el nombre y luego clickear; si preservas q queda doble filtro y 0 resultados).

## Cosas que se vienen / pendientes

- Página `/marketplace/tienda/{subdomain}` — su own controller también está usando el partial pero no expone los chips de categoría como sidebar. Si se agrega filtro por categoría dentro de la tienda, replicar el patrón del sidebar de home.
- Detalle del producto `/marketplace/p/{slug}` — todavía NO tiene selector de variantes con hover-image como Falabella; al click en un dot del listado se navega al detalle y el detalle carga la imagen del padre. Pendiente: aceptar `?variant=X` y montar el selector visual.

## Cuándo invocar este skill

- "Las cards se ven distintas en X y Y"
- "Quiero agregar Z a las cards del marketplace"
- "El color dot no aparece en /marketplace/c/{algo}"
- Hay que tocar `partials/listing-card.blade.php` o `MarketplaceController::decorateListingsWithVariantData`
- Se agrega una acción nueva en `MarketplaceController` que paginate listings

## Cuándo NO invocar

- Cambios al detalle del producto (`marketplace.show`) — ese es otro flujo
- Cambios al layout/header/footer del marketplace
- Backend tenant (catálogo, items_ecommerce form) — esos no tocan las cards
