---
name: ebaemy-legacy-audit
description: Auditoría sistemática de una dependencia legacy (URL, dominio, servicio externo, gateway viejo, librería deprecada) en EBAEMY antes de retirarla. Invocar cuando el usuario pida "auditar X", "buscar todas las referencias a Y", "antes de retirar Z", "qué romperá si elimino W". Recibe el string a auditar como argumento (ej: `devaemy.com`, `qr-api-old`, `mailgun`, etc.). NO modifica código — solo diagnostica. Multi-tenant aware: considera DB-per-tenant.
---

# Auditoría de dependencia legacy — EBAEMY

## Cuándo invocar este skill

- "Audita la dependencia X"
- "Buscar referencias a Y antes de retirarla"
- "Qué se rompe si elimino Z"
- "Estamos por retirar el servicio W, ¿dónde está usado?"
- "Quiero migrar de proveedor X a Y, qué hay que tocar"

## Cuándo NO invocarlo

- Diagnóstico arquitectónico general (usar Explore agent directo)
- Implementación de cambios (este skill solo audita)
- Code review post-cambios (usar `security-review` o `simplify`)

## Contexto fijo del sistema

EBAEMY es un SaaS Laravel multi-tenant con estas características que afectan toda auditoría:

- **Stack**: Laravel + Vue + Bootstrap/Porto, MySQL, Hyn Tenancy
- **Multi-tenancy**: DB-per-tenant. Hay ~11 tenants productivos (alasitas, makingroup, mitienda, talara, myka, torneo, calixto, gabito, torneoperu, ycre, charitzi)
- **Configuración WhatsApp**: tabla `configurations` (legacy) + `configuration_ecommerce` (nueva), ambas en tenant DB
- **Path local**: `c:\laragon\www\ebaemy-original`
- **Path producción**: `/home/ebaemy/ebaemy/laravel/`
- **Una sola consulta SQL no basta** — hay que iterar tenants con `Hyn\Tenancy\Environment`

## Procedimiento de auditoría (6 pasos)

### Paso 1 — Búsqueda literal en código fuente

Usar Grep (no `grep` directo) para el string objetivo en TODO el repo:

```
patrón: <STRING_OBJETIVO>
ubicaciones a cubrir:
  - app/                  (PHP: services, controllers, jobs, listeners, models)
  - config/               (config files)
  - database/migrations/  (system + tenant)
  - database/seeders/
  - routes/
  - resources/views/      (Blade)
  - resources/js/         (Vue + JS)
  - public/               (excluir build/ por ahora)
  - .env, .env.example, .env.production
  - composer.json, package.json
```

Para cada hallazgo reportar: archivo:línea + contexto (3 líneas alrededor) + clasificación:
- 🔴 **Hardcoded** (string literal en código que no se puede cambiar sin redeploy)
- 🟡 **Default/Fallback** (valor por defecto que aplica si BD vacía)
- 🟢 **Comment/Docs** (solo en comentario o documentación)

### Paso 2 — Búsqueda en configuración tenant (BD-per-tenant)

Generar UN script Artisan listo para correr (NO ejecutarlo aquí — entregarlo al usuario para que lo corra en producción/local):

```php
<?php
// Guardar como app/Console/Commands/AuditLegacyDependency.php
// Invocar: php artisan audit:legacy <STRING_OBJETIVO>

namespace App\Console\Commands;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditLegacyDependency extends Command
{
    protected $signature = 'audit:legacy {needle}';
    protected $description = 'Audita una cadena legacy en la BD de cada tenant';

    public function handle(Environment $env)
    {
        $needle = $this->argument('needle');
        $rows = [];

        foreach (Website::all() as $website) {
            $env->tenant($website);

            // Tablas a inspeccionar — extender según el dominio del audit
            $checks = [
                ['configurations', 'qr_api_url'],
                ['configurations', 'qr_api_apiKey'],
                ['configuration_ecommerce', 'whatsapp_api_token'],
                ['configuration_ecommerce', 'whatsapp_phone_number_id'],
                // Agregar más según contexto del audit
            ];

            foreach ($checks as [$table, $column]) {
                if (!\Schema::hasTable($table) || !\Schema::hasColumn($table, $column)) {
                    continue;
                }
                $hits = DB::table($table)
                    ->where($column, 'LIKE', "%{$needle}%")
                    ->select('id', $column)
                    ->get();
                foreach ($hits as $hit) {
                    $rows[] = [
                        'tenant'  => $website->uuid,
                        'table'   => $table,
                        'column'  => $column,
                        'row_id'  => $hit->id,
                        'value'   => $hit->{$column},
                    ];
                }
            }
        }

        $this->table(['Tenant', 'Tabla', 'Columna', 'ID', 'Valor'], $rows);
        $this->info(count($rows) . ' coincidencias encontradas');
    }
}
```

Este comando es **idempotente y de solo lectura**. Documentar al usuario cómo correrlo.

### Paso 3 — Cache, vistas compiladas y assets

Verificar si el string aparece en artefactos generados que persisten en producción:

```
- storage/framework/views/         (vistas Blade compiladas)
- storage/framework/cache/         (cache de config/routes/events)
- bootstrap/cache/                 (config.php, services.php cacheados)
- public/build/                    (assets Vue compilados — JS/CSS minificados)
```

Para `public/build/` usar Grep con tipo de archivo `js,css` y patrón del string. Si hay match, significa que el string fue compilado desde un .vue/.js — ese archivo SÍ está en código fuente y debió aparecer en Paso 1.

### Paso 4 — Referencias indirectas

Buscar variantes del string:

- Subdominios (`*.dominio.com` → buscar también el dominio raíz)
- Sin protocolo (`dominio.com` vs `https://dominio.com`)
- En comentarios `// TODO`, `// FIXME`, `// LEGACY`
- En `composer.json` / `package.json` (deps)
- En docker-compose.yml, Dockerfile, .github/

### Paso 5 — Análisis de blast radius

Para CADA hallazgo, clasificar:

| Categoría | Descripción | Riesgo de retirar |
|---|---|---|
| **Bloqueante** | Si retiro esto, una funcionalidad activa deja de funcionar | 🔴 Alto |
| **Configurable** | Es un valor que se cambia desde panel/BD sin tocar código | 🟢 Bajo |
| **Muerto** | Código no invocado ni referenciado | 🟢 Bajo |
| **Documental** | Solo aparece en comentarios o docs | 🟢 Cero |

Para cada **Bloqueante** indicar:
- Quién lo invoca (caller)
- Qué pasa cuando se ejecuta (timeout, error silencioso, exception)
- Plan de migración mínimo

### Paso 6 — Reporte final estructurado

Entregar SIEMPRE en este formato:

```markdown
# Audit: <STRING_OBJETIVO>

## 1. Hallazgos en código fuente
| # | Archivo:Línea | Tipo | Contexto |

## 2. Hallazgos en BD multi-tenant
[Adjuntar el script Artisan + instrucciones para correrlo]

## 3. Hallazgos en cache/assets compilados
[O confirmar "ninguno" explícitamente]

## 4. Referencias indirectas
[Variantes, comentarios, deps]

## 5. Tabla consolidada con blast radius
| Hallazgo | Categoría | Caller | Acción recomendada | Riesgo |

## 6. Plan de retiro sugerido
- Fase 1: cambios sin riesgo (comments, código muerto)
- Fase 2: configuraciones (BD)
- Fase 3: código bloqueante (requiere migración previa)
```

## Reglas duras

❌ **NUNCA modificar archivos** — este skill es solo lectura
❌ **NUNCA ejecutar el script Artisan** — entregarlo al usuario
❌ **NUNCA conectarse a BD de producción** — el script se ejecuta en el server
❌ **NUNCA recomendar retiro inmediato** sin evaluar blast radius primero

✅ **SIEMPRE citar archivo:línea** en cada hallazgo
✅ **SIEMPRE clasificar el riesgo** de cada hallazgo
✅ **SIEMPRE diferenciar** código fuente vs BD vs cache
✅ **SIEMPRE entregar el script Artisan parametrizado** para que el usuario verifique en su entorno
✅ **SIEMPRE listar explícitamente** qué NO se encontró (cache, assets, etc.) cuando una sección esté vacía

## Casos típicos de uso

| Escenario | Argumento de invocación |
|---|---|
| Retirar gateway WhatsApp legacy | `devaemy.com` o `qr-api` |
| Migrar de Mailgun a SES | `mailgun` |
| Eliminar módulo viejo | `OldModuleName` |
| Cambiar proveedor de pagos | `culqi-old-key` o nombre del proveedor |
| Limpiar referencias a tenant retirado | `nombre-tenant-retirado` |

## Notas para futuras invocaciones

- Si el string es un dominio, buscar TAMBIÉN el dominio raíz y subdominios comunes
- Si el string es un nombre de servicio, buscar variantes camelCase, snake_case y kebab-case
- Si es un token o API key, NUNCA mostrarlo completo en el reporte (mask los últimos chars)
- Extender el array `$checks` del script Artisan según el dominio del audit (WhatsApp → tablas WhatsApp; Pagos → tablas de pagos; etc.)
