# Marketplace Order Sync (Fase 4)

Permite que los pedidos del tenant queden registrados en el snapshot agregado del comprador en `system.marketplace_user_orders`, para personalizacion y "Mis pedidos" en la cuenta del comprador.

## Diseno

- **Tenant es fuente de verdad**. System solo guarda un agregado minimo (no lineas, no detalle).
- **Push via job en queue**. El pedido en el tenant NUNCA falla si system esta caido.
- **Idempotente**. Re-pushear el mismo pedido actualiza la fila (unique en `hostname_id + order_id`).

## Que esta listo

- Tabla `marketplace_user_orders` (Fase 3).
- `App\Services\Marketplace\MarketplaceOrderSyncService` con `syncOrder($userId, $tenantOrder)`.
- `App\Jobs\Marketplace\PushOrderToSystem` job idempotente.
- Pagina `/marketplace/account/orders` que muestra el historial cross-tenant.

## Como instrumentar el flujo legacy de pedidos del tenant

El hook NO esta enchufado por default — para no riesgo de romper el flujo de pedidos legacy con un deploy. Para activarlo:

### 1. Identificar dónde se confirma un pedido

Lugares comunes en este codebase:
- `app/Http/Controllers/Tenant/DocumentController@store`
- `app/Http/Controllers/Tenant/SaleNoteController@store`
- `app/Http/Controllers/Tenant/OrderController@store`
- Cualquier servicio interno de "registrar venta".

### 2. Asociar el comprador del marketplace al pedido del tenant

Al hacer checkout, si `auth('marketplace')->check()` es true, guardar el ID en el pedido. Para esto necesitas agregar una columna a la tabla relevante del tenant (sale_notes, documents, orders…):

```php
// migration en tenant
$table->unsignedBigInteger('marketplace_user_id')->nullable()->index();
```

En el form/controller del checkout:

```php
$payload['marketplace_user_id'] = auth('marketplace')->id();
```

### 3. Disparar el sync DESPUES de save() y dentro del if(success)

```php
use App\Services\Marketplace\MarketplaceOrderSyncService;

// ... codigo que guarda el documento/sale_note/order ...
if ($document->wasRecentlyCreated && $document->marketplace_user_id) {
    app(MarketplaceOrderSyncService::class)
        ->syncOrder($document->marketplace_user_id, $document);
}
```

### 4. Para cambios de estado (cancelar / completar)

Repetir el `syncOrder()` cada vez que el estado cambia. El job es idempotente — la fila se actualiza.

```php
// despues de cambiar state del pedido
if ($document->marketplace_user_id) {
    app(MarketplaceOrderSyncService::class)
        ->syncOrder($document->marketplace_user_id, $document);
}
```

## Requisitos del modelo `$tenantOrder`

El servicio espera estos campos (todos opcionales — usa defaults sanos):
- `id` (PK del pedido en el tenant) **requerido**.
- `total` (decimal) requerido.
- `currency_type_id` o `currency` (string, default `PEN`).
- `state_type_id` o `state` o `status` — para mapear estado.
- `confirmed_at` / `created_at` — fecha de confirmacion.
- `cancelled_at` — fecha de cancelacion si aplica.
- `items_count` — opcional, sino se intenta derivar de `items` relacion.
- `product_categories` — array opcional de IDs, sino se derivan de `items[].item.marketplace_category_id`.

## Status mapping

El service mapea estados internos del tenant a los 3 buckets de la tabla agregada:

| Tenant raw            | System status |
|-----------------------|---------------|
| `01`, `registered`, `confirmed`, `pending` | `confirmed` |
| `05`, `07`, `completed`, `delivered`        | `completed` |
| `11`, `13`, `cancelled`, `canceled`, `anulado` | `cancelled` |

Si el codigo del tenant usa otros, agregar en `mapStatus()`.

## Webhook (HTTP) — alternativa al job

Si en el futuro hay tenants en servidores separados, en lugar del job se puede exponer `POST /marketplace/internal/orders` con auth basica HMAC. Por ahora todo corre en el mismo server PHP, el job basta.

## Queue worker requerido

El push depende de `php artisan queue:work` corriendo en produccion (ya esta para los jobs de WhatsApp segun memoria del proyecto).
