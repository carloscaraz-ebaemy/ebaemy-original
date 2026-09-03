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
- **17 tenants productivos** (verificado 2026-08-28): alasitas, makingroup, mitienda, talara, myka, torneo,
  calixto, gabito, torneoperu, ycre, charitzi, motalvan, floristeriapetaloencanto, carolayimport,
  valentinaimportaciones, uniformespatty, importacionesdeywa
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

⚠ **CRÍTICO post-`composer install`: reaplicar permisos del tmp de mPDF.** Composer regenera `vendor/` con dueño `ebaemy`; si el tmp de mPDF no es escribible por `www-data`, **TODOS los documentos (cotización/NV/boleta/factura) fallan con "Ocurrió un error"** porque mPDF no puede crear el PDF temporal (`Cache.php: Temporary files directory is not writable`). Diagnosticado y resuelto 2026-06-03.
⚠ **NO usar `chown -R www-data:www-data storage`.** Deja al usuario CLI (`ebaemy`) sin escritura y
el siguiente `php artisan view:cache` / `config:cache` muere con `Permission denied` sobre
`storage/logs/laravel.log` y `storage/framework/cache`. Diagnosticado 2026-08-28.

El dueño correcto es `ebaemy` con grupo `www-data`: la CLI escribe como dueño, php-fpm
(que corre como `www-data`) escribe por grupo. El setgid hace que los archivos nuevos
hereden el grupo.
```bash
sudo chown -R ebaemy:www-data vendor/mpdf/mpdf/tmp storage bootstrap/cache
sudo chmod -R 0775 vendor/mpdf/mpdf/tmp storage bootstrap/cache
sudo find storage bootstrap/cache vendor/mpdf/mpdf/tmp -type d -exec chmod g+s {} \;
```
Verificar que ambos escriben:
```bash
sudo -u www-data test -w storage/logs/laravel.log && echo "www-data OK"
test -w storage/logs/laravel.log && echo "CLI OK"
```

Si hay migraciones tenant (`database/migrations/tenant/`):
```bash
php artisan tenancy:migrate --force
```

⚠ **NO usar `Artisan::call('migrate')` dentro de un loop de tenants.** `migrate` a
secas corre el path del SISTEMA (`database/migrations/`), no el de tenants, así que
responde **"Nothing to migrate"** y deja las tablas sin crear — sin ningún error que
lo delate. Diagnosticado 2026-08-30: el deploy dio por migrados 17 tenants que no
lo estaban.

Verificar SIEMPRE que la columna o tabla exista de verdad, no confiar en la salida:
```bash
php artisan tinker --execute="\$ws = \Hyn\Tenancy\Models\Website::all(); \$ok=0; \$falta=[];
foreach (\$ws as \$w) {
  app(\Hyn\Tenancy\Environment::class)->tenant(\$w);
  \$s = \Illuminate\Support\Facades\Schema::connection('tenant');
  if (\$s->hasColumn('TABLA','COLUMNA')) \$ok++; else \$falta[] = \$w->uuid;
}
echo \$ok.'/'.\$ws->count().PHP_EOL; if (\$falta) echo implode(', ', \$falta).PHP_EOL;"
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
Todos deben dar `HTTP/2 200`.

**Además, barrer TODOS los tenants** — los 4 endpoints públicos pueden dar 200 con storefronts caídos:
```bash
for t in alasitas makingroup mitienda talara myka torneo calixto gabito torneoperu ycre charitzi motalvan floristeriapetaloencanto carolayimport valentinaimportaciones uniformespatty importacionesdeywa; do
  printf "%s %s
" "$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 https://$t.ebaemy.com/ecommerce)" "$t"
done
```

**Un 503 en el storefront NO es una caida: es un tenant bloqueado.** `LockedTenant` responde
503 (pagina "volvemos pronto", para que Google reintente en vez de desindexar) cuando
`configuration.locked_tenant = 1`, y `laravel.log` no registra nada porque `HttpException`
esta en el `internalDontReport` de Laravel. Verificado 2026-09-02: los 8 tenants en 503 eran
exactamente los 8 bloqueados. Para confirmar en vez de suponer:
```bash
php artisan tinker --execute="\$ws = \Hyn\Tenancy\Models\Website::all();
foreach (\$ws as \$w) { app(\Hyn\Tenancy\Environment::class)->tenant(\$w);
  \$c = \App\Models\Tenant\Configuration::first();
  echo str_pad(\$w->uuid, 32).((\$c && \$c->locked_tenant) ? 'BLOQUEADO' : 'activo').PHP_EOL; }"
```
Guardar el resultado **ANTES** de desplegar: sin línea base no se puede distinguir una regresión
del deploy de una falla que ya venía. Si dan 404 con `Content-Type: application/json` → problema de permisos `/home/ebaemy/` (debe ser 755, no 711):
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
| `php artisan migrate` "Nothing to migrate" | Idempotencia con `Schema::hasTable/hasColumn` | OK al re-correr. **Pero si es una migración TENANT, es que estás usando el comando equivocado**: `migrate` mira `database/migrations/`, no `database/migrations/tenant/`. Usar `tenancy:migrate` |
| Comandos interactivos pegados con `>` rompen | bash interactivo del server interpreta literal `>` | Pegar comandos UNO POR UNO |
| `module 'full_suscription' not found` en logs | Workaround histórico: saltar `view:cache` | Resuelto 2026-04-28; ya no es necesario saltar |
| `view:cache` muere con `Permission denied` | `chown www-data:www-data storage` deja sin escritura al usuario CLI | `chown ebaemy:www-data` + `0775` + setgid (ver paso 5) |
| Storefront 500 **sin nada en `laravel.log`** | La rama `HttpException` del Handler renderiza `ecommerce::errors.500`, y Laravel nunca reporta `HttpException` (`internalDontReport`) | Para ver la traza real, comentar temporalmente esa rama en `app/Exceptions/Handler.php` y dejar que caiga a `parent::render()` |
| Fatales de PHP que no aparecen en ningún log | **Resuelto 2026-08-30**: el pool no tenía `error_log`. Ahora va a `/var/log/php-app/errors.log` (`php_admin_value[error_log]` en `pool.d/www.conf`), con logrotate propio en `/etc/logrotate.d/php-app-errors`. `display_errors` sigue en Off. | Si un fatal no aparece, revisar que el archivo siga siendo de `www-data`: los workers no pueden escribir un log de `root` (por eso no servía `/var/log/php8.3-fpm.log`) |
| Assets pesados / primera carga lenta | nginx trae `gzip on` pero con `gzip_types` **comentado** (default de Ubuntu): comprime solo `text/html` y deja CSS, JS y XML en crudo | **Resuelto 2026-08-30.** Antes de optimizar un asset, medir el cable: `curl -sI -H "Accept-Encoding: gzip" <url>`. Config en `/etc/nginx/nginx.conf`, backup en `nginx.conf.bak-20260830` |
| Contar 500s del `access.log` para atribuir culpa a un deploy | El formato de nginx **no incluye el vhost**: no se puede saber qué tenant falló | Comparar por tenant con `curl` antes y después, o revisar `laravel.log` que sí tiene contexto |

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
- **Data warehouse** (aprovisionado en produccion el 2026-09-01): **NO** hacen falta variables `DW_*` en `.env` — `config/database.php` ya cae por defecto en `ebaemy_warehouse` y hereda `DB_USERNAME`/`DB_PASSWORD`. Lo que si hace falta, porque ninguna migracion lo hace (Laravel necesita conectarse antes de migrar) y el path no lo recorre `migrate`:
  ```bash
  php artisan tinker --execute="DB::connection('system')->statement('CREATE DATABASE IF NOT EXISTS \`ebaemy_warehouse\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');"
  php artisan migrate --database=warehouse --path=database/migrations/warehouse --force
  php artisan warehouse:sync-etl --from=<hace 12 meses> --to=<hoy> --with-items   # carga historica
  ```
  Sin esto, el job de las 02:30 falla cada noche (`1049 Unknown database`) y la pantalla `/analytics` del SuperAdmin queda caida. El ETL solo LEE de los tenants y es idempotente.
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
