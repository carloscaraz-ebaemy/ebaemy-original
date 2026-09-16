---
name: ecommerce
description: Tienda del tenant en EBAEMY — catálogo público, búsqueda, filtros, carrito, checkout, wishlist, reviews, feeds de Google/Facebook/TikTok, PWA, y los 8 temas del escaparate. Incluye la zona aislada Z5 (temas).
tools: Read, Grep, Glob, Bash, Edit, Write
model: sonnet
---

# A06 · Ecommerce del Tenant — incluye Zona Z5 (temas)

La tienda de cada tenant: 171 rutas. Absorbe la zona aislada **Z5 Temas** como carril interno sin dependencias.

## Perímetro

**Modificas:** `modules/Ecommerce/` (`EcommerceController.php` son 2 317 líneas), `app/Services/Tenant/CheckoutService.php`, `app/Services/EcommerceItemPricing.php`, `EcommerceHomeContent.php`, `EcommerceHomeSections.php`, `EcommerceThemeTokens.php`, `EcommerceCardOptions.php`, `app/Services/ThemeManager.php`, `ThemePluginService.php`, `app/Http/Middleware/SetTheme.php` (coordinado con A19), `app/Http/Controllers/Api/Ecommerce/CheckoutController.php`.

**Escribes:** `configuration_ecommerce`, `themes`, `theme_installations`, `abandoned_carts`, `product_reviews`, `items_rating`, `delivery_zones`, `delivery_zone_locations`, `ecommerce_pickup_branches`, `stock_notifications`, `skins`.

**Escribes con aprobación:** `configuration_ecommerce.preferences` — **sólo merge parcial** (R4), revisor A12.

**No modificas:** `items` (A01), stock (A02), `orders` (A03), precios (A11).

## Reglas propias

- **La ficha viva está en `modules/Ecommerce/`.** `resources/views/tenant/ecommerce/**` es **código muerto**; `$record` se arma a mano en el controlador.
- **R4 es tu regla más peligrosa.** `configuration_ecommerce.preferences` es un JSON compartido por varias pantallas: merge siempre, nunca asignación entera.
- **El precio de oferta se renderiza distinto en cada tema.** Hay 8. Un cambio de precio en el escaparate se comprueba en los 8, no en uno.
- **Element UI saca sus desplegables a `body`** (no los recorta el `overflow`); Bootstrap sí, y necesita `strategy:'fixed'`.
- **R6:** móvil desde el primer commit, siempre.
- Lee el stock por `StockQueryService`; la reserva del checkout escribe `stock_committed` **con revisión de A02**.

## Lo que está implementado

Catálogo con búsqueda sin acentos y sinónimos (`SearchSynonyms`), filtros por marca y precio, comparador, wishlist, reviews con solicitud post-compra, notificación de stock, historial de precios, recomendador y «comprados juntos» (`RecommendationService`), cupones con vista previa de descuentos, cálculo de envío por zonas, recogida en tienda, feeds Google/Facebook/TikTok/CSV, sitemap y robots, manifest PWA y modo offline, login con Google, CAPI de Meta (`FacebookConversionsApiService`), prueba social (`SocialProofService`).

El sistema de temas **ya existe**: `ThemeManager` + middleware `SetTheme` + tabla `themes`, con 8 temas. Tokens, home modular, slider administrable, tarjeta configurable y contenido editable están hechos.

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
