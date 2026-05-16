# Marketplace — Login con Google

Login OAuth con Google para el comprador del marketplace. Usa Laravel Socialite (ya instalado).

## Setup en produccion

### 1. Crear credenciales en Google Cloud Console

1. https://console.cloud.google.com → APIs y Servicios → Credenciales.
2. Crear "ID de cliente OAuth 2.0" tipo "Aplicacion web".
3. **Origenes autorizados**: `https://ebaemy.com`
4. **URI de redireccion autorizada**: `https://ebaemy.com/marketplace/auth/google/callback`
5. Copiar Client ID y Client Secret.

### 2. Agregar al `.env` del server

```
GOOGLE_CLIENT_ID=xxxxxxxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxx
```

(Ya existe `config/services.php` con esos env vars — no hay que tocar nada mas.)

### 3. Reiniciar

```bash
php artisan config:clear && sudo systemctl restart php8.3-fpm
```

## Como funciona

- `GET /marketplace/auth/google` → redirect a Google con scopes `openid profile email`.
- Usuario aprueba en Google.
- Google redirect a `/marketplace/auth/google/callback?code=...`.
- Controller intercambia code por user info via Socialite.
- Servicio crea o recupera `MarketplaceUser` por email, marca verified, loguea.

## Comportamiento del registro

- Email **NO existe**: crea cuenta nueva. Marca `email_verified_at`, status active, name desde Google. Sin password (puede setearla despues en Mi cuenta si quiere combinar con login email+password).
- Email **YA existe sin password** (magic link previo): solo loguea, hereda nombre de Google si la cuenta lo tenia auto-generado.
- Email **YA existe con password**: tambien loguea. El password queda intacto (puede usar AMBOS metodos despues).

## Merge de favoritos anonimos

Funciona igual que el resto de los flujos: el `session_id` anonimo se captura antes del redirect, al volver se invoca `MarketplaceUserMergeService::mergeFromSession()`.

## Si Google no esta configurado

`GoogleRedirect` detecta `client_id` vacio y redirige a `/marketplace/login` con un error visible. El boton sigue en la UI pero no rompe.

## Para mas providers (Facebook, Apple…)

Mismo patron: agregar credentials en `config/services.php`, agregar `$endpoint`s en `MarketplaceAuthController`, botones en `login.blade.php` y `register.blade.php`.
