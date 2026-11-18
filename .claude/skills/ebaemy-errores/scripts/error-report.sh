#!/bin/bash
# Informe de errores de producción de EBAEMY (SOLO LECTURA).
#
# Se ejecuta EN EL SERVIDOR. Desde la máquina de desarrollo:
#   scp -q .claude/skills/ebaemy-errores/scripts/error-report.sh ebaemy@ebaemy.com:/tmp/er.sh
#   ssh ebaemy@ebaemy.com 'sudo -n bash /tmp/er.sh 14; rm -f /tmp/er.sh'
#
# Argumento: días hacia atrás (por defecto 7). Necesita sudo -n para leer nginx.
#
# Secciones:
#   1. Respuestas 5xx de nginx agrupadas por ruta (ids y uuids normalizados).
#   2. Errores de Laravel agrupados por mensaje, con primera/última fecha y
#      el archivo:línea de la app (no de vendor) de la última vez.
#   3. Fatales de PHP que no llegan a Laravel (/var/log/php-app/errors.log).
#   4. Jobs fallidos de la cola.
#
# Ojo al leerlo:
#   - 503 en /ecommerce* = tienda BLOQUEADA (LockedTenant), no caída.
#   - Laravel escribe a la vez en laravel.log y en laravel-AAAA-MM-DD.log:
#     se leen los dos y se deduplica por mensaje.
set -u
DIAS="${1:-7}"
APP=/home/ebaemy/ebaemy/laravel
LOGS="$APP/storage/logs"
DESDE=$(date -d "-$DIAS days" +%Y-%m-%d)

echo "# Informe de errores — últimos $DIAS días (desde $DESDE) — $(date '+%F %T')"
echo

echo "## 1. Respuestas 5xx de nginx por ruta"
NGX=$(ls /var/log/nginx/access.log* 2>/dev/null)
{
  for f in $NGX; do zcat -f "$f" 2>/dev/null; done
} | awk -v desde="$DESDE" '
  $9 ~ /^5/ {
    split($4, t, /[\[\/:]/); m["Jan"]="01";m["Feb"]="02";m["Mar"]="03";m["Apr"]="04";m["May"]="05";m["Jun"]="06";
    m["Jul"]="07";m["Aug"]="08";m["Sep"]="09";m["Oct"]="10";m["Nov"]="11";m["Dec"]="12";
    d = t[4] "-" m[t[3]] "-" t[2];
    if (d >= desde) print $9, $7
  }' \
 | sed -E 's#/[0-9a-f]{8}-[0-9a-f-]{27,}#/{uuid}#g; s#\?.*##; s#/[0-9]+(/|$)#/{id}\1#g; s#/[0-9]+(/|$)#/{id}\1#g' \
 | sort | uniq -c | sort -rn | head -40
echo
echo "(503 en /ecommerce* = tienda bloqueada; no es caída)"
echo

echo "## 2. Errores de Laravel por mensaje"
FILES=$(ls "$LOGS"/laravel.log "$LOGS"/laravel-*.log 2>/dev/null)
grep -h -E "^\[20[0-9-]+ [0-9:]+\] [a-z]+\.(ERROR|CRITICAL|ALERT|EMERGENCY)" $FILES 2>/dev/null \
 | awk -v desde="$DESDE" 'substr($1,2,10) >= desde' \
 | sed -E 's/^\[([0-9-]+ [0-9:]+)\] [a-z]+\.[A-Z]+: /\1\t/' \
 | sed -E 's/\{"(userId|exception)".*//; s/[0-9a-f]{8}-[0-9a-f-]{27,}/{uuid}/g; s#/home/ebaemy/ebaemy/laravel/##g; s/storage\/framework\/views\/[0-9a-f]+\.php/{vista}/g' \
 | awk -F'\t' '{ msg=substr($2,1,200); gsub(/[0-9]{3,}/,"{n}",msg); c[msg]++; if(!(msg in p)) p[msg]=$1; u[msg]=$1 }
              END { for (k in c) printf "%5d | %s → %s | %s\n", c[k], substr(p[k],1,16), substr(u[k],1,16), k }' \
 | sort -rn | head -40
echo

echo "### Origen en la app de los 10 más frecuentes"
grep -h -E "^\[20[0-9-]+ [0-9:]+\] [a-z]+\.ERROR" $FILES 2>/dev/null \
 | awk -v desde="$DESDE" 'substr($1,2,10) >= desde' \
 | grep -o -E ' at /home/ebaemy/ebaemy/laravel/(app|modules|resources|routes)/[^)" ]+' \
 | sed 's# at /home/ebaemy/ebaemy/laravel/##' | sort | uniq -c | sort -rn | head -10
echo

echo "## 3. Fatales de PHP fuera de Laravel"
if [ -r /var/log/php-app/errors.log ]; then
  awk -v desde="$DESDE" '{ print }' /var/log/php-app/errors.log | grep -E "Fatal|Parse error" \
   | sed -E 's/^\[[^]]+\] //; s#/home/ebaemy/ebaemy/laravel/##g' | cut -c1-200 | sort | uniq -c | sort -rn | head -15
else
  echo "(sin acceso a /var/log/php-app/errors.log)"
fi
echo

echo "## 4. Jobs fallidos de la cola"
cd "$APP" && php artisan tinker --execute="
try { \$r = DB::connection('system')->table('failed_jobs')->where('failed_at','>=','$DESDE')->selectRaw('LEFT(exception,140) e, COUNT(*) n, MAX(failed_at) u')->groupBy('e')->orderByDesc('n')->limit(10)->get(); foreach (\$r as \$x) echo str_pad(\$x->n,5,' ',STR_PAD_LEFT).' | '.\$x->u.' | '.preg_replace('/\s+/',' ',\$x->e).PHP_EOL; if (!\$r->count()) echo '(ninguno)'.PHP_EOL; } catch (\Throwable \$e) { echo '(sin tabla failed_jobs: '.\$e->getMessage().')'.PHP_EOL; }
" 2>/dev/null | grep -v '^$'
