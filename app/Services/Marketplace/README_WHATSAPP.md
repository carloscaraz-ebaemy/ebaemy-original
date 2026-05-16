# Marketplace WhatsApp (Fase 7)

Notificaciones por WhatsApp al comprador con consent gating. Reusa el `WhatsAppSystemService` que YA existe (SaaS-level, no requiere contexto de tenant).

## Que esta listo

- `MarketplaceWhatsAppNotifier` service con 3 metodos:
  - `sendPriceDrop($user, $drops)`
  - `sendAbandonedCart($user, $itemsCount, $couponCode)`
  - `sendWeeklyOffers($user, $offers, $categoryNames)`
- Hooks en `DetectAndNotifyPriceDrops` y `SendWeeklyMarketplaceDigest`: si el user tiene consent WA + preferences, envia tambien por ahi (paralelo al email, no excluyente).
- Auditoria: cada envio queda registrado en `system_whatsapp_logs` (tabla existente).

## Plantillas Meta requeridas (cuando salir de la ventana 24h)

Sin plantilla solo se puede iniciar conversacion DENTRO de la ventana 24h (si el user inicio chat). Para mensajes proactivos arbitrarios, registrar en Meta Business Manager:

| Template name              | Categoria   | Texto base |
|---|---|---|
| `mkt_price_drop_v1`        | UTILITY     | "Hola {{1}}, un producto que guardaste bajo de precio: {{2}} ahora S/{{3}} (antes S/{{4}})." |
| `mkt_weekly_offers_v1`     | MARKETING   | "Hola {{1}}, esta semana hay ofertas en {{2}}: {{3}}" |
| `mkt_abandoned_cart_v1`    | MARKETING   | "Hola {{1}}, dejaste {{2}} productos en tu carrito. {{3}}" |

Una vez aprobadas, modificar `MarketplaceWhatsAppNotifier` para usar `WhatsAppSystemService::sendTemplate(...)` (metodo a agregar, hoy usa texto libre).

## Driver actual

- Si tenant tiene `qr_api_instance` (Evolution API v2): cualquier mensaje funciona sin plantilla, hasta que Meta limite.
- Si gateway legacy (devaemy.com): texto libre dentro de ventana 24h. Migrar pronto.

## Consent gating

Antes de cada envio se valida:
1. `user.status === 'active'`
2. `user.phone` no vacio
3. Consent vigente para `(whatsapp, {purpose})` en `marketplace_user_consents`
4. `user.preferences.whatsapp_frequency !== 'off'` (default off)

WhatsApp es **opt-in explicito**: el user nunca recibe nada hasta que toggle `whatsapp_frequency` a `weekly|critical_only` Y haya consent grant.

## Abandoned cart — pendiente

`sendAbandonedCart()` esta listo pero **NO esta enchufado** porque el carrito del marketplace vive en sesion (no persiste por user). Para activarlo:

1. Crear tabla `marketplace_carts` (user_id, items[], updated_at).
2. Migrar `MarketplaceCartService` para escribir ahi cuando hay user.
3. Job scheduled `DetectAbandonedCartsViaWhatsApp` que busca carts updated_at > 24h sin checkout exitoso.

## Costos

WhatsApp Business cobra por conversacion iniciada (ventana 24h):
- UTILITY (price_drop, abandoned_cart): bajo costo.
- MARKETING (weekly_offers): costo medio.

Recomendado: empezar SOLO con utility (price_alerts, abandoned_cart). Marketing solo a usuarios muy engaged.
