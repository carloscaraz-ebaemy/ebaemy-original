# _trash — Librerías CSS/JS sin uso (candidatas a eliminar)

**Fecha de movimiento:** 2026-04-20 (2 fases)
**Audit ref:** `scripts/audit-css-usage.sh` — 0 referencias en `resources/views`, `modules` y `resources/js`.

**Total:** 37 librerías, **~4.2 MB**

## Fase 1 (2026-04-20) — libs con reemplazo obvio

| Librería | Tamaño | Razón | Reemplazo |
|---|---|---|---|
| `summernote` | 336K | Editor WYSIWYG legacy | `@ckeditor/ckeditor5-build-classic` (npm) |
| `codemirror` | 464K | Editor de código sin uso | ckeditor5 |
| `chartist` | 188K | Librería de gráficos | `chart.js` + `vue-chartjs` |
| `simple-line-icons` | 480K | Set de iconos | FontAwesome 5.11 |
| `elusive-icons` | 440K | Set de iconos redundante | FontAwesome 5.11 |
| `bootstrap-datepicker_bk` | 84K | Backup viejo del datepicker activo | `bootstrap-datepicker` (sigue activo) |
| `jquery-mockjax` | 32K | Librería de testing/mocks | No va en producción |

## Fase 2 (2026-04-20) — libs sin uso detectado

### Widgets Bootstrap sin referencias
| Librería | Tamaño | Función |
|---|---|---|
| `bootstrap-colorpicker` | 56K | Selector de color |
| `bootstrap-fileupload` | 8K | Upload de archivos |
| `bootstrap-markdown` | 104K | Editor markdown |
| `bootstrap-maxlength` | 20K | Contador de caracteres |
| `bootstrap-tagsinput` | 28K | Input de tags |
| `bootstrap-wizard` | 12K | Wizard multi-step |

### Librerías de gráficos / visualización
| Librería | Tamaño | Función |
|---|---|---|
| `flot.tooltip` | 24K | Addon tooltips de Flot |
| `fullcalendar` | 500K | Calendario completo |
| `gmaps` | 68K | Helpers Google Maps |
| `jqvmap` | 204K | Mapas vectoriales jQuery |
| `jquery-sparkline` | 124K | Mini-charts inline |
| `jquery.easy-pie-chart` | 12K | Charts circulares |
| `liquid-meter` | 8K | Medidores tipo líquido |
| `morris` | 69K | Charts Morris (legacy) |
| `raphael` | 92K | Motor SVG (dep de charts viejos) |
| `snap.svg` | 84K | Manipulación SVG |
| `snazzy-themes` | 144K | Temas Snazzy Maps |

### jQuery plugins sin uso
| Librería | Tamaño | Función |
|---|---|---|
| `jquery-appear` | 8K | Detector visibilidad elementos |
| `jquery-idletimer` | 12K | Detector de inactividad |
| `jquery-maskedinput` | 12K | Input masks |
| `jquery-nestable` | 20K | Listas anidables drag-drop |
| `jquery-validation` | 48K | Validación formularios |

### Otros
| Librería | Tamaño | Función |
|---|---|---|
| `fuelux` | 8K | UI extras |
| `intercooler-js` | 64K | AJAX declarativo |
| `ios7-switch` | 4K | Switches estilo iOS |
| `isotope` | 36K | Layouts masonry/filtering |
| `jstree` | 344K | Tree view |
| `nprogress` | 12K | Barra de progreso top |
| `pnotify` | 36K | Notificaciones |
| `store2` | 12K | localStorage wrapper |

## Plan de eliminación definitiva

- **Fecha objetivo:** 2026-05-05 (2 semanas después del movimiento)
- **Criterio:** si nadie reporta issues en este tiempo → eliminar permanentemente.

### Si alguien reporta un issue

Restaurar con:
```bash
cd public/porto-light/vendor
git mv _trash/<lib>/ <lib>/
git commit -m "revert: restaurar <lib> — uso detectado"
```

### Para eliminar permanentemente (después del 2026-05-05)

```bash
cd public/porto-light/vendor
rm -rf _trash/
git add -A
git commit -m "chore(css): eliminar 37 libs Porto sin uso (audit 2026-04-20)"
git push origin main
```

## Libs VIVAS que permanecen (30)

Estas libs **SÍ se usan** y permanecen en `public/porto-light/vendor/`:

`animate`, `autosize`, `bootstrap`, `bootstrap-datepicker`, `bootstrap-daterangepicker`, `bootstrap-multiselect`, `bootstrap-timepicker`, `common`, `datatables`, `dropzone`, `flot`, `font-awesome`, `gauge` (1 ref), `hover`, `jquery`, `jquery-browser-mobile`, `jquery-cookie`, `jquery-loading`, `jquery-placeholder`, `jquery-ui`, `jqueryui-touch-punch`, `magnific-popup`, `meteocons` (1 ref), `modernizr`, `moment`, `nanoscroller`, `owl.carousel`, `popper`, `select2`, `select2-bootstrap-theme`.

Las de ≤2 referencias (`gauge`, `meteocons`) se pueden revisar caso por caso más adelante.
