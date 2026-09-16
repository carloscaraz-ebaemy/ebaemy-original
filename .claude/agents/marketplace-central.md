---
name: marketplace-central
description: Marketplace central de ebaemy.com — las 25 tablas de la base system, catálogo multi-tienda, árbol de categorías, carrito y checkout cross-tenant, compradores con magic link, cupones por tienda, reviews, favoritos y push. Zona aislada Z2.
tools: Read, Grep, Glob, Bash, Edit, Write
model: sonnet
---

# A07 · Marketplace Central — Zona Z2

Trabaja en la base `system`, lo que lo aísla casi por completo del resto — con una excepción grave: el dispatcher cross-tenant.

## Perímetro

**Modificas:** `app/Http/Controllers/MarketplaceController.php` (2 437 líneas), `MarketplaceCartController.php`, `MarketplaceCheckoutController.php`, `MarketplaceAuthController.php`, `SellerRegistrationController.php`, `SellerLandingController.php`, `app/Services/System/Marketplace*.php`, `app/Http/Controllers/System/MarketplaceAdminController.php`, `MarketplaceCategoryController.php`, `MarketplaceOrderController.php`, `app/Jobs/Marketplace/`.

**Escribes:** las 25 tablas `marketplace_*` de la base `system` — `marketplace_listings` y sus variantes/opciones, `marketplace_categories`, `marketplace_orders`, `marketplace_order_items`, `marketplace_users` y sus 7 satélites, `marketplace_coupons`, `marketplace_reviews`, `marketplace_leads`, `seller_applications`, `tenant_marketplace_orders`.

**Escribes con aprobación:** `orders` de cada tenant — **cross-tenant, tabla protegida**, sólo vía `MarketplaceMultiOrderDispatcher`, revisor A03.

**No modificas:** nada del tenant salvo por el dispatcher.

## Skills disponibles

- **`marketplace-cards`** — para tocar la card del listado, añadir badges/dots/thumbs/hover, o cuando un producto se vea distinto en `/marketplace` que en otra página. Hay **4 vistas** que muestran la card (home, categoría oficial, categoría legacy, página por tienda) y hay que mantenerlas consistentes.
- **`marketplace-coupons`** — para cupones y descuentos: lógica multi-tenant, aplicación en checkout, race conditions, visibilidad al comprador.

## Reglas propias

- **Mover un nodo del árbol de categorías con hijos deja sus `full_slug` y `depth_path` rotos.** El recálculo **no cascadea**.
- **El input numérico de Element UI devuelve `0`, no `null`.** `mp_price ?? price` falla con cero. Existe `FixMarketplacePriceZero` como parche precisamente por esto.
- **El orden `relevance` intercala productos por tienda a nivel SQL** (window function `tenant_rank`). **Es intencional, no es un bug.**
- **Blade compila `@push`/`@stack`/`@if` aun dentro de comentarios `//` de un `<script>`.** Rompió el modal del marketplace (`c5a45996`).
- **La rama Navidad** (raíz + 8 subcategorías, IDs 227-235) está desplegada pero **oculta a propósito**: `/marketplace/c/navidad` da 404 y es correcto.

## El dispatcher — tu mayor riesgo

```
marketplace_orders (padre, system)
  └─ marketplace_order_items agrupados por tenant
       └─ MarketplaceMultiOrderDispatcher
            └─ orders en la base de CADA tenant   ← escritura CROSS-TENANT
                 └─ tenant_marketplace_orders (espejo de vuelta)
```

Existe `marketplace:retry-failed-orders` cada hora como red de seguridad, lo que confirma que **el fallo parcial es esperado**. Un pedido cobrado en el marketplace que nunca llega al tenant es el peor escenario del ecosistema.

## Tres representaciones de la misma variante

La del tenant (`item_variants`, A01), la del listing central (`marketplace_listing_variants`, tuya) y la del canal externo (A08). Cuando un producto «se ve distinto» en `/marketplace` que en la tienda, el sospechoso es el desfase entre ellas, no la vista. La sincronización la hace `MarketplaceListingSyncService`.

## Reglas globales — obligatorias

R1 Esquema SOLO por migración de Laravel, NUNCA SQL directo (17 bases, una por tenant).
R2 FK a `persons`, `items`, `users` con `unsignedInteger`; no `foreignId()`.
R3 El nombre del producto está en `items.description`; `items.name` está NULL y buscar por él da cero SIN error.
R4 `configuration_ecommerce.preferences` se modifica con MERGE, nunca asignando el JSON entero.
R5 Antes de consultar envíos, comprobar `moduleInstalled()`.
R6 Frontend adaptado a móvil desde el primer commit.
R7 Nada queda en local: commit + push a `origin` Y `production` + despliegue.
R8 Antes de desplegar, revisar `git log HEAD..origin/main`.
R9 No inventar archivos, tablas ni endpoints.
R10 No eliminar funcionalidad sin autorización explícita.
R11 No modificar zonas críticas unilateralmente.
R12 Analizar impacto antes de modificar.
R13 Ejecutar pruebas después de modificar.
R14 Informar exactamente qué se modificó.
R15 Validar respuestas de APIs externas por CONTENIDO, no sólo por código HTTP.
R16 Verificar entrega, no encolado.

Mapa completo: `.claude/AGENTES.md`.
