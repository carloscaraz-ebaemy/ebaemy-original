# Marketplace Notifications (Fase 6)

Sistema de notificaciones al comprador con consent gating estricto.

## Reglas obligatorias

**TODO** envio (email o whatsapp) DEBE pasar por `MarketplaceNotificationService::canSendEmail()` / `canSendWhatsApp()`. Estas funciones verifican:

1. `user.status === 'active'`
2. Existe consent vigente para `(channel, purpose)` en `marketplace_user_consents`.
3. `user.preferences.{channel}_frequency !== 'off'`.

Excepcion: `purpose='transactional'` saltea consent y preferences (legal — confirmacion de pedido, magic link).

## Jobs scheduled

| Job                              | Frecuencia       | Que hace |
|---|---|---|
| DetectAndNotifyPriceDrops        | Diario 09:00     | Detecta favoritos con price_snapshot > current_price >= 5%, agrupa por user, envia 1 mail con drops. Actualiza snapshot. |
| SendWeeklyMarketplaceDigest      | Domingo 10:00    | Para cada user con email_frequency=weekly + consent + intereses, envia top 6 ofertas en sus top 3 categorias. |

Ambos respetan consent. Si el user revoca, los siguientes runs no le mandan.

## Mailables

- `MarketplacePriceDropMail` — recibe `User + drops[]`.
- `MarketplaceWeeklyDigestMail` — recibe `User + offers[] + categoryNames[]`.

Templates en `resources/views/emails/marketplace_*`.

## Brevo migration (futuro)

El driver de mail es generico — cambiar `MAIL_MAILER=brevo` (con paquete `arvolution/brevo-laravel` o similar) y los Mailables siguen funcionando sin cambios.

## Probar localmente

Con `MAIL_DRIVER=log` el email se escribe en `storage/logs/laravel.log`. Para probar manualmente:

```bash
php artisan tinker
> (new App\Jobs\Marketplace\DetectAndNotifyPriceDrops())->handle(app(App\Services\Marketplace\MarketplaceNotificationService::class));
```

## Como agregar un nuevo tipo de notificacion

1. Definir el `purpose` (ej. `abandoned_cart`).
2. Crear Mailable + template.
3. Crear Job que use `$notif->canSendEmail($user, 'abandoned_cart')`.
4. Agregar al `MarketplaceUserConsent` enum si es nuevo.
5. Registrar opt-in en `marketplace_user_consents` cuando el user lo acepte (puede ser implicito en el form de checkout).
6. Schedule el job.

## SLA

- Consent revocado: efecto inmediato para nuevos sends. Jobs ya encolados verifican consent al ejecutar (no en el dispatch).
- Frequency=off: cero envios marketing. Transaccional sigue.

## NO hacer

- Saltarse `MarketplaceNotificationService`. Compliance.
- Persistir consents con UPDATE — siempre INSERT (append-only).
- Mandar marketing a usuarios sin opt-in explicito.
