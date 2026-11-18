---
name: ebaemy-errores
description: Cómo detectar, priorizar y corregir los errores de producción de EBAEMY (500, 502, 503, PDF que no imprime, pantallas que se quedan cargando). Invocar cuando el usuario reporte «error 500», «sale error», «no imprime», «no descarga el PDF», «se queda cargando», «no deja emitir», cuando se pida «búscame errores en todo el sistema», después de cada despliegue, y antes de dar por cerrado un arreglo de backend. Trae dos scripts de solo lectura: el informe de errores (nginx + Laravel + PHP + cola) y la prueba de impresión de PDF de todas las tiendas.
---

# Errores de producción — EBAEMY

Un 500 que el cliente ve casi nunca está solo: la misma causa suele romper otras
pantallas o documentos que nadie abrió todavía. El objetivo no es apagar el 500
reportado, sino **encontrar la clase de error y cerrarla en todo el sistema**.

## Herramientas (solo lectura)

### 1. Informe de errores
```bash
scp -q .claude/skills/ebaemy-errores/scripts/error-report.sh ebaemy@ebaemy.com:/tmp/er.sh
ssh ebaemy@ebaemy.com 'sudo -n bash /tmp/er.sh 14; rm -f /tmp/er.sh'
```
- **Sección 1:** 5xx de nginx por ruta (ids y uuids normalizados): QUÉ pantalla falla.
- **Sección 2:** errores de Laravel por mensaje, con primera y última fecha: POR QUÉ falla, y si sigue pasando.
- **Sección 2b:** archivo:línea de la app más repetidos: DÓNDE se arregla.
- **Sección 3:** fatales de PHP que no llegan a Laravel (`/var/log/php-app/errors.log`).
- **Sección 4:** jobs fallidos de la cola.

### 2. Prueba de impresión de PDF en todas las tiendas
```bash
scp -q .claude/skills/ebaemy-errores/scripts/pdf-check.php ebaemy@ebaemy.com:/tmp/pdf-check.php
ssh ebaemy@ebaemy.com 'cd /home/ebaemy/ebaemy/laravel && PDF_N=15 php artisan tinker --execute="require \"/tmp/pdf-check.php\";"; rm -f /tmp/pdf-check.php'
```
Genera A4 y ticket de las últimas N notas de venta y comprobantes de cada tienda, con el
mismo criterio que la web, y lista cuáles fallarían. Usar tras tocar plantillas, snapshots
JSON (`customer`, `establishment`, `items`) o servicios que creen documentos.

## Cómo leer el informe

| Señal | Significa | Acción |
|---|---|---|
| 503 en `/ecommerce*` | Tienda **bloqueada** (`LockedTenant`), por diseño | Ninguna. Confirmar con `configurations.locked_tenant` |
| 502 aislado | php-fpm reiniciándose durante un despliegue | Solo si se repite fuera de un despliegue |
| Mensaje con última fecha anterior a un arreglo | Ya resuelto | Comprobar con `git log` del archivo y descartarlo |
| `[SaleNote PDF] …` en el log | La impresión de la NV falló | Ver «snapshots JSON» abajo |
| Mensaje de validación de negocio (p. ej. SUNAT) repetido | No es un fallo: el texto no dice qué hacer | Reescribir el mensaje para que diga la salida |

**Prioridad:** P0 = bloquea vender, emitir o imprimir (POS, `/documents`, NV, checkout).
P1 = pantalla de gestión rota. P2 = un caso raro o un mensaje confuso.

## Trampas de este proyecto (causas reales ya vistas)

1. **Laravel escribe en DOS archivos**: `laravel.log` y `laravel-AAAA-MM-DD.log`. Si el diario
   está vacío, el error está en `laravel.log`. El script lee los dos.
2. **Warnings = 500 en la web, pero no en tinker.** «Undefined property», «non-numeric value» y
   «Undefined array key» rompen la petición web; en tinker solo se imprimen como WARNING y
   parece que funciona. Para reproducir: `set_error_handler` que lance `ErrorException`
   salvo E_DEPRECATED (lo hace `pdf-check.php`).
3. **Snapshots JSON incompletos rompen TODOS los PDF.** `sale_notes.establishment`,
   `.customer` y `sale_note_items.item` se guardan como JSON y las 277 plantillas leen
   `->district->description`, `->identity_document_type->description`, `->name`… como
   objetos. Todo servicio que cree un documento debe usar `EstablishmentInput::set` y
   `PersonInput::set`, igual que `SaleNoteController`. Caso 2026-11-18:
   `OrderToSaleNoteService` usaba `toArray()` y las NV creadas desde Pedidos no imprimían.
4. **`new Controlador()` sin argumentos.** Si el constructor pide un servicio, da «Too few
   arguments». Usar `app(Controlador::class)`. Buscar con
   `grep -rn "new [A-Za-z]*Controller()" app modules`.
5. **`soap_type_id` y correlativos.** Las NV se numeran por serie + `soap_type_id` de la
   EMPRESA. Si un servicio guarda `'01'` (demo) estando la empresa en `'02'`, la pantalla
   normal no ve esa nota y repite su número.
6. **Entradas de texto en campos numéricos.** El POS mandaba «4vela» como cantidad cuando el
   foco se quedaba en la cantidad. Validar en las dos puntas: limpiar en Vue y responder un
   mensaje claro en el servidor (nunca dejar que la resta lance).
7. **tinker por SSH no acepta bloques `try` en varias líneas.** Copiar el script a `/tmp` y
   ejecutarlo con `php artisan tinker --execute="require \"/tmp/x.php\";"`.
8. **Imprimir pide sesión:** `curl` a `/sale-notes/print/...` da 403. Probar dentro del
   servidor con `auth()->loginUsingId()` y el controlador.

## Procedimiento ante un error reportado

1. **Reproducir con datos reales**: ruta en nginx → mensaje en Laravel → archivo:línea.
2. **Encontrar la clase**: ¿qué otros registros, pantallas o tenants tienen la misma causa?
   (p. ej. todas las NV con `order_id` y el establecimiento incompleto).
3. **Corregir el origen**, no solo la vista que falló.
4. **Reparar los datos ya dañados** con un script idempotente: primero simulación, después
   aplicar, y nunca renumerar ni cambiar documentos enviados a SUNAT
   (`feedback_no_tocar_pedidos_enviados`).
5. **Verificar en producción** con el mismo criterio que la web (`pdf-check.php` o
   `set_error_handler`) y volver a correr `error-report.sh`.
6. Commit, push a los dos remotos y despliegue (skill `ebaemy-deploy`).

## Checklist antes de cerrar

- [ ] El error reproducido y su clase identificada en todo el sistema.
- [ ] Origen corregido; `php -l` y `view:cache` limpios.
- [ ] Datos dañados reparados, o la razón por la que no se tocaron.
- [ ] `pdf-check.php` sin fallos si el cambio toca documentos.
- [ ] `error-report.sh` después del despliegue sin el mensaje.
