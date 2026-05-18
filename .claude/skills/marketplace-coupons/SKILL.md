---
name: marketplace-coupons
description: Sistema de cupones y descuentos del marketplace ebaemy. Lgica multi-tenant (cada tienda crea sus cupones, descuentan solo sus items), aplicacin en checkout, race conditions, visibilidad al comprador. Invocar cuando el usuario pida crear/modificar cupones, cambiar reglas de descuentos, agregar touchpoints donde el comprador vea promociones, mejorar conversin de cupones asignados o tocar el flujo de checkout marketplace.
---

# Cupones y descuentos del marketplace

## Modelos y tablas (BD `system`, conexin shared)

```
app/Models/System/
 MarketplaceCoupon.php
    ATributos clave:
       code (unique), name, description
       type: 'percent' | 'fixed'
       value (decimal)
       min_subtotal (nullable)  mnimo de compra para aplicar
       max_discount (nullable)  cap absoluto si type='percent'
       scope: 'tenant' | 'platform'
       tenant_id (nullable, FK a hostnames.id)  obligatorio si scope=tenant
       valid_from, valid_until
       max_redemptions (global), max_per_user
       is_active
 MarketplaceUserCoupon.php
     Asignaciones cupon  user del marketplace
     Atributos: user_id, coupon_id, scope, tenant_id, assigned_at,
       used_at, expires_at, redeemed_hostname_id, redeemed_order_id

Tabla snapshot por orden (en orden):
 system.tenant_marketplace_orders
     Cupn del tenant:   coupon_code, discount_amount
     Cupn de plataforma: platform_coupon_code, platform_discount_amount,
                          platform_coupon_assignment_id
```

## Reglas de negocio (las 8 reglas)

1. **Quin crea**: cada tenant crea sus cupones desde su panel
   `/marketplace-coupons`. SuperAdmin asigna cupones de plataforma a
   usuarios desde `/admin/marketplace/coupons`.

2. **Scope del descuento**: un cupn descuenta **solo items del tenant
   emisor**. Carrito con productos de A, B, C + cupn de A  descuento
   slo a items de A.

3. **Quin absorbe**: el descuento del cupn de tenant lo absorbe el
   tenant (ebaemy le paga subtotal_propio  descuento_propio). El
   descuento del cupn de plataforma lo absorbe ebaemy (la plataforma).

4. **Min de compra**: se evala por subtotal de la tienda, **no por
   total del carrito**. Cupn de A con min S/100: aplica si A>=100 en
   ese carrito, independiente de lo que tenga B o C.

5. **Stackeo**: max 1 cupn por tienda. Comprador con productos de 3
   tiendas puede aplicar hasta 3 cupones distintos (uno por cada).
   Cupn de tenant + cupn de plataforma S coexisten en la misma
   tienda  se aplican secuencialmente (tenant primero, plataforma
   sobre el resto). Sin doble descuento.

6. **UX por tienda**: el carrito/checkout muestra blocks separados por
   tienda con input de cupn propio. Cada block muestra: items, input,
   descuento, subtotal final. Total general suma todo.

7. **Race conditions**: el redeem es atmico dentro de la transaccin
   de checkout. Si hay race entre dos compradores por el ltimo cupn,
   uno aborta el pedido ENTERO (no parcial). Ver
   `MarketplaceCheckoutService:265-280`.

8. **Ciclo de vida**:
   - Asignado pero no usado en 24h  job `ReleaseStaleCouponRedemptions`
     lo libera al pool.
   - Refund total post-paid  cupn se libera (`MarketplaceCouponService::releaseAllForOrder`)
   - Refund parcial  cupn queda consumido (decisin actual)
   - Fecha vencimiento  no aparece como disponible

## Services y controllers clave

### MarketplaceCouponService
**Path**: `app/Services/Marketplace/MarketplaceCouponService.php`

Mtodos importantes:
- `availableForUser($user, $hostnameId, $subtotal)`: lista cupones que
  el user puede aplicar a esa tienda con ese subtotal. Filtra por
  scope, ventana de validez, lmites global + per-user. Devuelve
  `[coupon, discount, assignment_id]`.
- `assignToUser($userId, $couponId, $scope, $tenantId)`: crea
  asignacin en `marketplace_user_coupons`. **Falta**: dispatch de
  CouponAssignedMail (gap del item 7 del roadmap visibilidad).
- `releaseAllForOrder($orderId)`: libera todos los cupones de una
  orden refunded (cualquier suborden).

### MarketplaceCheckoutService
**Path**: `app/Services/System/MarketplaceCheckoutService.php`

Lneas clave:
- `:56-106` calcula totales por tienda + cupones
- `:238` aplica cupn de plataforma sobre subtotal restante (no doble descuento)
- `:265-280` redeem atmico dentro de transaccin

### Controllers
```
app/Http/Controllers/
 Tenant/MarketplaceCouponController.php       seller crea/edita
 System/MarketplaceCouponController.php       SuperAdmin asigna
 MarketplaceCheckoutController.php
     applyCoupon(Request $req)  endpoint AJAX checkout
        marketplace.checkout.coupon (throttle 20,1)
```

### Job nocturno
```
app/Jobs/Marketplace/ReleaseStaleCouponRedemptions.php
 Libera cupones asignados sin usar > 24h
 Configurado en Console/Kernel.php  schedule diario
```

## Rutas clave

```
routes/web.php:
 1238 GET  marketplace/account/coupons          listado del comprador
 1239 GET  marketplace/account/coupons/count    {count: N} para badge navbar
 Tenant:   prefix marketplace/coupons          panel del seller
 System:   admin/marketplace/coupons           panel SuperAdmin
```

## Visibilidad al comprador (touchpoints)

Estado por touchpoint (despus del roadmap de visibilidad):

| Touchpoint | Path | Estado |
|---|---|---|
| Mi cuenta  Mis cupones | `marketplace/auth/coupons.blade.php` |  Listado completo |
| Badge navbar |  cono ticket + contador | post-roadmap |
| Hint mini-cart | mpMiniCartCouponHint en layout | post-roadmap |
| Lista en checkout | bloque "Tus cupones disponibles" por tienda | post-roadmap |
| Badge en card | listing-card si user tiene cupn aplicable | post-roadmap |
| Toast login | flash session despus de auth | post-roadmap |
| Seccin detalle producto | show.blade.php  cupones de esta tienda | post-roadmap |
| Email asignacin | CouponAssignedMail | post-roadmap |
| Banner home | si hay cupn por vencer <72h | post-roadmap |
| Carrito abandonado | job + email + cupn sugerido | post-roadmap |

## Reglas duras

  **NUNCA** apliques un descuento al total del carrito ignorando que
   los items vienen de varios tenants. Siempre subtotal por tienda.

  **NUNCA** persistas un descuento sin guardar en
   `tenant_marketplace_orders.coupon_code` + `discount_amount` y/o
   `platform_coupon_code` + `platform_discount_amount` + assignment_id.
   Necesarios para refunds.

  **NUNCA** llames a `MarketplaceCouponService::redeem` fuera de la
   transaccin del checkout. Si lo hace antes y luego falla la orden,
   queda redeemed sin orden  cupn "huerfano" hasta que el job
   ReleaseStaleCouponRedemptions lo libere.

  **NUNCA** apliques un cupn de tenant a items de otra tienda. El
   filtro `tenant_id === hostnameId` en `availableForUser:91` es
   obligatorio.

  **NUNCA** modifiques `marketplace_user_coupons.used_at` directo
   con DB::update sin pasar por el service  rompera el flag
   `redeemed_order_id` necesario para refunds.

  **SIEMPRE** que agregues un touchpoint nuevo de visibilidad de
   cupones, expone tambin el evento JS `window.mpCouponBadgeUpdate(n)`
   para que el badge navbar se actualice si el conteo cambia.

  **SIEMPRE** valida server-side al confirmar checkout. El input AJAX
   del cupn es solo UX; la fuente de verdad es la validacin final
   en `MarketplaceCheckoutService::create`.

  **SIEMPRE** que asignes un cupn nuevo, dispara
   `CouponAssignedMail` (cuando exista, ver item 7 roadmap). El
   comprador necesita saber que tiene el cupn aunque no entre al
   sitio.

## Casos comunes

### "Cmo debe verse un descuento en carrito multi-tenant?"
Cada tienda tiene su block con su subtotal y su descuento. El total
general resta todos los descuentos. Si Tienda A tiene cupn -10% y B
no tiene  A se ve descontada, B paga completo.

### "Cmo s si tengo cupones disponibles?"
Si ests logueado: badge en navbar con icono . Hint en mini-cart
drawer al abrirlo. Lista en /marketplace/account/coupons. Toast al
loggearte si hay cupones nuevos. Email cuando alguien te asigna uno
(items del roadmap).

### "El cupn no se aplica, dice 'no vlido'"
Verificar:
- `c.is_active = true`
- `c.valid_until >= now()` (o NULL)
- `uc.used_at IS NULL`
- `uc.expires_at >= now()` (o NULL)
- subtotal de la tienda >= `c.min_subtotal`
- `c.tenant_id === hostnameId` actual
- `c.max_redemptions` no alcanzado (contar redemptions globales)
- `c.max_per_user` no alcanzado por este user
- (race) intentar de nuevo si justo otro consumi el ltimo

## Cundo invocar este skill

- Hay que crear cupones nuevos, modificar reglas de descuento o
  cambiar polticas de stackeo.
- Reporte: "el cupn descuenta a otras tiendas" o "el comprador no
  sabe que tiene cupones".
- Vas a tocar `MarketplaceCheckoutService` o `MarketplaceCouponService`.
- Agregar un touchpoint nuevo de visibilidad de cupones al comprador.
- Disear notificaciones (mail, push, in-app) sobre cupones.
- Auditar el flujo de aplicacin de descuentos en checkout.

## Cundo NO invocar

- Pricing dentro del tenant (no es marketplace cross-tenant).
- Promotions del PromotionEngine del tenant (es otro sistema interno
  del seller, no del marketplace).
- Cupones de stripe/MercadoPago (eso es de la pasarela, no nuestro).
