---
name: ebaemy-deploy
description: Procedimiento exacto para desplegar ebaemy-original a producción (ebaemy.com). Invocar cuando el usuario pida "deploy", "subir a producción", "publicar cambios", "actualizar el server" o similar. Contiene los pasos verificados, los gotchas conocidos (OOM, permisos, public/build, view:cache, dos remotes), y el rollback.
---

# Deploy ebaemy-original → producción

## Servidor

- **Host**: ebaemy.com
- **OS**: Ubuntu 24.04 LTS · **PHP**: 8.3 (servicio `php8.3-fpm.service`) · **Web**: nginx + apache2 (raro setup; reload con `sudo systemctl reload nginx`)
- **Path proyecto**: `/home/ebaemy/ebaemy/laravel/`
- **RAM**: 3.82 GiB (insuficiente para `npm run build` → OOM)
- **11 tenants productivos**: alasitas, makingroup, mitienda, talara, myka, torneo, calixto, gabito, torneoperu, ycre, charitzi
- **Webmin**: https://ebaemy.com:10000 (timeout en sesión, mata foreground)

## Antes de empezar — checks obligatorios

1. ¿El usuario confirmó explícitamente que quiere desplegar AHORA? Deploy es acción destructiva/visible: NUNCA proceder sin confirmación clara, aunque el repo esté listo.
2. `git status` limpio (working tree limpio, branch `main`).
3. `npm run build` ejecutado **EN LOCAL** (NO en server) y `public/build/` commiteado.
4. Migraciones nuevas son idempotentes (`Schema::hasTable`, `hasColumn`).
5. No hay secretos en los commits (.env, credentials, tokens).

## Procedimiento estándar (verificado 2026-04-25)

### 1. Compilar assets en local
```bash
npm run build                     # NUNCA en server (OOM)
git add public/build/
git status                        # confirmar que public/build/ entró
```

### 2. Commit + push (DOS remotes)
```bash
git add <archivos modificados>
git commit -m "feat(...): ..."
git push origin main              # repo ebaemy-original (este)
git push production main          # repo ebaemy (producción real)
```

⚠ **El remote del server se llama `origin`** y apunta a `github.com/carloscaraz-ebaemy/ebaemy.git` — NO a `ebaemy-original`. Siempre push a AMBOS remotes para que server tenga los cambios.

### 3. Backup MySQL antes de migrar (si hay migraciones)
SSH al server. Ejecutar comandos UNO POR UNO (no pegar bloque con `>`, bash interactivo rompe):
```bash
cd /home/ebaemy/ebaemy/laravel
mysqldump -u root -p ebaemy > backups/ebaemy_system_$(date +%Y%m%d_%H%M%S).sql
ls -lh backups/ | tail -3        # confirmar tamaño razonable (~9MB típico)
```

### 4. Pull + restaurar build limpio
```bash
git pull origin main
rm -rf public/build/                         # ← CRÍTICO: borra residuos de intentos previos
git checkout HEAD -- public/build/           # restaura los assets del commit exacto
ls public/build/assets/ | wc -l              # confirmar que el conteo coincide con local
```

### 5. Composer + migrate
```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force                  # idempotente; "Nothing to migrate" es OK
```

Si hay migraciones tenant a aplicar a los 11:
```bash
php artisan tinker --execute="\$ws = \Hyn\Tenancy\Models\Website::all(); foreach (\$ws as \$w) { app(\Hyn\Tenancy\Environment::class)->tenant(\$w); echo \$w->uuid . PHP_EOL; \Artisan::call('migrate', ['--force' => true]); echo \Artisan::output(); }"
```

### 6. Cache + restart
```bash
php artisan optimize:clear
php artisan config:cache
php artisan view:cache                       # ✅ ahora SÍ funciona (carpetas .gitkeep agregadas 2026-04-28)
sudo systemctl restart php8.3-fpm
sudo systemctl reload nginx
```

### 7. Smoke test
```bash
curl -sI https://ebaemy.com/marketplace
curl -sI https://ebaemy.com/precios
curl -sI https://alasitas.ebaemy.com/        # un tenant cualquiera
curl -sI https://ebaemy.com/feeds/meta-catalog.xml
```
Todos deben dar `HTTP/2 200`. Si dan 404 con `Content-Type: application/json` → problema de permisos `/home/ebaemy/` (debe ser 755, no 711):
```bash
sudo chmod 755 /home/ebaemy /home/ebaemy/ebaemy
```

## Gotchas conocidos

| Síntoma | Causa | Fix |
|---|---|---|
| OOM al `npm run build` en server | 3.82 GiB RAM no alcanza para 1430 modules | Compilar local + commitear `public/build/` |
| Assets 404 con `Content-Type: application/json` | Permisos `/home/ebaemy/` = 711 (nginx no puede leer) | `sudo chmod 755 /home/ebaemy /home/ebaemy/ebaemy` |
| `git pull` deja `public/build/` inconsistente | Residuos de build fallido previo + checkout sobrescribe parcial | `rm -rf public/build/ && git checkout HEAD -- public/build/` |
| `view:cache` falla con `DirectoryNotFoundException` | Faltaban 36 carpetas `resources/views/modules/{slug}/` | Ya resuelto 2026-04-28 con `.gitkeep` (incluido en repo) |
| `php artisan migrate` "Nothing to migrate" | Idempotencia con `Schema::hasTable/hasColumn` | OK, es esperado al re-correr |
| Comandos interactivos pegados con `>` rompen | bash interactivo del server interpreta literal `>` | Pegar comandos UNO POR UNO |
| `module 'full_suscription' not found` en logs | Workaround histórico: saltar `view:cache` | Resuelto 2026-04-28; ya no es necesario saltar |

## Reglas duras (Master Skill)

❌ NUNCA `npm run build` en server (OOM garantizado)
❌ NUNCA `--no-verify` en commit/push
❌ NUNCA `git push --force` a `main` o a `production`
❌ NUNCA borrar BD tenant (`auto-delete-tenant-database*=false` en config/tenancy.php — protección hardcodeada)
❌ NUNCA aplicar migración system sin backup mysqldump previo
❌ NUNCA usar `php artisan route:cache` (closure routes no serializables → login JSON 404)

✅ SIEMPRE backup mysqldump antes de migrar system
✅ SIEMPRE confirmar al usuario antes de `git push production`
✅ SIEMPRE smoke test los 4 endpoints públicos post-deploy
✅ SIEMPRE verificar que el conteo de archivos en `public/build/assets/` coincide local↔server

## Rollback de emergencia

Si el deploy rompe producción:
```bash
cd /home/ebaemy/ebaemy/laravel

# 1. Volver al commit anterior
git log --oneline -5
git reset --hard <SHA_ANTERIOR>

# 2. Restaurar BD si hubo migraciones que rompieron datos
mysql -u root -p ebaemy < backups/ebaemy_system_<TIMESTAMP>.sql

# 3. Si las migraciones tenant rompieron:
php artisan migrate:rollback --step=N --force

# 4. Cache + restart
php artisan optimize:clear
php artisan config:cache
sudo systemctl restart php8.3-fpm
sudo systemctl reload nginx
```

## Activaciones pendientes (configurar UNA vez en producción)

Estas no son deploy de cada release; son configuración inicial. Mencionar al usuario cuando aplique:

- **S3 imágenes**: `MEDIA_DISK=media` + `AWS_*` en `.env` + `php artisan images:migrate-to-cloud`
- **Tenant snapshot**: `php artisan tenant:snapshot` después de cada deploy con migraciones nuevas (provisión de tenant nuevo = segundos en vez de 1027 migraciones)
- **Read replica**: `TENANT_REPLICA_HOST=host.replica` en `.env`
- **Queue worker** (CapturePaymentJob async): `php artisan queue:work` con supervisor
- **Data warehouse**: crear BD `ebaemy_warehouse` + `DW_DATABASE` en `.env` + `php artisan migrate --database=warehouse --path=database/migrations/warehouse`
- **WhatsApp webhook STOP**: `WHATSAPP_WEBHOOK_VERIFY_TOKEN=...` en `.env` + configurar URL `https://ebaemy.com/webhooks/marketing/inbound` en Meta Business Manager

## Pendientes de seguridad (alta prioridad)

⚠ **ROTAR contraseña MySQL `root` en producción** — la actual `8RRY0M7WsvF5tV8` quedó expuesta en chat de la sesión 2026-04-24.
```sql
-- En el server, conectado como root:
ALTER USER 'root'@'localhost' IDENTIFIED BY 'NUEVA_PASSWORD_FUERTE';
FLUSH PRIVILEGES;
```
Luego actualizar `DB_PASSWORD` en `/home/ebaemy/ebaemy/laravel/.env` y reiniciar PHP-FPM.

## Cuando invocar este skill

- Usuario pide "deploy", "subir cambios", "publicar a producción", "actualizar el server"
- Usuario menciona ebaemy.com + cambios listos
- Usuario quiere reproducir el procedimiento manual

## Cuando NO invocar

- Cambios solo locales (no producción)
- Solo migraciones de testing (`migrate:fresh` en local)
- Build de assets local sin intención de subir
