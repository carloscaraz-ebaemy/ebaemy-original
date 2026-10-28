# Marketplace — vendedores externos (PRO 9)

Tiendas que **no son tenants de ebaemy** (hoy, los tenants de PRO 9 en devaemy.com) publican en el marketplace por API firmada y reciben sus pedidos por webhook.

## Cómo se representa un vendedor externo

- Fila en `marketplace_external_sellers` + un `hostnames` propio **sin website** (`domain_type = 'external'`, `fqdn` = dominio de la tienda en PRO 9).
- Todo el marketplace sigue agrupando por `hostname_id`: listings, carrito, subpedidos (`tenant_marketplace_orders`), página `/marketplace/tienda/{subdominio}`.
- Los procesos que entran a la BD de un tenant (sync, cupones, estados, comandos) ya saltan los hostnames sin website.
- Si un subdominio coincide con un tenant de ebaemy, la página de tienda muestra el tenant.

## Alta y aprobación

La tienda se registra sola. En PRO 9 está en **Tienda Virtual → Configuración → Canales → «Publicar en ebaemy.com»**:

1. PRO 9 llama a `POST /api/marketplace/v1/sellers/register` firmado con la **llave de plataforma**. Esa llave es la misma en los dos `.env`:
   - ebaemy: `MARKETPLACE_PRO9_KEY`, `MARKETPLACE_PRO9_SECRET`, `MARKETPLACE_PRO9_DOMAIN=devaemy.com`
   - PRO 9: `EBAEMY_PLATFORM_KEY`, `EBAEMY_PLATFORM_SECRET`

   Solo se aceptan dominios `*.devaemy.com` cuyo webhook esté en ese mismo dominio.
2. ebaemy crea el vendedor en estado **`pending`**, avisa al administrador (campana del panel) y devuelve la clave y el secreto propios de la tienda, que PRO 9 guarda solo.
3. Mientras está `pending`, la tienda ya puede enviar productos. Se guardan con `is_active = false`.
4. El administrador entra a **/admin/marketplace/tiendas-externas** (menú «Tiendas PRO 9») y elige:
   - **Aprobar:** salen a la venta de una vez todos los productos que el vendedor dejó activos.
   - **Rechazar** o **Suspender:** se ocultan. La nota que se escriba la ve la tienda en su PRO 9.
5. PRO 9 consulta el estado (`ping`) cada vez que el dueño abre la tarjeta.
6. Una tienda rechazada puede volver a solicitar, y entonces vuelve a `pending`. Una suspendida no: el administrador la tiene que reactivar.

Alta manual, para casos puntuales:

```
php artisan marketplace:external-seller importaloya.devaemy.com "Importa Lo Ya" --webhook=https://importaloya.devaemy.com [--approve]
```

## Firma (los dos sentidos)

```
X-Seller-Key: mk_…
X-Timestamp:  <unix epoch, ventana ±300 s>
X-Signature:  hex(HMAC-SHA256(secreto, "{timestamp}\n{cuerpo crudo}"))
```

## API de ebaemy (`/api/marketplace/v1`, dominio central)

| Método | Ruta | Uso |
|---|---|---|
| POST | `sellers/register` | (llave de **plataforma**) La tienda pide vender; devuelve su clave y secreto y queda `pending` |
| GET | `ping` | Probar credenciales; devuelve `status`, `admin_notes` y `store_url` |
| POST | `listings/batch` | Hasta 50 productos `{remote_item_id, publishable, payload}` por petición |
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

## Variante en el pedido

- Cada talla o color es su propia línea del carrito, con clave `{listing}-{listing_variant}`.
- La línea queda guardada en `marketplace_order_items` (`listing_variant_id`, `tenant_variant_id`, `variant_name`).
- Al vendedor le llega como `variant_id` y `variant_name` en cada ítem.
