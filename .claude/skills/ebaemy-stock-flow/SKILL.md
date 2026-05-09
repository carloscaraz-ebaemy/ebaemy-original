---
name: ebaemy-stock-flow
description: Auditoría y mantenimiento del flujo de stock en EBAEMY (productos con y sin variantes). Invocar cuando el usuario reporte "el stock no cuadra", "se está actualizando el stock general al modificar variantes", "el marketplace muestra otro stock que el tenant", "la suma de variantes no coincide con el padre", o cuando se vaya a tocar cualquier código que escriba en items.stock, item_warehouse, item_variants.stock o item_variant_warehouse.
---

# Flujo de stock en EBAEMY (multi-tenant + variantes)

## Tablas y contrato

| Tabla | Columnas críticas | Quién es la fuente de verdad |
|---|---|---|
| `items` | `stock` (decimal 12,2), `has_variants` (bool) | **DERIVADO** cuando `has_variants=true`. Directo cuando false. |
| `item_warehouse` | `item_id`, `warehouse_id`, `stock`, `stock_physical`, `stock_committed` | **DERIVADO** cuando hay variantes (suma de `item_variant_warehouse`). Directo cuando no. |
| `item_variants` | `id`, `item_id`, `stock` (decimal 12,4), `is_active`, `is_primary` | **DERIVADO** — suma de `item_variant_warehouse` para esa variante. |
| `item_variant_warehouse` | `item_variant_id`, `warehouse_id`, `stock_physical`, `stock_committed`, `stock` | **FUENTE DE VERDAD** cuando hay variantes. Todo cálculo arriba sale de aquí. |

### Regla de oro

> Cuando `items.has_variants = true`, **nadie debe escribir directamente** en `items.stock` ni en `item_warehouse.stock`. Esos valores se recalculan desde `item_variant_warehouse` vía `ItemVariantService::propagateStock()`.

Cuando `has_variants = false`, los providers legacy (Inventory/Kardex) escriben directo en `item_warehouse` como siempre.

## Flujo correcto: ajustar stock de una variante

```
Seller abre dialog "Ajustar stock" en variants-tab.vue
       ↓
saveStock() → POST /items/{item}/variants/{variant}/stock
       ↓
ItemVariantController::updateStock()
       ↓
ItemVariantService::updateVariantStock($variant, $warehouseId, $stock)
       ↓ (transacción tenant)
       ├── 1. UPDATE item_variant_warehouse.stock_physical = $stock
       ├── 2. UPDATE item_variants.stock = SUM(item_variant_warehouse del variant)
       └── 3. propagateStock($item)
              ├── UPDATE item_warehouse.stock = SUM(item_variant_warehouse por warehouse)
              └── UPDATE items.stock = SUM(item_variant_warehouse global)
```

## Flujo correcto: sync al marketplace

`MarketplaceListingSyncService::buildPayload()` decide qué stock subir al system:

- Si `has_variants=true` → usa la suma de `item_variants.stock` (que ya está propagado).
- Si `has_variants=false` → usa la suma de `item_warehouse.stock`.

**Nunca lee `items.stock` directo** — solo como último fallback. Por eso el marketplace nunca se desincroniza aunque `items.stock` esté desactualizado.

## Bugs conocidos y cómo identificarlos

### Bug "se actualiza el stock del padre al editar variantes"

**Síntoma**: el seller cambia el stock de una variante en el dialog y al recargar el form del producto padre, el stock del padre cambió (a veces a 0 o a un valor que no esperaba).

**Causa**: NO está en `updateVariantStock` (eso propaga correctamente). El bug aparece cuando el seller después abre el form completo del producto padre (`form.vue` o `items_ecommerce/form.vue`) y le da "Guardar". El `ItemController::store()` hace `$item->fill($request->all())` y eso sobrescribe `items.stock` con lo que tenía el form (que puede ser 0, viejo, o un cálculo incorrecto del frontend).

**Fix aplicado (2026-05-08)**: tras `$item->save()`, si `has_variants=true`, llamar `ItemVariantService::propagateStock($item->fresh())` para forzar el recálculo derivado.

### Bug "marketplace muestra X y el tenant Y"

**Causa típica**: `items.stock` quedó desincronizado por el bug anterior. El marketplace lee de `item_variants.stock` (correcto) pero el tenant muestra `items.stock` (corrupto). Aplicar el fix arriba + correr una vez:

```bash
php artisan tinker --execute='
foreach (\App\Models\Tenant\Item::where("has_variants", true)->cursor() as $i) {
    app(\App\Services\Tenant\ItemVariantService::class)->propagateStock($i);
    echo "OK item " . $i->id . PHP_EOL;
}
'
```

(Iterar también por tenant si es producción multi-tenant.)

### Bug "stock disponible incorrecto" (committed)

`stock_available = stock_physical - stock_committed`. Si los pedidos se anulan pero `stock_committed` no se decrementa, queda permanentemente bloqueado. Buscar listeners en `app/Providers/AnulationServiceProvider.php` y verificar que se ejecutan al anular sale_notes / orders.

## Reglas duras

❌ **NUNCA** escribas directo a `items.stock` o `item_warehouse.stock` cuando el item tiene variantes. Si lo haces, el bug del padre vuelve.

❌ **NUNCA** confíes en `items.stock` para tomar decisiones — siempre lee `item_variant_warehouse` directamente o llama a `Item::getStockByWarehouse()` (que ya hace el cálculo correcto).

❌ **NUNCA** modifiques `propagateStock()` para que sea condicional ("solo si X"). Es la única red de seguridad.

✅ **SIEMPRE** que agregues una acción nueva que pueda modificar `items.stock` o `item_warehouse`, llama `propagateStock()` después si el item tiene variantes.

✅ **SIEMPRE** que el seller pueda editar un producto que tenga variantes (form padre, bulk edit, import), excluye `stock` del payload o llama `propagateStock` después.

✅ **SIEMPRE** que escribas un test que toque stock con variantes, verifica los 4 lados: `item_variant_warehouse`, `item_variants.stock`, `item_warehouse.stock`, `items.stock`.

## Archivos clave

```
app/Models/Tenant/
├── Item.php                                (getStockByWarehouse — lee derivado)
├── ItemVariant.php
├── ItemVariantWarehouse.php
└── ItemWarehouse.php

app/Http/Controllers/Tenant/
├── ItemController.php                      (store/update padre — barrera necesaria)
└── ItemVariantController.php               (updateStock variant — flujo correcto)

app/Services/Tenant/
└── ItemVariantService.php
    ├── updateVariantStock()                (entrada del flujo correcto)
    └── propagateStock()                    (la única función que recalcula desde variantes)

app/Services/System/
└── MarketplaceListingSyncService.php       (buildPayload — lee derivado, no items.stock)

resources/js/views/tenant/items/partials/
└── variants-tab.vue                        (saveStock → endpoint correcto)
```

## Cuándo invocar este skill

- "El stock del producto padre se desincronizó cuando edité variantes"
- "El marketplace muestra X, el tenant muestra Y"
- "Estoy agregando una acción que modifica stock"
- "Quiero auditar por qué un producto tiene stock 0 si las variantes suman 50"
- Cualquier PR/cambio que toque las 4 tablas de la primera sección

## Cuándo NO invocar

- Cambios solo de UI sin lógica de stock
- Reportes/dashboards que solo LEEN stock (no lo modifican)
- Productos sin variantes (`has_variants=false`) — flujo legacy directo, separado
