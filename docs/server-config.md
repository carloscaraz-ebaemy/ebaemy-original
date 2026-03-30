# Configuración de Servidor para Multi-Tenant con Themes

## Nginx (Producción recomendada)

```nginx
# /etc/nginx/sites-available/ebaemy

# Wildcard SSL para subdominios
server {
    listen 443 ssl http2;
    server_name *.ebaemy.com ebaemy.com;

    ssl_certificate     /etc/letsencrypt/live/ebaemy.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ebaemy.com/privkey.pem;

    root /var/www/ebaemy/public;
    index index.php;

    # Assets estáticos con cache largo
    location ~* \.(css|js|jpg|jpeg|png|gif|ico|svg|woff2?|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Themes assets
    location /themes/ {
        alias /var/www/ebaemy/public/themes/;
        expires 7d;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # Seguridad
    location ~ /\.(?!well-known) { deny all; }
    location ~ /vendor { deny all; }
}

# Redirect HTTP → HTTPS
server {
    listen 80;
    server_name *.ebaemy.com ebaemy.com;
    return 301 https://$host$request_uri;
}

# Dominios personalizados (custom domains)
# Cada dominio personalizado necesita su propio certificado SSL
# o usar Cloudflare con SSL Flexible
server {
    listen 443 ssl http2;
    server_name ~^(?<custom_domain>.+)$;

    # Cloudflare Origin Certificate (wildcard) o Let's Encrypt per-domain
    ssl_certificate     /etc/nginx/ssl/cloudflare-origin.pem;
    ssl_certificate_key /etc/nginx/ssl/cloudflare-origin.key;

    root /var/www/ebaemy/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Apache (Laragon / desarrollo)

```apache
# /etc/apache2/sites-available/ebaemy.conf
# O en Laragon: C:\laragon\etc\apache2\sites-enabled\auto.*.conf

<VirtualHost *:80>
    ServerName ebaemy.test
    ServerAlias *.ebaemy.test
    DocumentRoot "C:/laragon/www/ebaemy-original/public"

    <Directory "C:/laragon/www/ebaemy-original/public">
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    # Cache de assets
    <FilesMatch "\.(css|js|jpg|png|gif|svg|woff2)$">
        Header set Cache-Control "max-age=2592000, public"
    </FilesMatch>
</VirtualHost>
```

## DNS

### Subdominios (wildcard)
```
# En el proveedor DNS (Cloudflare, Route53, etc.)
*.ebaemy.com    A       IP_DEL_SERVIDOR
*.ebaemy.com    AAAA    IPv6_DEL_SERVIDOR (opcional)
ebaemy.com      A       IP_DEL_SERVIDOR
```

### Dominios personalizados
```
# El cliente configura en SU DNS:
tienda.cliente.com    CNAME    cliente.ebaemy.com

# Para verificación:
tienda.cliente.com    TXT      ebaemy-verify-xxxxxxxxxxxxx
```

## Cloudflare (recomendado)

### Configuración
1. **SSL/TLS** → Full (strict) si tienes cert en el servidor, o Flexible
2. **SSL/TLS** → Edge Certificates → habilitar "Always Use HTTPS"
3. **DNS** → Agregar wildcard: `*.ebaemy.com` → IP servidor (proxied)
4. **Page Rules** o **Rules** → Cache Level: Standard para assets

### Para dominios personalizados con Cloudflare
1. Cliente agrega CNAME apuntando a `cliente.ebaemy.com`
2. En Cloudflare del servidor: agregar el dominio custom en "Custom Hostnames" (plan Business+)
3. O usar Cloudflare for SaaS (plan Enterprise)
4. Alternativa económica: Let's Encrypt con certbot + DNS challenge

## Let's Encrypt Wildcard SSL

```bash
# Instalar certbot
sudo apt install certbot python3-certbot-nginx

# Wildcard (requiere DNS challenge)
sudo certbot certonly --manual --preferred-challenges dns \
    -d "ebaemy.com" -d "*.ebaemy.com"

# Auto-renovación
sudo crontab -e
# Agregar: 0 3 * * * certbot renew --quiet && systemctl reload nginx
```

## Queue Workers (Producción)

### Supervisor (recomendado)

```ini
# /etc/supervisor/conf.d/ebaemy-worker.conf

[program:ebaemy-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ebaemy/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/ebaemy/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
# Instalar y activar
sudo apt install supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start ebaemy-worker:*

# Verificar estado
sudo supervisorctl status
```

### Jobs que dependen del queue

| Job | Frecuencia | Descripción |
|-----|-----------|-------------|
| CapturePaymentJob | Inmediato | Captura pago Culqi pre-autorizado |
| SendWhatsAppMessage | Inmediato | Notificaciones WhatsApp |
| VerifyDomainDns | Cada 30 min (schedule) | Verifica DNS de dominios custom |
| GenerateSslCertificate | Bajo demanda | Genera cert SSL con certbot |
| PurgeAbandonedCarts | Diario 03:00 | Limpia carritos expirados |
| SyncCarrierTracking | Cada 30 min | Sincroniza tracking de carriers |
| EtlSyncWarehouse | Diario 02:30 | ETL incremental al warehouse |
| MarketplaceSync | Cada 15 min | Sync stock/orders marketplaces |

### Cron (Laravel Scheduler)

```bash
# Agregar al crontab del servidor
* * * * * cd /var/www/ebaemy && php artisan schedule:run >> /dev/null 2>&1
```

### Variables de entorno para producción

```env
QUEUE_CONNECTION=database
CACHE_DRIVER=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1

# Encriptación de credenciales (NUNCA cambiar después de encriptar)
APP_KEY=base64:... (generado con php artisan key:generate)

# Después de desplegar, ejecutar UNA VEZ:
# php artisan tenants:encrypt-credentials
# php artisan credentials:encrypt
```
