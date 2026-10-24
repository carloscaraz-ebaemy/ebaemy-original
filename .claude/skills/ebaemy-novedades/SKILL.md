---
name: ebaemy-novedades
description: Cómo anunciar y explicar DENTRO del panel una función nueva o cambiada para que el cliente (dueño de tienda o su personal) la entienda sin capacitación. Invocar SIEMPRE al terminar un cambio que el cliente vaya a ver o usar — un botón, columna, lápiz, diálogo, estado, regla de precio/stock/oferta nueva — y ANTES de darlo por terminado o desplegarlo. También cuando el usuario pregunte «¿cómo se lo decimos a los clientes?», «que no sea confuso», «sin capacitación», o pida una guía, un tour, un aviso de novedad o textos de ayuda.
---

# Novedades sin capacitación — EBAEMY

Una función que el cliente no descubre, o que descubre y no entiende, no existe. Y en
EBAEMY no hay capacitación: el dueño de la tienda abre el panel desde el celular entre
venta y venta. Esta skill convierte cada cambio visible en **una propuesta concreta de
cómo explicarlo en la propia pantalla**, que el usuario (Carlos) aprueba antes de
desplegar.

## Cuándo aplica

**Sí:** botón, lápiz, columna o filtro nuevo · un diálogo o formulario que cambia · una
regla que cambia lo que el cliente ve (precio, oferta, stock, estado de pedido) · algo
que se mueve de sitio · algo que antes no se podía hacer y ahora sí.

**No:** arreglos invisibles (rendimiento, un bug que nadie notaba), cambios solo de
backend, el marketplace público (eso lo lee el comprador; ver `marketplace-ads-seo`).

Si dudas, aplica: preguntar cuesta una línea; un cliente confundido cuesta un ticket.

## El flujo (5 pasos, en orden)

### 1 · Detectar qué puede confundir
Por cada cambio visible, contesta por escrito:
- **¿Lo va a encontrar?** ¿Está donde lo buscaría, o es un icono sin texto?
- **¿Sabe para qué sirve?** ¿El icono/etiqueta lo dice, o hace falta explicarlo?
- **¿Sabe qué pasa al usarlo?** ¿Es reversible? ¿Afecta a la tienda, al marketplace,
  a SUNAT? ¿Queda registrado?
- **¿Choca con lo que ya conocía?** Algo que antes estaba en otro sitio, o que antes
  hacía otra cosa (el caso típico: «el stock antes se cambiaba en Inventario»).
- **¿Un mismo dato se ve igual en todos los canales?** Panel, tienda del tenant y
  marketplace. Si el panel dice «oferta» y la tienda no la muestra, eso no se arregla
  con un texto: es un bug y se arregla antes (ver `ItemVariant::offerFor()`).

### 2 · Elegir las piezas (de menos a más intrusiva)

| Pieza | Cuándo | Cómo |
|---|---|---|
| **Texto en el propio control** | Siempre que quepa | Etiqueta o `title` claro en vez de icono solo |
| **Tooltip** | Un dato con matices (estado, fechas, «heredado») | `el-tooltip` con 2-4 líneas |
| **Texto de ayuda** bajo un campo | Una regla al rellenar («debe ser menor al normal») | `<small class="text-muted">` |
| **Etiqueta «NUEVO»** | Algo nuevo en un sitio que ya conocían | Pastilla junto a la cabecera, con **fecha de caducidad** (4-6 semanas) |
| **Guía de novedades** (spotlight) | Función nueva que cambia cómo trabajan, o difícil de descubrir | `FeatureTour.vue`, 1-3 pasos, una vez por usuario |
| **Estado vacío** | Pantalla o lista que puede salir vacía | Qué es esto + qué hacer ahora (un botón) |

Regla: **la menos intrusiva que resuelva la duda.** Una guía para un tooltip cansa y
enseña a cerrar guías sin leerlas.

### 3 · Proponer al usuario ANTES de implementar
Con `AskUserQuestion`, una sola vez, con la propuesta ya redactada (los textos
exactos, no «añadir una guía»). Formato recomendado:
- Pregunta 1 — **qué piezas** (multiSelect): guía · NUEVO · tooltips · nada.
- Pregunta 2 — solo si hay guía: **los pasos**, con el texto de cada globo en la
  descripción de la opción.
- Avisos fuera del sistema (WhatsApp/correo a los tenants) **nunca** por defecto: el
  2026-10-22 el usuario eligió «solo dentro del sistema». Ofrecerlo solo si lo pide.

### 4 · Implementar
Ver «Cómo se monta» abajo. Textos según «Cómo se escribe».

### 5 · Verificar de verdad
Captura en escritorio (1440) **y** móvil (390) con puppeteer contra el servidor local,
recorriendo la guía entera. Guiones listos en `C:/tmp/shot/` (`tour.js`,
`variantes.js`): login local `admin@gmail.com` del tenant demo, servidor
`php artisan serve --host=0.0.0.0 --port=8123`, URL `http://demo.ebaemy-original.test:8123`.
Comprobar: sale sola la primera vez · avanza · se guarda · al recargar no vuelve ·
«¿Qué hay de nuevo?» la reabre · el globo no tapa el elemento ni se parte en móvil.
Antes de capturar, borrar la marca: `delete from user_feature_tours` en
`ebaemyoriginal_demo` (solo local).

## Cómo se escribe (microcopy)

- **Español de Perú, de tú**, frases cortas. Verbo primero: «Toca el lápiz para…».
- **Qué hace + qué gana**: «Ajusta el stock desde aquí, sin ir a Inventario».
- **Qué pasa después** si importa: «Queda registrado en el kardex y se actualiza en tu
  tienda y en el marketplace». Da confianza para tocarlo.
- **Sin jerga interna**: nada de `variant`, `compare_at`, `sync`, `listing`, `SKU`
  sin explicar. Términos del negocio que el cliente ya usa (kardex, boleta, NV) sí.
- **Títulos de guía ≤ 6 palabras; cuerpo ≤ 45 palabras.** Si no cabe, son dos pasos.
- **Botones con el verbo de lo que hacen**: «Guardar», «Entendido», no «OK».
- **Estados con nombre humano**: «Programado», «Vencido», «del producto», no `scheduled`.

## Cómo se monta

### Guía de novedades — `resources/js/components/FeatureTour.vue`

```vue
<feature-tour
    ref="tour"
    tour-key="pantalla-novedad-AAAA-MM"   <!-- único; una clave nueva vuelve a salir -->
    :steps="[
      { target: '.clase-estable', title: '…', body: '…' },
    ]"
></feature-tour>
<button @click="$refs.tour.start()">✨ ¿Qué hay de nuevo?</button>
```

- **`target`**: una clase propia y estable puesta a propósito en el elemento
  (`ie-stock-edit`), nunca una clase de Element UI ni un `:nth-child`. Se usa el primer
  elemento visible; los pasos sin elemento en pantalla se saltan solos.
- Guarda «visto» **en la cuenta** (`user_feature_tours`, `GET/POST /feature-tours/{key}`),
  no en el navegador: no reaparece al cambiar de dispositivo.
- Reintenta ~12 s si la tabla aún carga. «No volver a mostrar» y «Entendido» la marcan.
- Tenant sin la tabla → se da por vista (nunca bloquea la pantalla).

### Etiqueta «NUEVO» con caducidad

```js
const NOVEDAD_X_HASTA = new Date('AAAA-MM-DDT23:59:59');   // 4-6 semanas
data() { return { showNewBadge: new Date() <= NOVEDAD_X_HASTA } }
```
Estilo: `.ie-new-pill` en `items_ecommerce/index.vue` (pulso que respeta
`prefers-reduced-motion`). Copiarlo, no reinventarlo.

## Registro de novedades publicadas

Añadir una fila por cada guía o NUEVO desplegado. Sirve para no repetir claves y para
saber qué caduca.

| Fecha | Pantalla | Clave / pieza | Pasos | Caduca |
|---|---|---|---|---|
| 2026-10-22 | Tienda virtual → Productos | `tienda-productos-2026-10` | lápiz de stock · flecha de variantes | NUEVO en Stock General hasta 2026-11-30 |
| 2026-10-22 | Tienda virtual → Productos | `tienda-productos-2026-10-v2` (sustituye a la anterior) | lápiz de stock · variantes con lápiz propio de precio/oferta/stock y estados de oferta | — |
| 2026-10-24 | Inicio (Panel Operativo) + Productos | `aviso-marketplace-2026-10` (tarjeta) · `publicar-marketplace-2026-10` (globo) | «Tus productos pueden llegar a más clientes» → señala «Publicar todos en Marketplace». Solo admin con productos sin publicar (`items/marketplace-invite`) | — |
| 2026-10-24 | Inicio + Productos | `aviso-envios-2026-10` (tarjeta, prioridad sobre la de publicar) | «Confirma cómo envías tus productos» → `/items_ecommerce?envios=1` abre el formulario. Solo admin de tiendas que venden en línea sin política confirmada. No se marca al pulsar: vuelve hasta que la guarde | — |
| 2026-10-24 | Inicio + Productos | `aviso-cambios-2026-10` (tarjeta, entre envíos y publicar) | «Publica tu política de cambios» → `/items_ecommerce?cambios=1` abre el formulario. Solo tiendas que venden en línea sin `preferences.returns_policy`. No se marca al pulsar | — |

**Ojo:** el Inicio real es `modules/Dashboard/.../DashV2.vue` («Panel Operativo»), no `views/index.vue`, que ya no se monta.

## Reglas duras

❌ **NUNCA** desplegar un cambio visible sin haber pasado por el paso 3 (propuesta al usuario).
❌ **NUNCA** usar una guía para tapar un dato incoherente entre canales: eso se arregla.
❌ **NUNCA** enviar avisos fuera del sistema (WhatsApp, correo) sin que el usuario lo pida.
❌ **NUNCA** reutilizar una `tour-key`: los que ya la vieron no verían la novedad nueva.
✅ **SIEMPRE** probar la guía en móvil: es donde más se usa el panel.
✅ **SIEMPRE** poner fecha de caducidad a una etiqueta NUEVO.
