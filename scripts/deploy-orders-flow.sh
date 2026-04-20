#!/usr/bin/env bash
#
# Deploy del flujo de pedidos de 4 pasos (commit 733ec131).
#
# Uso:
#   bash scripts/deploy-orders-flow.sh           # ejecuta el deploy
#   bash scripts/deploy-orders-flow.sh --dry-run # muestra qué haría sin ejecutar
#
# Requisitos:
#   - PHP 8.1+, Composer, Node 18+, npm
#   - Acceso al queue worker (si aplica systemctl/supervisorctl)
#   - Ejecutar desde la raíz del proyecto

set -euo pipefail

# ─── Configuración ───────────────────────────────────────────────────────────
DRY_RUN=0
[[ "${1:-}" == "--dry-run" ]] && DRY_RUN=1

# Colores para logs
C_RED='\033[0;31m'
C_GREEN='\033[0;32m'
C_YELLOW='\033[1;33m'
C_BLUE='\033[0;34m'
C_RESET='\033[0m'

log()     { echo -e "${C_BLUE}[$(date +%H:%M:%S)]${C_RESET} $*"; }
success() { echo -e "${C_GREEN}✔${C_RESET} $*"; }
warn()    { echo -e "${C_YELLOW}⚠${C_RESET} $*"; }
fail()    { echo -e "${C_RED}✖${C_RESET} $*" >&2; exit 1; }

run() {
    if [[ $DRY_RUN -eq 1 ]]; then
        echo -e "  ${C_YELLOW}[dry-run]${C_RESET} $*"
    else
        eval "$*"
    fi
}

# ─── Verificación de entorno ─────────────────────────────────────────────────
[[ -f artisan ]] || fail "No se encontró artisan. Ejecuta desde la raíz del proyecto."
[[ -f composer.json ]] || fail "No se encontró composer.json."
[[ -f package.json ]] || fail "No se encontró package.json."

log "Proyecto: $(basename "$PWD")"
log "Modo: $([[ $DRY_RUN -eq 1 ]] && echo 'DRY-RUN (no ejecuta)' || echo 'EJECUTAR')"
echo ""

# ─── 0. Pre-flight checks ────────────────────────────────────────────────────
log "═══ 0/6 Verificando git y migraciones pendientes ═══"

if [[ $DRY_RUN -eq 0 ]]; then
    git_status=$(git status --porcelain | grep -v '^ M public/build/' || true)
    if [[ -n "$git_status" ]]; then
        warn "Working directory tiene cambios sin commitear (excluyendo assets):"
        echo "$git_status" | head -5
        read -p "¿Continuar de todos modos? [y/N] " -n 1 -r; echo
        [[ $REPLY =~ ^[Yy]$ ]] || fail "Deploy cancelado por el usuario."
    fi
fi

current_commit=$(git rev-parse --short HEAD 2>/dev/null || echo 'n/a')
log "Commit actual: $current_commit"
echo ""

# ─── 1. Dependencias PHP ─────────────────────────────────────────────────────
log "═══ 1/6 Instalando dependencias PHP (Composer) ═══"
run "composer install --no-dev --optimize-autoloader --no-interaction"
success "Dependencias PHP OK"
echo ""

# ─── 2. Dependencias Node + Build assets ─────────────────────────────────────
log "═══ 2/6 Build de assets frontend (Vite) ═══"
run "npm ci"
run "npm run build"
success "Assets generados en public/build/"
echo ""

# ─── 3. Migraciones tenant ───────────────────────────────────────────────────
log "═══ 3/6 Migraciones tenant ═══"
log "Aplicando migraciones del flujo de pedidos:"
log "  • 2026_04_20_000001_add_warehouse_phase_timestamps_to_orders"
log "  • 2026_04_20_000002_create_order_status_logs_table"
log "  • 2026_04_20_000003_add_unique_order_id_to_sale_notes"
run "php artisan tenancy:migrate --force"
success "Migraciones tenant aplicadas"
echo ""

# ─── 4. Cache Laravel ────────────────────────────────────────────────────────
log "═══ 4/6 Refrescando cache Laravel ═══"
run "php artisan config:clear"
run "php artisan cache:clear"
run "php artisan view:clear"
# NO hacer route:cache — memoria dice que rompe rutas closure
run "php artisan config:cache"
success "Cache Laravel refrescado (sin route:cache)"
echo ""

# ─── 5. Queue worker ─────────────────────────────────────────────────────────
log "═══ 5/6 Reiniciando queue worker ═══"
run "php artisan queue:restart"
success "Señal de restart enviada (workers reiniciarán después del job actual)"
echo ""

# ─── 6. Verificación post-deploy ─────────────────────────────────────────────
log "═══ 6/6 Verificación post-deploy ═══"

if [[ $DRY_RUN -eq 0 ]]; then
    # Verificar que las 3 columnas existen en el primer tenant
    verify_output=$(php artisan tinker --execute="
        \$tenants = \Hyn\Tenancy\Models\Website::limit(1)->get();
        foreach (\$tenants as \$t) {
            app(\Hyn\Tenancy\Environment::class)->tenant(\$t);
            \$cols = \DB::connection('tenant')->select(\"SHOW COLUMNS FROM orders LIKE '%_at'\");
            \$hasLogs = \Schema::connection('tenant')->hasTable('order_status_logs');
            echo 'tenant=' . \$t->uuid;
            echo ' | cols=' . count(\$cols);
            echo ' | logs_table=' . (\$hasLogs ? 'yes' : 'NO');
            echo PHP_EOL;
        }
    " 2>&1 | tail -5)
    echo "  $verify_output"

    if echo "$verify_output" | grep -q 'logs_table=NO'; then
        fail "La tabla order_status_logs NO se creó. Revisa los logs de migración."
    fi
    success "Schema verificado en tenant(s)"
else
    warn "Dry-run: verificación saltada"
fi
echo ""

# ─── Resumen final ───────────────────────────────────────────────────────────
echo -e "${C_GREEN}════════════════════════════════════════════════════════════${C_RESET}"
echo -e "${C_GREEN}  DEPLOY COMPLETO${C_RESET}"
echo -e "${C_GREEN}════════════════════════════════════════════════════════════${C_RESET}"
log "Checklist manual post-deploy:"
echo "  1. Abre el panel de pedidos y verifica que los botones nuevos aparecen"
echo "     (Verificar pago / Preparar / Despachar / Marcar entregado)"
echo "  2. Haz click en el ícono 🕒 de cualquier pedido → debe abrir el timeline"
echo "  3. Hace una transición de estado y confirma que se registró en order_status_logs"
echo "  4. Revisa storage/logs/laravel.log por warnings de 'sale_notes.order_id duplicates'"
echo "     (si aparece, hay duplicados históricos que requieren limpieza manual)"
echo ""
