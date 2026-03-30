# Checklist de Deployment a Producción — ebaemy-original

## Pre-Deployment (OBLIGATORIO)

### 1. Credenciales — ROTAR INMEDIATAMENTE
- [ ] Cambiar `MAIL_PASSWORD` en .env (rotar app password de Gmail)
- [ ] Cambiar `TOKEN_SERVER` en .env (generar nuevo token)
- [ ] Cambiar `GOOGLE_CLIENT_SECRET` en Google Cloud Console
- [ ] Cambiar `PUSHER_APP_SECRET` si se usa websockets
- [ ] Verificar que `.env` NO esté en el repositorio git
- [ ] Agregar `.env` a `.gitignore` si no está

### 2. Configuración de Producción
```env
APP_ENV=production
APP_DEBUG=false
FORCE_HTTPS=true
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_LIFETIME=30
CORS_ALLOWED_ORIGINS=https://tudominio.com,https://demo.tudominio.com
QUEUE_CONNECTION=database
CACHE_DRIVER=redis
```

### 3. Certificado SSL
- [ ] SSL configurado para todos los dominios de tenants
- [ ] HSTS habilitado (ya configurado en SecurityHeaders middleware)

---

## Migraciones Pendientes

### Sistema
```bash
php artisan migrate
```

### Tenants (aplicar a TODOS los tenants)
```bash
php artisan tenancy:migrate
```

Migraciones nuevas que se aplicarán:
- `2026_03_24_000001_create_abandoned_carts_table`
- `2026_03_24_000002_add_performance_indexes`
- `2026_03_27_000001_add_critical_performance_indexes`
- `2026_03_27_000002_create_audit_logs_table`
- `2026_03_27_000004_create_rbac_tables`
- `2026_03_27_000005_create_jobs_tables`
- `2026_03_27_000006_seed_rbac_permissions_and_assign_roles`

---

## Queue Workers

Iniciar workers para que los jobs se procesen en background:

```bash
# Supervisor config recomendado
php artisan queue:work database --sleep=3 --tries=3 --max-time=3600
```

Configurar Supervisor (Linux) o Task Scheduler (Windows) para mantener workers activos.

---

## Build Frontend

```bash
npm run build
```

Los assets ya están compilados con lazy loading (302 componentes code-split).

---

## Post-Deployment

### Verificar
- [ ] Dashboard V2 carga métricas ecommerce
- [ ] Pedido ecommerce genera SaleNote automáticamente
- [ ] Admins reciben email de nuevo pedido
- [ ] RBAC funciona (roles asignados a usuarios existentes)
- [ ] Audit logs se registran
- [ ] Queue workers procesan jobs (verificar tabla `jobs`)
- [ ] Lazy loading funciona (componentes cargan bajo demanda)

### Monitoreo
- [ ] Revisar logs en `storage/logs/` diariamente la primera semana
- [ ] Verificar `stock_movements` table para auditoría de stock
- [ ] Verificar `audit_logs` table para acciones de usuarios

---

## Upgrade Path (Planificar)

| Componente | Actual | Target | Urgencia |
|-----------|--------|--------|----------|
| PHP | 8.1.10 | 8.3+ | ALTA (EOL) |
| Laravel | 9.x | 11.x | ALTA (EOL) |
| Vue | 2.6.14 | 3.x | MEDIA |
| Element UI | 2.13.0 | Naive UI / PrimeVue | MEDIA |
