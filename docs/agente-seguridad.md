# Agente de Seguridad y Detección de Riesgos

Revisa el sistema cada 15 minutos, clasifica lo que encuentra en **BAJA / MEDIA / ALTA / CRÍTICA** y avisa con una recomendación concreta.

**El agente sólo lee y avisa.** No bloquea IPs, no pausa publicaciones, no modifica pedidos y no escribe una sola fila en la base de datos de producción. Todo su estado vive en archivos dentro de `storage/app/security-agent/`.

---

## Estado de los módulos

| # | Módulo | Estado | Fuente de datos |
|---|--------|--------|-----------------|
| 5 | Ingreso no autorizado | **Activo** | `login_events`, `users` |
| 3 | Ciberseguridad del servidor | **Activo** | access log de nginx/OpenResty + SHA-256 de archivos |
| 1 | Fraude en pedidos y pagos | **Activo** | `orders` (IP, user-agent, huella de tarjeta) |
| 2 | Errores de catálogo | **Activo** | `items`, `item_warehouse`, `item_price_history` |
| 4 | Salud en marketplaces | Fase C | API de MercadoLibre + métricas locales de Falabella |
| 6 | Horarios de trabajo | Fase C | `audit_logs`, `login_events` |

Los módulos de la fase C ya tienen su bloque de configuración reservado y salen desactivados.

---

## Instalación

### 1. Migraciones

Crean la bitácora de autenticación `login_events` (una en la base del sistema, una por tenant). **No se ejecuta SQL directo: todo por migración.**

```bash
# Bitácora de autenticación (módulos 5 y 6)
php artisan migrate --path=database/migrations/2026_09_22_000100_create_login_events_table.php --force
php artisan tenancy:migrate --path=database/migrations/tenant/2026_09_22_000100_create_login_events_table.php --force

# Contexto de riesgo del pedido: IP, user-agent y huella de tarjeta (módulo 1)
php artisan tenancy:migrate --path=database/migrations/tenant/2026_09_22_000200_add_risk_context_to_orders.php --force
```

Verifica que la tabla exista en todos los tenants:

```sql
SELECT table_schema FROM information_schema.tables WHERE table_name = 'login_events';
```

### 2. Archivo de configuración

```bash
php artisan security:scan --init-config
```

Crea `storage/app/security-agent/config.json`, comentado y listo para editar. **Es el único archivo que necesitas tocar en el día a día.**

### 3. Ruta del access log

Averigua la ruta real en el servidor:

```bash
ls -l /usr/local/openresty/nginx/logs/access.log
ls -l /var/log/openresty/access.log
```

Ponla en `web_attacks.access_log_paths` (la primera de la lista que exista y sea legible es la que se usa) y asegúrate de que el usuario que corre PHP pueda leerla:

```bash
sudo usermod -aG adm www-data     # o el grupo dueño del log
```

Mientras no la encuentre, el agente emite una alerta MEDIA diciéndolo — no falla en silencio.

### 4. Usuario de base de datos de sólo lectura (recomendado)

```sql
CREATE USER 'ebaemy_security'@'localhost' IDENTIFIED BY 'una-clave-larga';
GRANT SELECT ON `ebaemy%`.* TO 'ebaemy_security'@'localhost';
FLUSH PRIVILEGES;
```

Declara la conexión en `config/database.php` y apúntala con `SECURITY_AGENT_DB_CONNECTION` en `.env`. Es un cinturón de seguridad: aunque un módulo tuviera un error, no podría escribir.

### 5. Credenciales de notificación (`.env`, nunca en el código)

```dotenv
SECURITY_AGENT_ENABLED=true

# Telegram
SECURITY_AGENT_TELEGRAM_ENABLED=true
SECURITY_AGENT_TELEGRAM_TOKEN=123456:ABC-DEF...
SECURITY_AGENT_TELEGRAM_CHAT_ID=-1001234567890

# Correo (varios destinatarios separados por coma)
SECURITY_AGENT_MAIL_ENABLED=true
SECURITY_AGENT_MAIL_TO=carlos@ebaemy.com,soporte@ebaemy.com

# Webhook entrante de Slack
SECURITY_AGENT_WEBHOOK_ENABLED=true
SECURITY_AGENT_WEBHOOK_URL=https://hooks.slack.com/services/...
```

Sólo se notifican las alertas **ALTA y CRÍTICA** (ajustable en `notify.min_severity`). Si un canal falla, los demás salen igual y el fallo queda en el log.

### 6. Programación

Ya está registrado en `app/Console/Kernel.php`:

```php
$schedule->command('security:scan')->everyFifteenMinutes()->withoutOverlapping();
```

Sólo hace falta que el scheduler de Laravel esté en el crontab del servidor (ya lo está en producción):

```cron
* * * * * cd /home/ebaemy && php artisan schedule:run >> /dev/null 2>&1
```

---

## Uso

```bash
php artisan security:scan                          # corrida normal
php artisan security:scan --dry-run                # no guarda estado ni notifica
php artisan security:scan --module=web_attacks     # un solo módulo
php artisan security:scan --tenant=<uuid>          # un solo tenant
php artisan security:scan --no-notify              # revisa sin avisar a nadie
php artisan security:scan --init-config            # crea el config.json
php artisan security:scan --accept-integrity       # acepta los hashes actuales
```

### Salidas

| Salida | Dónde |
|--------|-------|
| Consola | salida del comando y `storage/logs/security_agent.log` |
| Historial | `storage/app/security-agent/alerts.jsonl` (una alerta por línea) |
| Reporte | `storage/app/security-agent/report.html` |
| Notificaciones | Telegram, correo y webhook — sólo ALTA y CRÍTICA |

Consultar el historial:

```bash
tail -20 storage/app/security-agent/alerts.jsonl | jq '.severity + " " + .title'
grep CRITICA storage/app/security-agent/alerts.jsonl | jq -r .title
```

---

## Cómo se configura (sin tocar código)

Todo vive en `storage/app/security-agent/config.json`. Acepta comentarios `//`. Sólo necesitas escribir lo que quieras **cambiar**: el resto conserva el valor por defecto de `config/security-agent.php`.

Cuidado con una regla: **una lista reemplaza a la lista entera**, no se suma. Si escribes `"allowed_countries": ["PE","CL"]`, esa es la lista completa.

### Agregar un usuario autorizado

```json
"unauthorized_access": {
  "authorized_emails": [
    "carlos@ebaemy.com",
    "ventas@ebaemy.com",
    "nuevo.empleado@ebaemy.com"
  ]
}
```

Si dejas la lista **vacía**, el agente usa la tabla `users`: vale cualquier usuario activo y no bloqueado. Sirve para arrancar, pero la lista explícita es la detección más valiosa del módulo — con ella, un usuario creado por un atacante salta como CRÍTICA aunque esté activo en la base.

### Agregar feriados

```json
"work_schedule": {
  "holidays": ["2027-01-01", "2027-04-01", "2027-05-01"]
}
```

Hay que actualizarlos cada año: son fechas fijas, no se calculan.

### Turnos especiales por usuario

```json
"work_schedule": {
  "user_exceptions": {
    "nocturno@ebaemy.com": {
      "mon": ["22:00", "06:00"],
      "tue": ["22:00", "06:00"]
    }
  }
}
```

### Ajustar el fraude

```json
"order_fraud": {
  "max_amount": 8000,
  "same_ip_orders": 4,
  "disposable_domains": ["mailinator.com", "yopmail.com", "elquevistehoy.com"]
}
```

El puntaje se arma sumando señales; los puntos de cada una están en `config/security-agent.php`, bajo `order_fraud.scores`. Si quieres que una señal deje de pesar, ponla en `0` desde el JSON:

```json
"order_fraud": { "scores": { "night_purchase": 0 } }
```

Útil si vendes de madrugada con normalidad: sin esto, toda venta nocturna arrastra 10 puntos de base.

### Ajustar el catálogo

```json
"catalog_anomalies": {
  "min_margin_pct": 15,
  "critical_stock": 5,
  "ignore_item_ids": [1204, 1877]
}
```

`ignore_item_ids` es para productos que siempre van a "fallar" a propósito: muestras, promociones permanentes, artículos de costo cero.

### Silenciar ruido

- `web_attacks.whitelist_ips` — tu oficina, tu monitoreo, tu CDN.
- `unauthorized_access.trusted_ips` — IPs internas que no deben generar alerta de "IP nueva".
- `modules.<nombre>: false` — apaga un módulo entero.
- `dedupe_hours` — la misma alerta no se repite dentro de esta ventana (24 h por defecto).

---

## Después de cada despliegue

El módulo 3 vigila el SHA-256 de los archivos del checkout y de pagos. Un despliegue legítimo los cambia, así que **hay que aceptar la nueva línea base** o el agente reportará CRÍTICA:

```bash
php artisan security:scan --accept-integrity
```

Conviene añadirlo al final del script de despliegue, después de `npm run build` y del `git pull`.

---

## Detalles que conviene saber

**La primera corrida aprende, no alerta.** Los hashes de integridad y las IPs conocidas de cada usuario se siembran en el primer escaneo. Si no fuera así, el estreno del agente dispararía una alerta por cada usuario y cada archivo.

**La geolocalización se resuelve al escanear, no al entrar.** Ningún usuario espera una llamada HTTP para iniciar sesión. El resultado se guarda en caché 30 días en `storage/app/security-agent/state/geo_cache.json`.

**El número de tarjeta no se guarda en ningún sitio.** Para detectar "la misma tarjeta con varios clientes" se guarda únicamente los últimos 4 dígitos y un HMAC-SHA256 de BIN + últimos 4, con la `APP_KEY` como clave. Dos pedidos con la misma tarjeta dan la misma huella, pero de la huella no se reconstruye nada — ni por fuerza bruta, porque sin la `APP_KEY` no se puede recalcular. La huella tampoco aparece en las alertas: ahí sólo sale `****1111`.

**Las alertas de catálogo van agrupadas.** Si una importación deja 300 artículos sin precio, llega un aviso con la lista, no 300 avisos. La firma de deduplicación incluye el conjunto de productos: si la lista cambia, vuelve a avisar.

**La bitácora nunca guarda contraseñas.** `RecordLoginEvent` toma sólo el identificador intentado (correo o usuario) de las credenciales; la contraseña se descarta. Y un fallo al escribir la bitácora jamás impide un login.

**Un módulo caído no tumba la corrida.** Cada detector va aislado: su error se reporta en la consola, en el HTML y en `laravel.log`, y los demás siguen. El comando termina con código 1 para que el cron lo note.

**La lectura del access log es incremental.** Se guarda el offset; si `logrotate` deja el archivo más pequeño, vuelve a empezar desde cero automáticamente.

---

## Pruebas

```bash
php artisan test --filter=Security
# o, si pdo_sqlite no está habilitado en tu php.ini:
php -d extension=php_pdo_sqlite.dll vendor/phpunit/phpunit/phpunit tests/Feature/Security
```

Los datos simulados incluyen al menos un caso de cada tipo de alerta, además de tráfico legítimo y una IP en lista blanca que **no** debe alertar. El access log de prueba está en `tests/Fixtures/security/access-log-simulado.log`.

---

## Qué hacer ante cada alerta

Ver [acciones ante alertas críticas](agente-seguridad-acciones.md).
