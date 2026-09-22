# Acciones ante alertas críticas

Qué hacer, en orden, cuando el agente avisa. Cada alerta que llega por Telegram, correo o Slack ya trae su recomendación resumida; esto es la versión larga.

**Regla general:** el agente no toca nada. Bloquear, desactivar y rotar credenciales son decisiones tuyas, y todas se ejecutan a mano.

---

## Antes de nada: los tres comandos que vas a usar

```bash
# Bloquear una IP en el servidor
sudo ufw deny from 203.0.113.10

# Ver qué hizo una cuenta después de entrar
SELECT created_at, action, module, description, ip_address
FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 50;

# Ver el historial completo de accesos de una cuenta
SELECT created_at, result, ip_address, user_agent
FROM login_events WHERE email = '?' ORDER BY created_at DESC LIMIT 50;
```

---

## Módulo 5 — Ingreso no autorizado

### `usuario_no_autorizado` — CRÍTICA
Entró alguien que no está en la lista de autorizados.

1. ¿Es un empleado nuevo que nadie agregó a la lista? Agrégalo a `authorized_emails` y cierra el caso.
2. Si no lo reconoces: **desactiva el usuario** en Configuración → Usuarios (`active = 0`).
3. Cambia la contraseña y cierra todas sus sesiones.
4. Revisa `audit_logs` de esa cuenta: precios cambiados, clientes exportados, usuarios creados, cuentas bancarias tocadas.
5. Si la cuenta **no existía ayer**, alguien con acceso de administrador la creó. Trata el incidente como compromiso total: rota todas las contraseñas de administrador.

### `acceso_tras_fallos` — CRÍTICA
Un ingreso exitoso precedido de varios fallos: o el dueño olvidó su clave, o alguien la adivinó.

1. Llama al titular. Si no fue él, la cuenta está comprometida.
2. Cambia la contraseña y cierra sesiones.
3. Revisa `audit_logs` de la ventana posterior al ingreso.
4. Si hubo movimientos de dinero o cambios de cuenta bancaria, avisa a contabilidad antes que a nadie.

### `viaje_imposible` — CRÍTICA
La misma cuenta entró desde dos países en menos de 4 horas. Una de las dos sesiones no es del usuario.

1. Cambia la contraseña de inmediato, sin llamar primero.
2. Cierra todas las sesiones.
3. Revisa `audit_logs` de toda la ventana.
4. Activa segundo factor para esa cuenta.

> Excepción legítima: VPN corporativa o un proxy que salga por otro país. Si es tu caso habitual, agrega esa IP a `trusted_ips`.

### `fuerza_bruta` — ALTA
Muchos intentos fallidos contra una cuenta.

1. Bloquea las IPs de la evidencia.
2. Avisa al titular y fuerza cambio de contraseña si la actual es débil.
3. Comprueba que ninguno de los intentos haya tenido éxito (`login_events`, `result = 'success'`).

### `rociado_contrasenas` — ALTA
Una IP probando una contraseña común contra muchas cuentas.

1. Bloquea la IP.
2. Cruza la lista de cuentas probadas con los ingresos exitosos de la misma franja.
3. Si alguna cuenta usa una contraseña de diccionario, fuérzale el cambio hoy.

### `pais_no_permitido` — ALTA
1. Confirma con el usuario si está de viaje.
2. Si no lo está: contraseña nueva y sesiones cerradas.
3. Si viaja seguido, agrega el país a `allowed_countries`.

### `ip_nueva` — MEDIA
1. Pregunta al usuario si reconoce el acceso.
2. Si no lo reconoce, trátalo como `acceso_tras_fallos`.
3. Si sí, no hay nada que hacer: el agente ya dio esa IP por conocida.

---

## Módulo 3 — Ciberseguridad del servidor

### `integridad_checkout` — CRÍTICA
Cambió el contenido de un archivo de checkout o de pagos.

1. **¿Acabas de desplegar?** Entonces es normal: `php artisan security:scan --accept-integrity`.
2. Si NO desplegaste, asume un **skimmer de tarjetas** hasta demostrar lo contrario:
   - `git diff` y `git status` sobre los archivos de la evidencia.
   - Si aparece código que nadie escribió, saca el sitio de línea (modo mantenimiento).
   - Rota las llaves de Culqi y de MercadoPago.
   - Avisa a tu adquirente: hay obligación de notificar si se pudieron capturar tarjetas.
   - Restaura desde git, no a mano.
   - Busca cómo entró: revisa `rutas_sensibles`, accesos FTP/SSH y las alertas del módulo 5 de los días previos.

### `integridad_inventario` — ALTA
Apareció o desapareció un archivo de la lista vigilada. Un archivo nuevo dentro del checkout es la vía habitual para inyectar un skimmer; uno que desaparece puede ser un borrado de huellas. Mismo procedimiento que arriba.

### `sql_injection` / `command_injection` con respuesta 2xx — CRÍTICA
El servidor **respondió sin error** a un payload de ataque.

1. Bloquea la IP ahora.
2. Revisa `laravel.log` de esa franja: busca errores de base de datos o consultas raras.
3. Identifica la ruta atacada en la evidencia y comprueba que valide sus parámetros.
4. Si la inyección pudo leer datos: cuenta cuántos clientes están en esa tabla y evalúa la obligación de notificar.
5. Rota las credenciales de base de datos.

### `sql_injection` / `xss` / `path_traversal` / `command_injection` rechazados — ALTA
1. Bloquea la IP.
2. Revisa que la ruta tocada valide entradas.
3. No hace falta más: fue sondeo rechazado. Pero si se repite desde rangos distintos, es un ataque dirigido, no ruido de fondo.

### `rutas_sensibles` con respuesta 2xx — CRÍTICA
Una ruta que debería estar cerrada devolvió contenido.

1. Ciérrala en nginx ahora:
   ```nginx
   location ~ /\.(env|git|ssh|aws) { deny all; return 404; }
   location ~* /(phpmyadmin|adminer|backup|dump\.sql) { deny all; return 404; }
   ```
2. **Rota todas las credenciales de `.env`**: base de datos, Culqi, MercadoPago, tokens de Falabella y MercadoLibre, `APP_KEY`, claves de correo.
3. Bloquea la IP.

### `rutas_sensibles` rechazadas — MEDIA
Confirma que esas rutas devuelvan 404 y no un 403 con contenido, y bloquea la IP si insiste.

### `escaner_conocido` — ALTA
Nadie navega con sqlmap o nikto: es reconocimiento previo a un ataque.

1. Bloquea la IP.
2. Revisa las siguientes horas con más atención: el escaneo suele preceder al intento real.
3. Si el escaneo es tuyo (auditoría contratada), agrega la IP a `whitelist_ips`.

### `escaneo_4xx` — ALTA
1. Bloquea la IP o aplica límite de tasa.
2. Revisa la lista de rutas de la evidencia: si alguna existe de verdad y no debería ser pública, ciérrala.

### `fuerza_bruta_login_web` — ALTA
1. Bloquea la IP.
2. Cruza con el módulo 5: ¿alguno tuvo éxito?
3. El throttle de la aplicación es de 3 intentos / 5 minutos; un volumen alto significa que están rotando la cuenta objetivo.

### `trafico_excesivo` — ALTA
Scraping del catálogo o intento de saturación.

1. Límite de tasa en nginx:
   ```nginx
   limit_req_zone $binary_remote_addr zone=general:10m rate=60r/m;
   ```
2. Si es un bot legítimo (Google, Meta), agrégalo a `whitelist_ips`.

### `access_log_ausente` — MEDIA
El agente está ciego para todo el módulo 3. Corrige la ruta en `config.json` y los permisos de lectura. No es una alerta de ataque: es el agente avisando que no puede hacer su trabajo.

---

## Si confirmas un compromiso: el orden correcto

1. **Contener** — modo mantenimiento, bloquear las IPs, desactivar las cuentas sospechosas.
2. **Preservar** — copia los logs (`access.log`, `laravel.log`, `login_events`, `audit_logs`) **antes** de restaurar nada. Si restauras primero, pierdes la evidencia.
3. **Erradicar** — restaurar desde git, nunca parchear a mano.
4. **Rotar** — todas las credenciales: `.env`, base de datos, pasarelas de pago, tokens de marketplaces, contraseñas de administrador.
5. **Recuperar** — volver a línea, aceptar la nueva base de integridad, vigilar 72 horas con atención.
6. **Aprender** — ajusta los umbrales del agente con lo que aprendiste del incidente.

Para incidentes con tarjetas de por medio, el paso 2 no es opcional: sin logs preservados no hay forma de determinar el alcance ante el adquirente.
