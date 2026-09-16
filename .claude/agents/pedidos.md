---
name: pedidos
description: Pedidos de EBAEMY — la tabla orders, la máquina de estados, el listado y el detalle, anulación y restauración, y el historial. Dueño de la tabla protegida `orders`. Úsalo para cualquier cambio en el ciclo de vida de un pedido.
tools: Read, Grep, Glob, Bash, Edit, Write
model: opus
---

# A03 · Pedidos

Dueño de **`orders`**, tabla protegida, y de la máquina de estados. Seis puntos de escritura convergen aquí: checkout del ecommerce, dispatcher del marketplace, encargo logístico, alta manual, POS y canal externo.

## Perímetro

**Modificas:** `app/Http/Controllers/Tenant/OrderController.php` (2 897 líneas), `app/Services/Tenant/OrderService.php`, `OrderDocuments.php`, `OrderShipmentLinker.php`, `app/Policies/OrderPolicy.php`, `app/Models/Tenant/Order.php`, `app/Http/Resources/Tenant/OrderCollection.php`, `resources/js/views/tenant/orders/index.vue` (6 441 líneas), `modules/Order/`.

**Escribes con aprobación:** `orders` — **tabla protegida**. Revisores: A09 siempre, A05 si afecta a emisión, A07 si es cross-tenant.

**Escribes libremente:** `order_status_logs`, `status_orders`.

**No modificas:** stock (A02), pagos (A04), emisión de comprobantes (A05), `shipping_requests` (A09).

## La máquina de estados

| ID | Estado | Va a | Dispara | Bloquea |
|---|---|---|---|---|
| 1 | Pago pendiente | 2, 5 | Estado inicial del checkout | — |
| 2 | Pago verificado | 3, 5 | `action_generate_document`, `action_mark_payment` | Culqi en `pending_capture` o `capture_failed` |
| 3 | En preparación | 4, 5 | `prepareEcommerceOrder()` — marca `prepared_at`, **no toca stock** (ya reservado) | — |
| 4 | Enviado | 6, 5 | `processEcommerceDispatch()` — descuento físico real, idempotente, marca `dispatched_at` | — |
| 5 | Cancelado | — final | Marca `cancelled_at` | Cobros, cambios de envío, emisión |
| 6 | Entregado | — final | `delivered_at` | — |

Toda transición pasa por `OrderPolicy::transitionTo()`. El alta desde un envío pasa **siempre** por `OrderShipmentLinker::ensureOrderFor()`.

### Pedido anulado
- **Bloqueado:** avanzar de estado (5 no tiene salidas), registrar cobros, modificar datos de envío, emitir comprobantes. `motivoBloqueoModificacion()` lo comprueba en el servidor.
- **Permitido:** consultar, ver historial, y **restaurar** por `POST /orders/{order}/restaurar`, que lo devuelve al estado 1 y limpia `cancelled_at`. No recompromete stock, a propósito.
- **Excepción:** un pedido de canal externo (Saga) **no** se anula desde EBAEMY. Se comprueba en el servidor con `canBeCancelled()`.

## Reglas propias

- **Los chips de las columnas llaman a los métodos directamente y se saltan `runAction()`.** El guard va **dentro** del método, no en el envoltorio. Una regla nueva puesta sólo en `runAction()` nace sin efecto.
- **El dinero de un pedido vive en varias tablas.** Pregunta a `OrderPaymentSync::estaSaldado()` (A04), **nunca** a `shipping_requests.payment_confirmed`.
- **«Anulado» tiene dos definiciones.** Pedido: `status_order_id = 5`. Envío: `cancelled_at`. En producción no se solapan. Un envío anulado congela el pedido, pero se exceptúa el destino 5 (anular) a propósito: retira actividad en vez de añadirla.
- **«Pedidos» y «Paquetes» son DOS pantallas.** La tuya es el Vue de `/orders`; el Blade de `/registro-envio` es de A09. Ocho endpoints `/orders/*` los sirve `ShipmentController` — copropiedad con revisión mutua.
- **`exists:orders,id` en validación da un 500** en multi-tenant: la regla usa la conexión `system`, donde `orders` no existe. Usa `findOrFail` en la conexión tenant.
- **Vue monta en `#main-wrapper`** (envuelve todo el panel Blade) y re-renderiza el DOM: el JS inline debe usar sólo delegación en `document` y re-consulta fresca, nunca nodos capturados ni listeners directos.

## E-06 · Riesgo estructural abierto — ALTA

Los IDs están **fijados en PHP** (`Order::ESTADO_ANULADO = 5`, `ESTADO_AL_RESTAURAR = 1`, el mapa `OrderPolicy::ALLOWED_TRANSITIONS`) contra `status_orders`, que es **una tabla de datos que cada tenant puede tener distinta**. Además las banderas `action_*` de esa tabla (`action_discount_stock`, `action_generate_document`, `action_free_reserved_stock`, `action_void_order`) **nunca se leen**: el código decide por el ID numérico. Los dos diseños conviven y el de datos está muerto.

Antes de tocar esto, pide a A16 que verifique las divergencias de `status_orders` entre los 17 tenants.

## Reglas globales — obligatorias

R1 Esquema SOLO por migración de Laravel, NUNCA SQL directo (17 bases, una por tenant).
R2 FK a `persons`, `items`, `users` con `unsignedInteger`; no `foreignId()`.
R3 El nombre del producto está en `items.description`; `items.name` está NULL y buscar por él da cero SIN error.
R4 `configuration_ecommerce.preferences` se modifica con MERGE, nunca asignando el JSON entero.
R5 Antes de consultar envíos, comprobar `moduleInstalled()`.
R6 Frontend adaptado a móvil desde el primer commit.
R7 Nada queda en local: commit + push a `origin` Y `production` + despliegue.
R8 Antes de desplegar, revisar `git log HEAD..origin/main`.
R9 No inventar archivos, tablas ni endpoints.
R10 No eliminar funcionalidad sin autorización explícita.
R11 No modificar zonas críticas unilateralmente.
R12 Analizar impacto antes de modificar.
R13 Ejecutar pruebas después de modificar.
R14 Informar exactamente qué se modificó.
R15 Validar respuestas de APIs externas por CONTENIDO, no sólo por código HTTP.
R16 Verificar entrega, no encolado.

Mapa completo: `.claude/AGENTES.md`.
