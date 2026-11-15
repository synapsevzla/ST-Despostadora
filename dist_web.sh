#!/usr/bin/env bash
#==============================================================================
# Soluciones Totales Despostadora - Script de Ofuscación y Distribución Web
# Archivo: dist_web.sh
# Propósito: Ofuscar código PHP (yakpro-po) y JavaScript (javascript-obfuscator),
#            empaquetar recursos limpios y verificar integridad en dist_web/
#==============================================================================

set -euo pipefail

# Colores de terminal para visualización clara
CLR_RESET='\033[0m'
CLR_RED='\033[0;31m'
CLR_GREEN='\033[0;32m'
CLR_YELLOW='\033[1;33m'
CLR_BLUE='\033[0;34m'
CLR_CYAN='\033[0;36m'
CLR_BOLD='\033[1m'

info() {
    echo -e "${CLR_CYAN}[INFO]${CLR_RESET} $*"
}

success() {
    echo -e "${CLR_GREEN}[OK]${CLR_RESET} $*"
}

warn() {
    echo -e "${CLR_YELLOW}[WARN]${CLR_RESET} $*"
}

fail() {
    echo -e "${CLR_RED}[ERROR]${CLR_RESET} $*" >&2
    exit 1
}

# 1. Determinar directorios absolutos
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST_DIR="${PROJECT_ROOT}/dist_web"
YAKPRO_BIN="${PROJECT_ROOT}/yakpro-po/yakpro-po.php"
YAKPRO_CNF="${PROJECT_ROOT}/yakpro-po/yakpro-po.cnf"

echo -e "${CLR_BOLD}${CLR_BLUE}====================================================================${CLR_RESET}"
echo -e "${CLR_BOLD}  ST DESPOSTADORA - GENERACIÓN DE DISTRIBUCIÓN OFUSCADA (dist_web)${CLR_RESET}"
echo -e "${CLR_BOLD}${CLR_BLUE}====================================================================${CLR_RESET}"
info "Directorio del proyecto : ${PROJECT_ROOT}"
info "Destino de distribución : ${DIST_DIR}"

# 2. Verificación de dependencias requeridas
info "Verificando dependencias de compilación..."
command -v php >/dev/null 2>&1 || fail "PHP no está instalado o no está en el PATH."
command -v node >/dev/null 2>&1 || fail "Node.js no está instalado o no está en el PATH."
command -v npx >/dev/null 2>&1 || fail "npx no está instalado o no está en el PATH."

PHP_VER=$(php -r 'echo PHP_VERSION;')
NODE_VER=$(node -v)
info "PHP Detectado : ${PHP_VER}"
info "Node Detectado: ${NODE_VER}"

[[ -f "${YAKPRO_BIN}" ]] || fail "No se encontró el ejecutable de yakpro-po en: ${YAKPRO_BIN}"
[[ -f "${YAKPRO_CNF}" ]] || fail "No se encontró la configuración en: ${YAKPRO_CNF}"
success "Todas las dependencias están disponibles."

# 3. Preparación de directorios temporales y limpieza de dist_web
info "Preparando directorio temporal de construcción..."
STAGING_DIR=$(mktemp -d -t st_dist_build_XXXXXX)
trap 'rm -rf "${STAGING_DIR}"' EXIT

if [[ -d "${DIST_DIR}" ]]; then
    warn "Limpiando directorio dist_web previo..."
    rm -rf "${DIST_DIR}"
fi
mkdir -p "${DIST_DIR}"

# 4. Ofuscación de Backend PHP con yakpro-po
info "Iniciando ofuscación de código PHP con yakpro-po..."
php "${YAKPRO_BIN}" --config-file "${YAKPRO_CNF}" "${PROJECT_ROOT}" -o "${STAGING_DIR}"

OBFUSCATED_SOURCE="${STAGING_DIR}/yakpro-po/obfuscated"
[[ -d "${OBFUSCATED_SOURCE}" ]] || fail "La compilación de yakpro-po no produjo el directorio esperado."

# Copiar el resultado ofuscado a dist_web
info "Copiando estructura base ofuscada hacia dist_web..."
cp -a "${OBFUSCATED_SOURCE}/." "${DIST_DIR}/"

# El directorio de contexto de yakpro-po jamás debe distribuirse
if [[ -d "${STAGING_DIR}/yakpro-po/context" ]]; then
    info "Descartando diccionario de símbolos (context/) para máxima seguridad..."
fi

# 5. Ofuscación de Frontend JavaScript con javascript-obfuscator
info "Iniciando ofuscación de scripts JavaScript con javascript-obfuscator..."
mkdir -p "${DIST_DIR}/public/js"

npx --yes javascript-obfuscator "${PROJECT_ROOT}/public/js" \
    --output "${DIST_DIR}/public/js" \
    --compact true \
    --control-flow-flattening true \
    --control-flow-flattening-threshold 0.75 \
    --numbers-to-expressions true \
    --simplify true \
    --string-array true \
    --string-array-encoding base64 \
    --string-array-threshold 0.8 \
    --split-strings true \
    --split-strings-chunk-length 5 \
    --rename-properties false \
    --rename-globals false \
    --log false

success "Todos los scripts JavaScript fueron ofuscados con éxito."

# 6. Sincronización y Aseguramiento de Recursos
info "Asegurando recursos estáticos, plantillas y migraciones..."

# CSS
mkdir -p "${DIST_DIR}/public/css"
cp -a "${PROJECT_ROOT}/public/css/." "${DIST_DIR}/public/css/"

# Migraciones SQL (necesarias para MigrationRunner::runPending)
mkdir -p "${DIST_DIR}/database/migrations"
cp -a "${PROJECT_ROOT}/database/migrations/." "${DIST_DIR}/database/migrations/"

# Plantillas de vistas (preservadas en t_keep)
mkdir -p "${DIST_DIR}/views"
cp -a "${PROJECT_ROOT}/views/." "${DIST_DIR}/views/"

# Plantilla de variables de entorno
if [[ -f "${PROJECT_ROOT}/.env.example" ]]; then
    cp -f "${PROJECT_ROOT}/.env.example" "${DIST_DIR}/.env.example"
fi

# Bundle de Certificados CA (requerido para cURL / OpenSSL en Windows)
if [[ -f "${PROJECT_ROOT}/config/cacert.pem" ]]; then
    mkdir -p "${DIST_DIR}/config"
    cp -f "${PROJECT_ROOT}/config/cacert.pem" "${DIST_DIR}/config/cacert.pem"
fi

# 7. Depuración de Archivos Internos y Confidenciales en dist_web
info "Eliminando archivos internos, documentos y artefactos de desarrollo..."
rm -f "${DIST_DIR}/GEMINI.md" \
     "${DIST_DIR}/README.md" \
     "${DIST_DIR}/.gitignore" \
     "${DIST_DIR}/.env" \
     "${DIST_DIR}/license_cache.json" \
     "${DIST_DIR}/dist_web.sh" \
     "${DIST_DIR}/dist_webxampp.sh" \
     "${DIST_DIR}/dist_windows.sh" \
     "${DIST_DIR}/dist_linux.sh" 2>/dev/null || true

rm -rf "${DIST_DIR}/yakpro-po" \
       "${DIST_DIR}/dist_windows" \
       "${DIST_DIR}/dist_linux" \
       "${DIST_DIR}/dist_web" \
       "${DIST_DIR}/dist_webxampp" \
       "${DIST_DIR}/electron" \
       "${DIST_DIR}/scratch" \
       "${DIST_DIR}/VARIOS" \
       "${DIST_DIR}/.git" 2>/dev/null || true

# 8. Verificación de Integridad Sintáctica (Zero-Breakage Check)
info "Ejecutando verificación de integridad estática (php -l y node --check)..."

# Verificar cada archivo PHP generado
PHP_FAIL_COUNT=0
while IFS= read -r -d '' php_file; do
    if ! php -l "${php_file}" >/dev/null 2>&1; then
        echo -e "${CLR_RED}[SYNTAX ERROR]${CLR_RESET} Archivo PHP corrupto: ${php_file}" >&2
        PHP_FAIL_COUNT=$((PHP_FAIL_COUNT + 1))
    fi
done < <(find "${DIST_DIR}" -type f -name "*.php" -print0)

if [[ ${PHP_FAIL_COUNT} -gt 0 ]]; then
    fail "Se detectaron ${PHP_FAIL_COUNT} errores sintácticos en archivos PHP ofuscados."
fi
success "Validación sintáctica PHP superada sin errores."

# Verificar cada archivo JS generado
JS_FAIL_COUNT=0
while IFS= read -r -d '' js_file; do
    if ! node --check "${js_file}" >/dev/null 2>&1; then
        echo -e "${CLR_RED}[SYNTAX ERROR]${CLR_RESET} Archivo JS corrupto: ${js_file}" >&2
        JS_FAIL_COUNT=$((JS_FAIL_COUNT + 1))
    fi
done < <(find "${DIST_DIR}/public/js" -type f -name "*.js" -print0)

if [[ ${JS_FAIL_COUNT} -gt 0 ]]; then
    fail "Se detectaron ${JS_FAIL_COUNT} errores sintácticos en archivos JavaScript ofuscados."
fi
success "Validación sintáctica JavaScript superada sin errores."

# 9. Asignación de Permisos Seguros
find "${DIST_DIR}" -type d -exec chmod 755 {} +
find "${DIST_DIR}" -type f -exec chmod 644 {} +

# 10. Resumen Final de Compilación
PHP_COUNT=$(find "${DIST_DIR}" -type f -name "*.php" | wc -l)
JS_COUNT=$(find "${DIST_DIR}/public/js" -type f -name "*.js" | wc -l)
CSS_COUNT=$(find "${DIST_DIR}/public/css" -type f -name "*.css" | wc -l)
SQL_COUNT=$(find "${DIST_DIR}/database/migrations" -type f -name "*.sql" | wc -l)
TOTAL_SIZE=$(du -sh "${DIST_DIR}" | awk '{print $1}')

echo -e "\n${CLR_BOLD}${CLR_GREEN}====================================================================${CLR_RESET}"
echo -e "${CLR_BOLD}${CLR_GREEN}  ¡DISTRIBUCIÓN GENERADA Y VALIDADA CON ÉXITO!                     ${CLR_RESET}"
echo -e "${CLR_BOLD}${CLR_GREEN}====================================================================${CLR_RESET}"
echo -e "  - Directorio resultante : ${CLR_BOLD}${DIST_DIR}${CLR_RESET}"
echo -e "  - Tamaño total          : ${CLR_BOLD}${TOTAL_SIZE}${CLR_RESET}"
echo -e "  - Archivos PHP ofuscados: ${CLR_BOLD}${PHP_COUNT}${CLR_RESET}"
echo -e "  - Archivos JS ofuscados : ${CLR_BOLD}${JS_COUNT}${CLR_RESET}"
echo -e "  - Hojas de estilo CSS   : ${CLR_BOLD}${CSS_COUNT}${CLR_RESET}"
echo -e "  - Migraciones SQL       : ${CLR_BOLD}${SQL_COUNT}${CLR_RESET}"
echo -e "  - Archivos sensibles    : ${CLR_BOLD}0 (Excluidos .env, .git, yakpro-po)${CLR_RESET}"
echo -e "${CLR_BOLD}${CLR_BLUE}====================================================================${CLR_RESET}\n"
