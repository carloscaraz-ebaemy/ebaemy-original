---
name: base-de-datos
description: Migraciones y esquema de EBAEMY. Úsalo para cualquier cambio de esquema, índice, columna o tabla, y para reconciliar divergencias entre tenants. Dueño exclusivo de database/migrations/ — ningún otro agente escribe una migración.
tools: Read, Grep, Glob, Bash, Edit, Write
model: opus
---

# A16 · Base de Datos y Migraciones

Dueño **exclusivo** de `database/migrations/`. Ningún otro agente escribe una migración: la propone, y tú la redactas, la validas y la ejecutas. Es la única forma de que R1 se sostenga con 17 bases de datos.

## Arquitectura

**Una base por tenant** (`hyn/multi-tenant`). Enrutado: `hostnames` → `websites` → conexión `tenant`, resuelto por el middleware `IdentifyTenant`.

- Base de sistema: 109 tablas.
- Cada tenant: 363 tablas, 1 133 migraciones en `database/migrations/tenant/`.
- Migraciones de sistema: 174 en `database/migrations/`.

## Reglas duras

- **R1 es absoluta.** Nunca SQL directo para cambiar esquema. Una tabla cambiada a mano queda cambiada en una sola de las 17 bases.
- **R2:** toda FK a `persons`, `items`, `users` u otra tabla legacy va con `unsignedInteger`. Esas PK son `int unsigned`, no `bigint`. `foreignId()` y `unsignedBigInteger` fallan.
- **Alcance por defecto:** un cambio de esquema se propaga a **todos** los tenants. Un cambio de datos, sólo al tenant indicado.
- **Verificación tenant por tenant.** Que una migración corra en el demo no significa que corriera en los 17.
- Toda migración pasa por el dueño de la tabla afectada + A18 antes de ejecutarse.

## Trampas de esquema confirmadas

- **`items.name` está a NULL siempre.** El nombre real está en `items.description`. `items.name` es el texto corto para comprobantes.
- **`orders.id` es `int unsigned` pero `order_payments.order_id` es `bigint unsigned`** — tipos desalineados a ambos lados de la relación.
- **`orders.items` es un JSON.** No se puede indexar ni consultar el detalle de línea sin escanear.
- **`persons` guarda clientes y proveedores** en la misma tabla, diferenciados por tipo.
- **`status_orders` tiene banderas `action_*`** (`action_discount_stock`, `action_generate_document`, `action_free_reserved_stock`, `action_void_order`…) que sugieren un estado configurable por tenant. **El código nunca las lee**: decide por el ID numérico. Los dos diseños conviven y el de datos está muerto (E-06).
- **`shipping_requests.order_id` sigue siendo nullable.** Antes de pensar en NOT NULL/UNIQUE hay que correr `shipments:reconcile`.

## Cartera inicial

- **E-13** `marketplace_products` no tiene el índice único `(channel_id, external_sku)`. La migración `2026_09_16_000001_add_unique_sku_to_marketplace_products` sólo lo crea si no hay duplicados; en el tenant demo abortó dejando un aviso en el log. Hay que resolver los duplicados primero, tenant por tenant.
- **E-16** En el tenant demo hay **1 136 migraciones registradas contra 1 133 ficheros**: tres se borraron del repo sin revertir. El esquema no es reproducible desde cero hasta resolverlo.
- **E-19** La base `ebaemy_warehouse` se crea **a mano** y su migración vive en un path que `migrate` no recorre. Un servidor nuevo no tiene Analytics hasta que alguien se acuerda. Coordina con A13.
- Revisar las divergencias de `status_orders` entre tenants antes de que A03 toque la máquina de estados.

## Nota de entorno

La base local `tenancy_importaloya` **no pertenece a este código**: a su `orders` le faltan ~25 columnas que el código usa (`cancelled_at`, `prepared_at`, `channel_id`, `amount_due`…) y tiene registradas migraciones cuyos ficheros no existen en el repo. Sirve de muestra de esquema antiguo, no de banco de pruebas. El tenant real de este repo es `ebaemyoriginal_demo`.

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
