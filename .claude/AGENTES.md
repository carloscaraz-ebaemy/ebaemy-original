# Ecosistema de agentes EBAEMY

Referencia operativa del ecosistema. Deriva de la auditoría del 2026-09-16 sobre `main @ 6b8454dd`.
Cualquier agente puede leer este fichero cuando necesite el mapa completo.

---

## Decisiones de arranque (cambiables)

| # | Decisión | Valor por defecto |
|---|----------|-------------------|
| 1 | Mecanismo de aprobación | El Maestro invoca al revisor como subagente. **Se detiene y pregunta al usuario** en 4 casos: escritura en tabla protegida, ruta pública nueva o modificada, cualquier migración, despliegue. Todo lo demás procede con registro posterior |
| 2 | Registro de cambios | `.claude/registro-cambios.md` |
| 3 | Alcance por tenant | Cambios de **esquema** → los 17 tenants, propagados por A16. Cambios de **datos** → sólo el tenant indicado |
| 4 | Qué se hace con el RBAC (E-01) | **NO DETERMINADO** — es la tarea 6 de A17 y requiere decisión del usuario |
| 5 | Agrupación de SellerSku de Saga en producto padre (E-04) | **NO DETERMINADO** — bloquea el trabajo de variantes de A08 |

---

## Roster

| Código | Agente | Nivel | Zona |
|--------|--------|-------|------|
| A00 | Maestro EBAEMY | solo lectura + coordinación | — |
| A01 | Catálogo | crítica | — |
| A02 | Inventario y Stock | crítica | — |
| A03 | Pedidos | crítica | — |
| A04 | Cobranzas y Caja | crítica | — |
| A05 | Ventas y Comprobantes | crítica | — |
| A06 | Ecommerce del Tenant | controlada | Z5 |
| A07 | Marketplace Central | controlada | Z2 |
| A08 | Canales Externos e Importaciones | crítica | Z6 |
| A09 | Logística y Envíos | controlada | — |
| A10 | Compras y Costos | controlada | — |
| A11 | Precios y Promociones | crítica | — |
| A12 | Maestros y Configuración | controlada | — |
| A13 | Reportes y Analítica | solo lectura | Z1 |
| A14 | Notificaciones | controlada | Z4 |
| A15 | Sorteos y Campañas | controlada | Z3 |
| A16 | Base de Datos y Migraciones | crítica | — |
| A17 | Seguridad | crítica | — |
| A18 | QA y Regresión | controlada | — |
| A19 | Plataforma y Despliegue | crítica | — |

---

## Las 4 tablas protegidas

| Tabla | Dueño | Puerta única | Revisores obligatorios |
|-------|-------|--------------|------------------------|
| `items` | A01 | Servicios de `app/Services/Tenant/`; silenciar `MarketplaceItemObserver` cuando corresponda | A02, A06, A07, A08, A11 |
| `item_warehouse`, `item_variant_warehouse` | A02 | Lectura: `StockQueryService`. Escritura: `StockReservation`, `OrderService` | A06 (reserva del checkout), A03 (descuento del despacho) |
| `orders` | A03 | `OrderPolicy::transitionTo()` + `OrderService`. Alta desde envío: `OrderShipmentLinker::ensureOrderFor()` | A09 siempre; A05 si afecta emisión; A07 si es cross-tenant |
| `order_payments`, `document_payments`, `sale_note_payments`, `cash_document_payments` | A04 | `OrderPaymentSync::estaSaldado()` para leer el saldo. **Nunca** `shipping_requests.payment_confirmed` | A03, A05 |

### Protocolo de 7 pasos (obligatorio para las 4 tablas)

1. Informar qué quiere cambiar — tabla, columnas, filas, si es lectura/escritura/esquema.
2. Explicar por qué — qué solicitud lo motiva y por qué no cabe en su propio perímetro.
3. Analizar dependencias — qué observers se disparan, qué servicios leen esa tabla.
4. Identificar módulos afectados — para `items`, siempre los siete dominios que la consumen.
5. Obtener revisión del dueño + revisores obligatorios.
6. Ejecutar pruebas — suite de la tabla + regresión de zonas dependientes, validada por A18.
7. Registrar el cambio en `.claude/registro-cambios.md`.

---

## Ficheros de dueño exclusivo

| Fichero / carpeta | Dueño | Los demás |
|-------------------|-------|-----------|
| `database/migrations/` | A16 | Proponen |
| `routes/web.php`, `routes/api.php` | A19 | Proponen |
| `app/Http/Middleware/`, `app/Policies/`, `config/auth.php` | A17 | Proponen |
| `tests/` | A18 | Proponen |

---

## Orden de ejecución con varios agentes

```
1. A16 Migraciones ......... serializado, nunca concurrente
2. A01 Catálogo ............ antes que stock, marketplace y canales
3. A02 Inventario .......... antes que pedidos
4. A03 Pedidos ............. antes que pagos, documentos y envíos
5. A04 · A05 · A09 ......... en paralelo entre sí
6. A13 · A14 · A15 ......... en paralelo con todo
7. A18 QA .................. tras cada agente y al final
8. A19 Despliegue .......... serializado, último
```

## Dependencias circulares y cómo se rompen

| Ciclo | Regla |
|-------|-------|
| A01 ↔ A02 | A01 escribe primero, A02 después. Nunca en la misma transacción |
| A01 ↔ A11 | A11 escribe **sólo** las columnas de precio de `items`; A01 nunca las toca |
| A03 ↔ A09 | `OrderShipmentLinker::ensureOrderFor()` es la única puerta, y pertenece a A03 |
| A03 ↔ A04 | A04 expone `estaSaldado()`; A03 sólo lee esa función |
| A07 ↔ A08 | A08 silencia el observer durante la importación; la sincronización de vuelta la arbitra A00 |

---

## Qué ocurre cuando

| Situación | Resolución |
|-----------|------------|
| Dos agentes necesitan el mismo archivo | Se serializan; el dueño va primero. Si es `routes/web.php`, una migración o `tests/`, lo edita su dueño transversal con ambos cambios juntos |
| Dos agentes necesitan la misma tabla | Sólo escribe el dueño. El otro presenta solicitud. Si es tabla protegida, protocolo de 7 pasos |
| Una modificación afecta otro módulo | A00 amplía la asignación **antes** de implementar: el agente afectado entra como revisor |
| QA encuentra un error | A18 devuelve al dueño con el caso reproducible. A18 no arregla código de producción |
| La prueba falla | La tarea no se cierra. No se despliega con la suite en rojo |
| Un agente no tiene permisos | Se detiene y escala a A00. **Nunca** procede por su cuenta |
| Dependencia circular | A00 aplica el orden fijo de arriba; si es nueva, la declara y la documenta aquí |

---

## Zonas aisladas

| Zona | Agente | Verifica además |
|------|--------|-----------------|
| Z1 Reportes | A13 | Que `ebaemy_warehouse` exista antes de tocar Analytics |
| Z2 Marketplace central | A07 | El dispatcher cross-tenant y el orden `relevance` (intercala tiendas a propósito) |
| Z3 Sorteos | A15 | Que la notificación al ganador se **entregue** (A14) |
| Z4 Notificaciones | A14 | Que `queue:work` esté vivo en producción (A19) |
| Z5 Temas | A06 | Los 8 temas; el precio de oferta se renderiza distinto en cada uno |
| Z6 Saga y canales | A08 | `items` con A01, `item_warehouse` con A02, y que el observer quedó silenciado **y restaurado** |

---

## Cartera inicial — hallazgos abiertos de la auditoría

| ID | Módulo | Error | Prioridad | Dueño |
|----|--------|-------|-----------|-------|
| E-01 | Global | RBAC aplicado en 3 de 1 281 rutas | CRÍTICA | A17 |
| E-02 | Envíos | `GET /envio/cliente/{dni}` filtra nombre, teléfono y dirección | CRÍTICA | A17 + A09 |
| E-03 | Saga | Importación «exitosa» con 0 productos: `handleResponse()` sólo mira el código HTTP | ALTA | A08 |
| E-04 | Saga | Las variantes entran como productos sueltos (`item_variant_id` siempre null) | ALTA | A08 |
| E-05 | Envíos | `GET /envio/guia/{code}` sirve la guía por código enumerable | ALTA | A17 + A09 |
| E-06 | Pedidos | Estados fijados en PHP contra la tabla de datos `status_orders` | ALTA | A03 |
| E-07 | Precios | `floor_price` se calcula pero no frena ninguna venta | ALTA | A11 |
| E-08 | Ventas | Precio de línea protegido sólo en el navegador | ALTA | A17 + A11 |
| E-09 | Saga | Posible bucle infinito de lotes (`done = fetched < limit`) | MEDIA | A08 |
| E-10 | Saga | Categorías y marcas se crean sin normalizar | MEDIA | A08 |
| E-11 | Saga | El almacén de destino depende del usuario logueado | MEDIA | A08 |
| E-12 | Logística | `logistics.manage_couriers` no existe en el catálogo de permisos | MEDIA | A17 |
| E-13 | Marketplace | `marketplace_products` sin índice único (channel_id, external_sku) | MEDIA | A16 |
| E-14 | Envíos | Consulta pública de RUC/DNI contra servicio de pago | MEDIA | A17 |
| E-15 | Saga | Cobertura parcial: `FalabellaService` y `SagaProductPayloadBuilder` sí tienen test desde 2026-06-19; `FalabellaImportService` no tuvo ninguno hasta el 2026-09-16. E-03 sigue sin cubrir | MEDIA | A18 |
| E-16 | BD | 3 migraciones registradas sin fichero | MEDIA | A16 |
| E-17 | Infra | Caché y sesión en `file` con Redis disponible | MEDIA | A19 |
| E-18 | Código | `sale_notes copy/` y 8 ficheros `.bak`/`_old` | BAJA | dueño de cada zona |
| E-19 | Reportes | `ebaemy_warehouse` se crea a mano | BAJA | A13 + A16 |
| E-20 | Módulos | `modules/Company` activo y vacío | BAJA | A19 |

---

## Recomendación de activación por olas

1. **Ola 1 — marco de control:** A00, A17, A18, A19
2. **Ola 2 — problemas abiertos:** A08, A09
3. **Ola 3 — núcleo de datos:** A01, A02, A03, A04, A05
4. **Ola 4 — el resto:** A06, A07, A10, A11, A12, A13, A14, A15, A16
