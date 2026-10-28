# Marketplace — vendedores externos (PRO 9)

Tiendas que **no son tenants de ebaemy** (hoy, los tenants de PRO 9 en devaemy.com) publican en el marketplace por API firmada y reciben sus pedidos por webhook.

## Cómo se representa un vendedor externo

- Fila en `marketplace_external_sellers` + un `hostnames` propio **sin website** (`domain_type = 'external'`, `fqdn` = dominio de la tienda en PRO 9).
- Todo el marketplace sigue agrupando por `hostname_id`: listings, carrito, subpedidos (`tenant_marketplace_orders`), página `/marketplace/tienda/{subdominio}`.
- Los procesos que entran a la BD de un tenant (sync, cupones, estados, comandos) ya saltan los hostnames sin website.
- Si un subdominio coincide con un tenant de ebaemy, la página de tienda muestra el tenant.

Alta (muestra clave y secreto una sola vez):

```
php artisan marketplace:external-seller importaloya.devaemy.com "Importa Lo Ya" --webhook=https://importaloya.devaemy.com
php artisan marketplace:external-seller importaloya.devaemy.com --rotate     # secreto nuevo
```

La clave y el secreto se pegan en PRO 9: Tienda Virtual → Configuración → Canales → Marketplace ebaemy.com.

## Firma (los dos sentidos)

```
X-Seller-Key: mk_…
X-Timestamp:  <unix epoch, ventana ±300 s>
X-Signature:  hex(HMAC-SHA256(secreto, "{timestamp}\n{cuerpo crudo}"))
```

## API de ebaemy (`/api/marketplace/v1`, dominio central)

| Método | Ruta | Uso |
|---|---|---|
| GET | `ping` | Probar credenciales; devuelve `store_url` |
| GET | `categories` | Árbol de categorías (PRO 9 lo copia por `full_slug`) |
| PUT | `listings/{remote_item_id}` | Crear/actualizar producto: `{seller, listing, options, variants}` |
| DELETE | `listings/{remote_item_id}` | Pausar producto (no se borra) |
| POST | `orders/{order_number}/status` | `{status: shipped|delivered|cancelled}`; nunca retrocede |

- Del `listing` solo se aceptan los campos de `ExternalListingUpsertService::LISTING_FIELDS`.
- ebaemy calcula por su cuenta el slug, el texto de búsqueda y la categoría (a partir de `category_full_slug`, solo si es una hoja abierta a vendedores).
- Las imágenes se aceptan únicamente si están en el dominio de la tienda.
- Hereda el `throttle:60,1` del grupo `api`.

## Pedidos (ebaemy → PRO 9)

`MarketplaceMultiOrderDispatcher` desvía los subpedidos de vendedores externos a `ExternalSellerOrderPusher`, que hace:

```
POST {webhook_url}/api/ebaemy/orders
{external_id, order_number, paid, paid_at, customer{…}, delivery{…}, items[{remote_item_id, title, quantity, unit_price}], subtotal, discount, total}
→ {"order_id": 123}
```

- `external_id` es fijo por subpedido, así que un reintento no duplica el pedido.
- Los fallos usan el mismo `retry_count` y el mismo comando `RetryFailedMarketplaceOrders` que los tenants locales.
- PRO 9 avisa "despachado" y "entregado" con `orders/{order_number}/status`.

## Pendiente

- El carrito no registra la variante elegida. Esta limitación ya existe para los tenants locales.
- Cancelar desde PRO 9 todavía no avisa a ebaemy. El endpoint acepta `cancelled`, pero falta enganchar la anulación del pedido en PRO 9.
