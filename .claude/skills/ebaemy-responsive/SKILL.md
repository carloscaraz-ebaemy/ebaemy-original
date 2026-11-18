---
name: ebaemy-responsive
description: Cómo hacer que TODO lo que se implementa en EBAEMY se vea y funcione en cualquier dispositivo (360 px a escritorio) y cómo comprobarlo con medición real en Chrome headless. Invocar SIEMPRE que se cree o toque una vista Blade, un componente Vue, un .css/.scss o un tema de la tienda (marketplace, escaparate del tenant, panel, SuperAdmin), y cuando se reporte «no se ve bien en el celular», «se sale de la pantalla», «no puedo tocar el botón», «el menú/modal no deja elegir», «la tabla no cabe» o «hace zoom al escribir». También antes de dar por terminado o desplegar cualquier cambio visual.
---

# Adaptable a cualquier dispositivo — EBAEMY

La mayoría del tráfico llega por WhatsApp y se abre en un celular. Una vista que solo
funciona en escritorio no funciona. Esta skill fija **qué** se exige, **cómo se mide**
(no a ojo) y **qué trampas** de este proyecto rompen la adaptabilidad.

## Anchos de referencia

| Ancho | Representa | Notas |
|---|---|---|
| **360** | Android pequeño (el más común en Perú) | el caso más duro: aquí se rompe todo primero |
| **390** | iPhone 12-15 | iOS: zoom en inputs < 16 px, `safe-area` abajo |
| **768** | Tablet vertical | frontera de Bootstrap `md`; muchos menús cambian aquí |
| **1024** | Tablet horizontal / portátil pequeño | frontera de `lg`; sidebars aparecen |
| **1366** | Escritorio típico | |

Breakpoints del proyecto: `@media (max-width: 600px)` móvil, `@media (min-width: 900px)`
escritorio; el drawer de Categorías cambia a 700 px; el panel (`erp-redesign.scss`) a 480/767/991.

## Reglas (obligatorias)

1. **Sin scroll horizontal** en ningún ancho: `document.documentElement.scrollWidth <= clientWidth`.
   Y sin contenido *cortado*: `body{overflow-x:hidden}` esconde el scroll pero no arregla
   nada — el elemento sigue saliéndose y no se ve entero (lo detecta el script como «Contenido cortado»).
2. **Objetivos táctiles ≥ 44×44 px** en móvil (botón, enlace, icono, chip, paginación, bullets
   de carrusel). Si el dibujo debe ser pequeño, agranda el área: `min-width/min-height:44px`
   o un `::after` con `inset:-6px`. Excepción: enlaces en línea dentro de un párrafo.
3. **Inputs, selects y textareas con `font-size: 16px` o más en móvil** — con menos, iOS hace
   zoom al enfocar y la página queda descuadrada. Aplica también a `<select>` de ordenar.
4. **Texto ≥ 12 px** (badges y precios tachados incluidos). Ojo con `rem` en la tienda: ver trampas.
5. **Modales y diálogos a pantalla completa en móvil** (`width:100%` / `fullscreen` < 600 px),
   nunca más anchos que el viewport. Cuerpo con `overflow-y:auto` y `max-height` en `dvh`.
6. **Tablas → tarjetas en móvil.** Una tabla de más de 3-4 columnas no se encoge: se convierte
   en tarjetas apiladas (`display:block` por fila con `data-label`) o, como mínimo, scroll-x
   dentro de su contenedor con sombra que indique que hay más.
7. **Imágenes con `object-fit`** (`cover` en miniaturas cuadradas, `contain` en la ficha) y
   `aspect-ratio` o `width/height` para no deformar ni mover el layout. Nunca `width` y `height`
   fijos sin `object-fit`. `max-width:100%` siempre.
8. **`safe-area`**: todo lo `position:fixed` pegado abajo (barra de compra, consentimiento,
   FAB, bottom-sheet) suma `env(safe-area-inset-bottom)` al padding; y `viewport-fit=cover`
   en el `<meta viewport>` si se usa.
9. **Overlays: lo abierto es lo único que recibe clics.** Un drawer/modal abierto debe estar
   por encima de cualquier otro overlay (z-index explícito en el contenedor Y en el panel si el
   backdrop es hermano posterior), y al abrirse cierra los otros overlays automáticos.
10. **Listas horizontales** (chips, pestañas, carruseles) con indicio de que hay más: fade o
    scrollbar visible; nunca esconder el scrollbar sin pista.
11. **Unidades fluidas**: `clamp()`, `min()`, `%`, `dvh/dvw` (no `100vh` en iOS), grid con
    `minmax(0,1fr)` (un `1fr` solo deja que un hijo largo reviente la columna).

## Cómo medir (el script)

`scripts/responsive-audit.js` — Node 20 sin dependencias npm, Chrome por CDP. **Chrome headless
ignora `--window-size` en este equipo** (el escalado de Windows lo deja en ~504 px), por eso el
script usa `Emulation.setDeviceMetricsOverride` y comprueba `clientWidth`: si en el informe sale
`360 (!504)`, la medida no vale.

```bash
cd .claude/skills/ebaemy-responsive/scripts
# Una o varias URLs, los 5 anchos por defecto, captura de página completa:
node --experimental-websocket responsive-audit.js --full --out <scratchpad>/responsive/x \
     https://ebaemy.com/marketplace "ficha|https://ebaemy.com/marketplace/item/plantas-colgantes-maceta-pvc-t1-171"

# Lista en archivo (una por línea, «etiqueta|url», # comenta):
node --experimental-websocket responsive-audit.js --file urls.txt --out <dir>

# Probar un menú/modal ABIERTO: click real en --open y elementFromPoint en el centro de cada opción
node --experimental-websocket responsive-audit.js --widths 390,1366 --open "#mpMegaToggle" \
     --options ".mp-cat-drawer__root, .mp-cat-drawer__child, .mp-cat-drawer__close" --click-option \
     https://ebaemy.com/marketplace

# Panel del tenant (requiere sesión): copiar desde DevTools › Application › Cookies del
# subdominio la cookie que termina en «_session» y XSRF-TOKEN. SOLO lectura: no pulses guardar.
node --experimental-websocket responsive-audit.js --cookie "<nombre>_session=..." --cookie "XSRF-TOKEN=..." \
     https://alasitas.ebaemy.com/items https://alasitas.ebaemy.com/orders https://alasitas.ebaemy.com/pos
```

Salida en `--out`: `report.md` (tabla resumen + detalle por ancho), `report.json` (todo, con
selectores, tamaños y la cadena de stacking del elemento que tapa) y `<etiqueta>_<ancho>.png`
(+ `_open.png`). Chrome se cierra siempre (también con Ctrl+C o error).

Mide: scroll horizontal y elementos que se salen · objetivos táctiles < 44 px (móvil) · inputs
< 16 px · texto < 12 px · imágenes deformadas/desbordadas · interactivos tapados por otro
elemento (`elementFromPoint`) · diálogos más anchos que el viewport · opciones bloqueadas de un
elemento abierto · errores JS.

**Escenarios de varios pasos** (abrir, elegir raíz, pulsar subcategoría…): `require` el script
como módulo — exporta `launch, emulate, navigate, realClick, screenshot, killChrome, PAGE_LIB`.
Inyectar CSS local sobre producción para verificar un arreglo sin desplegar:
`cdp.evalJS("document.head.insertAdjacentHTML('beforeend','<style>…</style>')")`
(si la versión anterior ya está desplegada, las dos reglas se suman: medir de nuevo tras desplegar).

Cómo leer el informe sin perder tiempo:
- Un mismo selector repetido ×24 es UNA regla CSS: arréglalo en la regla, no 24 veces.
- «Tapado por `#mpConsentBar`» es esperado mientras el aviso de cookies está visible, salvo
  que tape un CTA fijo (barra de compra): eso sí es fallo.
- Los primeros 1,5 s tras cargar el marketplace se abre solo el modal de Ofertas del día: si un
  ancho sale con todo «tapado por `.mp-offers-modal__backdrop`», es ese modal.

## Trampas propias del proyecto

- **Element UI `el-dialog` con `width` fijo** (`width="800px"`, `width="60%"`): en 360 px o
  desborda o queda diminuto. `erp-redesign.scss` (~l.1108) fuerza `95%` en móvil, pero un
  `width` en línea con `!important`, `.dialog-import .el-dialog{width:500px!important}`
  (`resources/sass/custom.scss` ~l.74) o `:style` lo anulan. Usa `:fullscreen="isMobile"` o
  `width="min(800px, 100%)"` vía clase; nunca px fijos.
- **`.el-table` en móvil**: no se adapta sola. Columnas fijas (`fixed`) + `min-width` suman
  más de 360 px. Ocultar secundarias con `class-name="el-table-column--hidden-mobile"` (regla en
  `erp-redesign.scss` ~l.1161, hoy sin uso en ningún .vue) o renderizar tarjetas con `v-if="isMobile"`.
  `el-select`/`el-date-picker` con `style="width:300px"` desbordan: usa `width:100%`.
- **Popovers/dropdowns de Element** (`el-select`, `el-popover`, `el-date-picker`) se pintan en
  el `<body>` con z-index propio (2000+): dentro de un drawer o modal custom con z-index mayor
  quedan DEBAJO. Usar `:popper-append-to-body="false"` o subir el z-index del popper.
- **Temas de la tienda** (`resources/views/themes/*`, `public/porto-light/css/themes/*.css`,
  `styles_ecommerce.css`, `ec-mobile.css`): Porto fija `html{font-size:10px}` (62,5 %), así que
  **`1rem` = 10 px, no 16**: `.75rem` son 7,5 px. En la tienda usa `px` o `clamp()` en px.
  Cada tema puede sobrescribir: mide al menos dos temas distintos (p. ej. alasitas y carolayimport,
  que usa `data-theme="dark"`) y el modo oscuro.
- **`body{overflow-x:hidden}` en la tienda**: oculta el desborde pero el contenido queda
  cortado (paginación de Laravel con muchos números, carruseles). Arreglar el elemento.
- **Overlays del marketplace y sus z-index** (consultar antes de añadir otro): consentimiento
  1080, FAB ofertas 1200, modal ofertas 1300/1310, drawer Categorías 1350, barra de compra móvil
  del escaparate 10000. Un backdrop hermano posterior al panel sin z-index se pinta ENCIMA del
  panel (bug de Categorías, 2026-11-17).
- **Drawer teletransportado al `<body>`**: los `position:fixed` dentro de un header `sticky`,
  `transform` o `filter` se calculan contra ese ancestro. Teleport o fuera del header.
- **Cards del marketplace**: 4 vistas comparten la card — usa la skill `marketplace-cards`.

## Checklist antes de dar por terminado un cambio visual

- [ ] Corrí el script sobre cada pantalla tocada en los 5 anchos (el `clientWidth` coincide).
- [ ] 0 scroll horizontal y 0 «contenido cortado» en 360 y 390.
- [ ] Ningún objetivo táctil nuevo < 44 px; ningún input nuevo < 16 px en móvil.
- [ ] Ningún texto nuevo < 12 px (en la tienda, nada en `rem` sin calcular la base 10 px).
- [ ] Si añadí un modal/drawer/menú: probado ABIERTO con `--open/--options`, 0 opciones bloqueadas,
      se cierra con X, backdrop y Escape, y no lo tapa ningún overlay automático.
- [ ] Tablas nuevas → tarjetas o scroll contenido en móvil; diálogos a pantalla completa < 600 px.
- [ ] Imágenes con `object-fit` y proporción reservada; elementos fijos abajo con `safe-area`.
- [ ] Miré las capturas PNG de 360 y 1366 (no solo los números).
- [ ] En la tienda: probado en dos temas (claro y `data-theme="dark"`).
- [ ] Adjunté la tabla del `report.md` (antes/después) al informe para A18 QA.
