#!/usr/bin/env bash
#
# Auditor de uso de librerías CSS — solo lectura, no modifica nada.
#
# Escanea resources/views, modules, resources/js y reporta cuántas veces
# se referencia cada lib vendor de Porto. Libs con 0 referencias son
# candidatas a eliminar (confirmar antes).
#
# Uso:
#   bash scripts/audit-css-usage.sh              # reporte completo
#   bash scripts/audit-css-usage.sh --zero       # solo libs sin uso
#   bash scripts/audit-css-usage.sh --summary    # resumen con conteos
#
# Output: stdout. Para guardar:
#   bash scripts/audit-css-usage.sh > /tmp/css-audit.txt

set -euo pipefail

MODE="${1:-full}"
VENDOR_DIR="public/porto-light/vendor"
ECOM_CSS="public/porto-ecommerce/assets/css"
SCAN_PATHS=("resources/views" "modules" "resources/js")

C_RED='\033[0;31m'
C_GREEN='\033[0;32m'
C_YELLOW='\033[1;33m'
C_BLUE='\033[0;34m'
C_GRAY='\033[0;90m'
C_RESET='\033[0m'

[[ -d "$VENDOR_DIR" ]] || { echo "No se encontró $VENDOR_DIR — ejecuta desde raíz del proyecto"; exit 1; }

# Grep portable (ripgrep si está disponible, si no grep -r)
if command -v rg >/dev/null 2>&1; then
    GREP_CMD() { rg -l --no-messages "$@" 2>/dev/null; }
else
    GREP_CMD() { grep -rl "$@" 2>/dev/null; }
fi

count_refs() {
    local lib="$1"
    local count=0
    local found
    for p in "${SCAN_PATHS[@]}"; do
        [[ -d "$p" ]] || continue
        found=$(GREP_CMD "$lib" "$p" 2>/dev/null | wc -l | tr -d ' \n')
        found=${found:-0}
        count=$((count + found))
    done
    echo "$count"
}

echo -e "${C_BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${C_RESET}"
echo -e "${C_BLUE}  AUDITORÍA CSS — $(date +%Y-%m-%d\ %H:%M:%S)${C_RESET}"
echo -e "${C_BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${C_RESET}"
echo ""

# ── 1. Porto vendor libs ────────────────────────────────────────────────
echo -e "${C_BLUE}▸ Libs vendor Porto (público/porto-light/vendor/)${C_RESET}"
echo ""

total=0
zero_libs=()
printf "  %-35s %8s\n" "LIBRERÍA" "REFS"
printf "  %-35s %8s\n" "─────────" "────"

for lib_path in "$VENDOR_DIR"/*/; do
    lib=$(basename "$lib_path")
    refs=$(count_refs "$lib")
    total=$((total + 1))

    if [[ "$refs" -eq 0 ]]; then
        zero_libs+=("$lib")
        [[ "$MODE" != "--summary" ]] && printf "  ${C_RED}%-35s %8d${C_RESET}\n" "$lib" "$refs"
    else
        [[ "$MODE" == "--zero" ]] || {
            if [[ "$refs" -le 2 ]]; then
                printf "  ${C_YELLOW}%-35s %8d${C_RESET}\n" "$lib" "$refs"
            else
                printf "  ${C_GREEN}%-35s %8d${C_RESET}\n" "$lib" "$refs"
            fi
        }
    fi
done

echo ""
echo -e "${C_BLUE}▸ Resumen Porto vendor${C_RESET}"
echo "  Total libs: $total"
echo -e "  ${C_RED}Sin uso (0 refs): ${#zero_libs[@]}${C_RESET}  → candidatas a eliminar"

if [[ ${#zero_libs[@]} -gt 0 ]]; then
    echo ""
    echo -e "${C_RED}▸ Candidatas a eliminar (0 referencias)${C_RESET}"
    for lib in "${zero_libs[@]}"; do
        # tamaño en disco
        if [[ -d "$VENDOR_DIR/$lib" ]]; then
            size=$(du -sh "$VENDOR_DIR/$lib" 2>/dev/null | cut -f1 || echo '?')
            printf "  ${C_RED}✗${C_RESET} %-35s ${C_GRAY}(%s)${C_RESET}\n" "$lib" "$size"
        fi
    done
fi

# ── 2. Archivos CSS grandes ─────────────────────────────────────────────
if [[ "$MODE" != "--zero" && "$MODE" != "--summary" ]]; then
    echo ""
    echo -e "${C_BLUE}▸ Archivos CSS grandes (>200KB)${C_RESET}"
    find public -name "*.css" -size +200k 2>/dev/null \
        | xargs -I{} du -h {} 2>/dev/null \
        | sort -rh | head -10 | while read line; do
            echo "  $line"
        done
fi

# ── 3. Imports CDN externos en blade ────────────────────────────────────
if [[ "$MODE" != "--zero" && "$MODE" != "--summary" ]]; then
    echo ""
    echo -e "${C_BLUE}▸ CDN externos cargados en vistas${C_RESET}"
    GREP_CMD "cdnjs.cloudflare\|cdn.jsdelivr\|fonts.googleapis" resources/views modules 2>/dev/null \
        | xargs -I{} grep -hE "https?://[^\"' ]+\.(css|woff|woff2)" {} 2>/dev/null \
        | sed 's/.*\(https\?:\/\/[^"'"'"' ]*\).*/\1/' \
        | sort -u | head -15 | while read cdn; do
            echo "  $cdn"
        done
fi

# ── 4. Node modules (CSS packages) ──────────────────────────────────────
if [[ "$MODE" != "--zero" && "$MODE" != "--summary" ]]; then
    echo ""
    echo -e "${C_BLUE}▸ Paquetes NPM con CSS${C_RESET}"
    if [[ -f package.json ]]; then
        grep -E '"(bootstrap|element-ui|@ckeditor|vue-treeselect|chart.js|jqwidgets)"' package.json \
            | sed 's/[",]//g' | awk '{printf "  • %s %s\n", $1, $2}'
    fi
fi

echo ""
echo -e "${C_BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${C_RESET}"
echo -e "${C_BLUE}  Listo. Para limpiar libs sin uso:${C_RESET}"
echo -e "  ${C_GRAY}1. Verificar manualmente cada una (puede haber carga dinámica)${C_RESET}"
echo -e "  ${C_GRAY}2. Mover a public/porto-light/vendor/_trash/ por 2 semanas${C_RESET}"
echo -e "  ${C_GRAY}3. Si nadie reporta issue → eliminar con commit dedicado${C_RESET}"
echo ""
