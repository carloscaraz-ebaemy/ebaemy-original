# Marketplace Coupons (Fase 5)

Cupones de PLATAFORMA gestionados desde SuperAdmin (`/admin/marketplace/coupons`). Distintos a `tenant.coupons` (codigos publicos por tienda) — las dos capas coexisten en el checkout.

## Casos de uso tipicos

- **Captacion**: regalo a usuarios recien registrados ("WELCOME10" 10% off).
- **Retencion**: cupon de retorno a usuarios inactivos.
- **VIP**: descuento permanente para usuarios con N pedidos completados.
- **Recuperacion**: cupon automatico en abandoned cart >24h (futuro).

## Schema

- `marketplace_coupons`: definicion (code, type, value, scope, ventana, limites).
- `marketplace_user_coupons`: asignacion 1:1 user↔coupon (assigned_at, used_at).

## API basica

```php
use App\Services\Marketplace\MarketplaceCouponService;

$service = app(MarketplaceCouponService::class);

// Asignar
$service->assignToUser($marketplaceUser, $coupon, $expiresAt = null);

// Listar aplicables a un contexto (hostnameId puede ser null si platform-wide)
$available = $service->availableForUser($marketplaceUser, $hostnameId, $subtotal);
// → Collection<['coupon', 'discount', 'assignment_id']>

// Redimir tras confirmar pedido
$service->redeem($assignmentId, $hostnameId, $orderId);
```

## Que esta listo

- SuperAdmin UI completo: crear / pausar / asignar (por emails masivos).
- Service `MarketplaceCouponService` con metodos para checkout.
- Pagina `/admin/marketplace/coupons` enchufada al menu de admin (sin link aun en sidebar — usar URL directa).

## Que NO esta enchufado (intencional)

- **Aplicacion en el checkout del marketplace** (`MarketplaceCheckoutController@store`) y en el del tenant.

Para enchufarlo cuando quieras:

### En el checkout del marketplace (`MarketplaceCheckoutController`)

Despues de calcular `$subtotal` del store y antes de calcular `$total`:

```php
$mktUser = auth('marketplace')->user();
if ($mktUser) {
    $available = app(MarketplaceCouponService::class)
        ->availableForUser($mktUser, $hostnameId, $subtotal);
    if ($available->isNotEmpty()) {
        // Aplicar el mejor (mayor descuento)
        $best = $available->sortByDesc('discount')->first();
        $platformDiscount = $best['discount'];
        $platformAssignmentId = $best['assignment_id'];
        // Restar de total y guardar en sesion / payload del store.
    }
}
```

Al confirmar pedido exitoso:

```php
if (!empty($platformAssignmentId)) {
    app(MarketplaceCouponService::class)
        ->redeem($platformAssignmentId, $hostnameId, $tenantOrderId);
}
```

### En el checkout del tenant (eCommerce de la tienda)

Mismo patron — el tenant consulta `availableForUser($user, $hostnameId, $subtotal)`. Como `MarketplaceUser` vive en system, la query funciona desde cualquier contexto.

UI: mostrar los cupones disponibles en el resumen del carrito ("Tienes un descuento de plataforma: -S/X").

## Diferencias vs `tenant.coupons`

| Aspecto              | tenant.coupons          | marketplace_coupons       |
|---|---|---|
| Vive en              | DB del tenant           | DB system                 |
| Quien crea           | Admin del tenant        | SuperAdmin (+ futuro: tenant admin con scope=tenant) |
| Codigo publico       | Si (cualquiera lo usa)  | Solo asignados lo ven     |
| Aplica en            | Solo esa tienda         | Platform-wide o tenant-specific |
| Tracking de usuario  | No                      | Si (assigned_at, used_at) |
| Limite por user      | No                      | max_per_user enforced     |
| Compose ambos        | Si — son aditivos en el checkout                  |

## Asignacion masiva

Desde el modal "+ Asignar" del admin, pegar emails (uno por linea o separados por coma). Los que no existan en `marketplace_users` se reportan en `missing_emails`.

Para asignar a TODOS los users activos:
```php
$coupon = MarketplaceCoupon::find($id);
$service = app(MarketplaceCouponService::class);
MarketplaceUser::where('status','active')->chunk(500, function($users) use ($service, $coupon) {
    foreach ($users as $u) $service->assignToUser($u, $coupon);
});
```

## Auditoria

Si un cupon tiene redenciones (`used_at IS NOT NULL`), `destroy()` lo bloquea — solo se puede desactivar (`toggle`). Las asignaciones se conservan para historial.
