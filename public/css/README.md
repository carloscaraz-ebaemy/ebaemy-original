# ebaemy Design System — v1

Sistema de diseño coherente para ebaemy. **Aditivo y opt-in** — no rompe nada
existente. Coexiste con Bootstrap 5, Element UI y el tema legacy Porto.

## Archivos

| Archivo | Propósito | Tamaño |
|---|---|---|
| `design-tokens.css` | Variables CSS (`--eb-*`) | ~4 KB |
| `eb-components.css` | Componentes reutilizables (`.eb-*`) | ~10 KB |
| `auth.css` | Login (usa los tokens) | ~10 KB |

## Cómo activar en una vista

Solo agregar 2 `<link>` antes del resto del CSS:

```blade
<link rel="stylesheet" href="{{ asset('css/design-tokens.css') }}">
<link rel="stylesheet" href="{{ asset('css/eb-components.css') }}">
```

**No hace falta remover nada** — las clases `.eb-*` conviven con `.btn`,
`.card`, `.form-control` de Bootstrap.

## Uso por componente

### Botones

```html
<button class="eb-btn eb-btn-primary">Guardar</button>
<button class="eb-btn">Cancelar</button>
<button class="eb-btn eb-btn-danger-outline eb-btn-sm">Eliminar</button>
<button class="eb-btn eb-btn-ghost">Ver más</button>
<button class="eb-btn eb-btn-primary eb-btn-block eb-btn-lg">Iniciar sesión</button>
```

Variantes: `eb-btn-primary`, `eb-btn-accent`, `eb-btn-ghost`, `eb-btn-danger`, `eb-btn-danger-outline`
Tamaños: `eb-btn-sm`, `eb-btn-lg`, `eb-btn-xl`
Modificadores: `eb-btn-block`, `eb-btn-icon`

### Inputs

```html
<label class="eb-label">Correo electrónico</label>
<input type="email" class="eb-input" placeholder="tucorreo@empresa.com">
<span class="eb-field-hint">Usaremos este correo para enviar notificaciones</span>

<!-- Error -->
<input type="email" class="eb-input is-invalid">
<span class="eb-field-error">Correo no válido</span>
```

### Cards

```html
<div class="eb-card eb-card-hover">
  <div class="eb-card-header">
    <h3 class="eb-card-title">Pedidos hoy</h3>
    <span class="eb-badge eb-badge-brand">+12</span>
  </div>
  <p>Contenido del card...</p>
  <div class="eb-card-footer">
    <button class="eb-btn eb-btn-primary">Ver todo</button>
  </div>
</div>
```

### Badges

```html
<span class="eb-badge eb-badge-success eb-badge-dot">Pagado</span>
<span class="eb-badge eb-badge-warning">Pendiente</span>
<span class="eb-badge eb-badge-danger">Cancelado</span>
<span class="eb-badge eb-badge-brand">Nuevo</span>
```

### Chips

```html
<div class="d-flex" style="gap: 8px; flex-wrap: wrap;">
  <span class="eb-chip is-active">Todos</span>
  <span class="eb-chip">Pendientes</span>
  <span class="eb-chip">Pagados</span>
</div>
```

### Alertas

```html
<div class="eb-alert eb-alert-success">
  <strong class="eb-alert-title">✓ Pedido creado</strong>
  <p class="eb-alert-body">El pedido #123 se generó correctamente.</p>
</div>

<div class="eb-alert eb-alert-danger">
  <p class="eb-alert-body">No se pudo procesar el pago. Inténtalo de nuevo.</p>
</div>
```

### Skeleton (loading)

```html
<div class="eb-card">
  <div class="eb-skeleton eb-skeleton-title mb-2"></div>
  <div class="eb-skeleton eb-skeleton-text mb-1"></div>
  <div class="eb-skeleton eb-skeleton-text" style="width:80%"></div>
</div>
```

## Temas por tenant

Activar una paleta alterna en el body del layout:

```blade
<body data-tenant-theme="{{ $tenant->theme_name ?? 'default' }}">
```

Temas disponibles: `gold`, `violet`, `blue`. (Default = turquesa)

## Usar los tokens en CSS propio

Dentro de cualquier `.css` o `<style>`, podés usar las variables:

```css
.mi-componente {
  color: var(--eb-brand);
  background: var(--eb-surface-soft);
  padding: var(--eb-space-4);
  border-radius: var(--eb-radius);
  box-shadow: var(--eb-shadow-md);
  transition: all var(--eb-duration) var(--eb-ease);
}
```

## Referencia rápida de tokens

| Categoría | Variables |
|---|---|
| **Marca** | `--eb-brand`, `--eb-brand-dark`, `--eb-brand-light`, `--eb-brand-soft` |
| **Acento** | `--eb-accent`, `--eb-accent-dark`, `--eb-accent-light`, `--eb-accent-soft` |
| **Neutrales** | `--eb-ink`, `--eb-ink-soft`, `--eb-muted`, `--eb-line`, `--eb-surface` |
| **Estados** | `--eb-success`, `--eb-warning`, `--eb-danger`, `--eb-info` (+ `-soft`) |
| **Tipografía** | `--eb-font`, `--eb-text-{xs,sm,base,md,lg,xl,2xl,3xl}` |
| **Espaciado** | `--eb-space-{1,2,3,4,5,6,8,10,12,16,20}` (base 4px) |
| **Radios** | `--eb-radius-{sm,,md,lg,xl,full}` |
| **Sombras** | `--eb-shadow-{sm,,md,lg,xl}` + `--eb-shadow-brand` |
| **Transiciones** | `--eb-ease`, `--eb-duration-{fast,,slow}` |

## Roadmap

- [x] v1: tokens + componentes base (botones, inputs, cards, badges, chips, alerts)
- [ ] v2: tabla (`eb-table`), modal wrapper, dropdown, tooltip
- [ ] v3: product-card, cart-dropdown, price-tag (ecommerce)
- [ ] v4: dashboard (sidebar, topbar, stat-card)

## Preguntas / issues

Ver `MEMORY.md` → `project_design_system.md` para decisiones de diseño.
