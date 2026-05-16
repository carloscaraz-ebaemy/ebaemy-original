# Marketplace Auth — Fase 1

Identidad del comprador cross-tenant. Vive en la BD `system` y es reconocido por todos los subdominios via cookie de sesion compartida.

## Que entrega esta fase

- Tablas: `marketplace_users`, `marketplace_user_consents`, `marketplace_user_magic_links`, `marketplace_user_preferences`.
- Login passwordless por magic link + codigo 6 digitos (mismo email).
- Guard Laravel `marketplace` + provider `marketplace_users`.
- Consent append-only (compliance: nunca se hace UPDATE).
- Rate limiting: 3 magic links/hora por email, 10/hora por IP.
- Vistas: `/marketplace/login`, `/marketplace/account`, link en navbar.

## Que NO entrega aun

- Middleware en tenants (Fase 2 — requiere JWT con clave publica para validar sin hit a system DB).
- Pedidos cross-tenant (Fase 4).
- Cupones, intereses, notificaciones (Fases 5-7).
- "Mi cuenta" rica: solo placeholder con favoritos/carrito existentes.

## Deploy a produccion

```bash
cd /home/ebaemy/ebaemy/laravel
git pull origin main
php artisan migrate                                # crea las 4 tablas en system
php artisan view:clear && php artisan view:cache
sudo systemctl restart php8.3-fpm
```

**Config requerida en `.env` del server** (sino la cookie no llega a los subdominios):

```
SESSION_DOMAIN=.ebaemy.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

Con `SESSION_DOMAIN=.ebaemy.com` (nota el punto inicial) la cookie de sesion se setea para `ebaemy.com` y todos los subdominios la leen. Es la base para que la Fase 2 funcione.

## Email (mail driver)

El magic link usa `Mail::send()` con el driver default. Verificar que `MAIL_*` este configurado en produccion (SMTP corporativo, Brevo cuando se migre, etc.).

En local con `MAIL_DRIVER=log` el contenido del email (incluido token+codigo) queda en `storage/logs/laravel.log`.

## Flujo

1. Visitante → `/marketplace/login` → ingresa email + opcionalmente marca opt-in marketing.
2. Backend genera token random (40 chars) + codigo 6 digitos. Guarda hashes (SHA-256) en `marketplace_user_magic_links`. TTL 15 min.
3. Envia email con AMBOS: link directo (`/marketplace/auth/verify?token=...`) Y codigo.
4. Si el user clickea el link → consume token y loguea.
5. Si abrio el correo en mobile (link puede abrir otro browser) → pega el codigo en el form `/marketplace/auth/code`.
6. Verify crea el `MarketplaceUser` si no existe, marca `email_verified_at`, registra consent `transactional`. Si el opt-in estaba marcado, registra `marketing`.
7. `Auth::guard('marketplace')->login($user)` → sesion Laravel con cookie cross-domain.

## Como consultar en codigo (Fase 1)

```php
$user = auth('marketplace')->user();          // null si anonimo
if ($user) {
    if ($user->hasActiveConsent('email', 'marketing')) {
        // ok mandar promo
    }
}
```

En Fase 2 esto seguira funcionando IDENTICO en cualquier subdominio del tenant, sin tocar el codigo de los tenants — basta con instalar el middleware `IdentifyMarketplaceUser`.

## Tests manuales

- `php artisan tinker` + crear MagicLink + verify (ver script en commit `<hash>`).
- Visitar `/marketplace/login` con `MAIL_DRIVER=log` y leer `storage/logs/laravel.log` para extraer el codigo.

## Proximas fases

- 2: `IdentifyMarketplaceUser` middleware en tenants + merge de favoritos sesion->user.
- 3: Tracking de views async + interests job + "Mi cuenta" enriquecido.
- 4: Pedidos cross-tenant via job/webhook.
- 5: Cupones desde SuperAdmin + motor en tenants.
- 6: Brevo (email transaccional + marketing con consent gating).
- 7: WhatsApp con plantillas Meta.
