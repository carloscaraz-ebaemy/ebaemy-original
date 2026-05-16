# Integración del Marketplace User en vistas del Tenant

A partir de la Fase 2 (commit `<hash>`) cualquier vista Blade del proyecto — incluidas las del ecommerce de cada tenant — recibe automaticamente la variable `$marketplaceUser` con el comprador del marketplace logueado (o `null`).

## Como funciona

- Middleware `App\Http\Middleware\IdentifyMarketplaceUser` esta registrado en el grupo `web` del HTTP Kernel.
- Lee `auth('marketplace')->user()` (la sesion es compartida via cookie `.ebaemy.com`).
- Hace `View::share('marketplaceUser', $user)`.
- Refresca `last_seen_at` con throttling (1 update/min max).

Si el comprador esta logueado en ebaemy.com, automaticamente lo veras logueado en `cualquier-tienda.ebaemy.com` sin escribir codigo.

## Como usarlo en una view Blade

```blade
@if($marketplaceUser)
    <span>Hola, {{ $marketplaceUser->name }}</span>

    @if($marketplaceUser->hasActiveConsent('email', 'marketing'))
        {{-- Puede ver promos --}}
    @endif
@else
    <a href="{{ route('marketplace.login', ['next' => url()->current()]) }}">
        Entrar a ebaemy
    </a>
@endif
```

## En un controller del tenant

```php
$mkt = auth('marketplace')->user();   // null si anonimo
if ($mkt) {
    // tu logica para usuarios logueados de la red
}
```

## En el ecommerce del tenant: que ofrecer

Casos de uso recomendados:

1. **Header**: si logueado, "Hola, X" con dropdown que apunta a `ebaemy.com/marketplace/account`.
2. **Precios personalizados** (cuando exista Fase 5 cupones): consultar cupones del user con scope tenant=this.
3. **Pre-llenar checkout** con email/nombre/telefono del marketplace user.
4. **Boton "Guarda en favoritos"** que apunta a `ebaemy.com/marketplace/favorites/toggle` (CSRF cross-domain pendiente Fase 3).

## Que NO hacer

- NO mezclar con `Auth::user()` del tenant. Son guards distintos y modelos distintos. El comprador del marketplace nunca pisa la sesion del usuario admin del tenant.
- NO depender de que la cookie llegue si `SESSION_DOMAIN` no esta seteado a `.ebaemy.com` en produccion.
- NO consultar `system.marketplace_users` directamente: usa `auth('marketplace')->user()` o `$marketplaceUser` — ya esta cacheado por el guard durante el request.

## Merge silencioso al login

Cuando un visitante anonimo marca favoritos y luego se loguea, los favoritos pasan automaticamente a su cuenta. Implementado en `MarketplaceUserMergeService`.

Reglas:
- Nunca pisa lo que el user ya tenia.
- Nunca duplica.
- Idempotente.

En Fase 3 se agrega merge de `marketplace_user_views` cuando exista.
