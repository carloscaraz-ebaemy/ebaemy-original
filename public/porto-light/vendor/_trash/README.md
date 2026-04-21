# _trash — Librerías CSS/JS sin uso (candidatas a eliminar)

**Fecha de movimiento:** 2026-04-20
**Audit ref:** `scripts/audit-css-usage.sh` — 0 referencias en `resources/views`, `modules`, `resources/js`.

## Librerías aquí guardadas

| Librería | Tamaño | Razón de eliminar | Reemplazo actual |
|---|---|---|---|
| `summernote` | 336K | Editor WYSIWYG legacy | `@ckeditor/ckeditor5-build-classic` (npm) |
| `codemirror` | 464K | Editor de código sin uso | `@ckeditor/ckeditor5` para contenido |
| `chartist` | 188K | Librería de gráficos | `chart.js` + `vue-chartjs` (npm) |
| `simple-line-icons` | 480K | Set de iconos | FontAwesome 5.11 (`porto-light/vendor/font-awesome`) |
| `elusive-icons` | 440K | Set de iconos | FontAwesome 5.11 |
| `bootstrap-datepicker_bk` | 84K | Backup viejo del datepicker activo | `bootstrap-datepicker` (sigue activo) |
| `jquery-mockjax` | 32K | Librería de testing/mocks | No debería estar en producción |

**Total movido:** ~2.0 MB

## Plan de eliminación definitiva

- **Fecha objetivo:** 2026-05-05 (2 semanas después del movimiento)
- **Criterio:** si nadie reporta issues en este tiempo, eliminar permanentemente con un commit dedicado.

### Si alguien reporta un issue

Si alguna view/módulo referencia alguna de estas libs (quizás con carga dinámica que el grep estático no detectó), restaurar con:

```bash
cd public/porto-light/vendor
git mv _trash/<lib>/ <lib>/
git commit -m "revert: restaurar <lib> — uso detectado"
```

## Cómo eliminar permanentemente (después del 2026-05-05)

```bash
cd public/porto-light/vendor
rm -rf _trash/
git add -A
git commit -m "chore(css): eliminar libs Porto sin uso (2026-04-20 audit)"
git push origin main
```
