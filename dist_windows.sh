#!/usr/bin/env bash
#==============================================================================
# Soluciones Totales Despostadora - Script de Empaquetado Windows Desktop
# Archivo: dist_windows.sh
# Propósito: Empaquetar dist_web en una aplicación nativa para Windows (.exe)
#            con Electron, servidor PHP embebido, puerto dinámico y Wizard .env
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

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ELECTRON_DIR="${PROJECT_ROOT}/electron"
DIST_WEB_DIR="${PROJECT_ROOT}/dist_web"
DIST_WIN_DIR="${PROJECT_ROOT}/dist_windows"
PHP_WIN_DIR="${ELECTRON_DIR}/php-win"

echo -e "${CLR_BOLD}${CLR_BLUE}====================================================================${CLR_RESET}"
echo -e "${CLR_BOLD}  ST DESPOSTADORA - GENERACIÓN DE INSTALADOR WINDOWS (dist_windows)${CLR_RESET}"
echo -e "${CLR_BOLD}${CLR_BLUE}====================================================================${CLR_RESET}"
info "Directorio del proyecto   : ${PROJECT_ROOT}"
info "Destino del instalador    : ${DIST_WIN_DIR}"

# 1. Comprobación de herramientas necesarias
info "Verificando herramientas del sistema..."
command -v node >/dev/null 2>&1 || fail "Node.js no está instalado."
command -v npm >/dev/null 2>&1 || fail "npm no está instalado."
command -v curl >/dev/null 2>&1 || fail "curl no está instalado."
command -v unzip >/dev/null 2>&1 || fail "unzip no está instalado."
success "Herramientas del sistema verificadas."

# 2. Asegurar que dist_web esté recién compilado y validado
info "Compilando y verificando versión ofuscada dist_web..."
"${PROJECT_ROOT}/dist_web.sh"

[[ -d "${DIST_WEB_DIR}/public" ]] || fail "No se encontró ${DIST_WEB_DIR}/public tras ejecutar dist_web.sh"
success "Código fuente ofuscado listo en dist_web."

# 3. Verificar o preparar el runtime de PHP Windows x64 embebido
if [[ ! -f "${PHP_WIN_DIR}/php.exe" || ! -f "${PHP_WIN_DIR}/ext/php_pdo_sqlsrv.dll" ]]; then
    warn "Runtime de PHP Windows no detectado. Descargando PHP 8.3 NTS x64 y controladores SQL Server..."
    mkdir -p "${PHP_WIN_DIR}/ext"
    TMP_DL=$(mktemp -d)

    info "Descargando PHP 8.3 NTS Windows x64..."
    curl -sL "https://downloads.php.net/~windows/releases/php-8.3.36-1-nts-Win32-vs16-x64.zip" -o "${TMP_DL}/php.zip"

    info "Descargando controladores Microsoft SQL Server (msphpsql)..."
    curl -sL "https://github.com/microsoft/msphpsql/releases/download/v5.13.3/Windows_5.13.3RTW.zip" -o "${TMP_DL}/msphpsql.zip"

    info "Descomprimiendo runtime PHP..."
    unzip -q -o "${TMP_DL}/php.zip" -d "${PHP_WIN_DIR}/"

    info "Extrayendo DLLs de sqlsrv..."
    unzip -p "${TMP_DL}/msphpsql.zip" Windows/php_pdo_sqlsrv_83_nts_x64.dll > "${PHP_WIN_DIR}/ext/php_pdo_sqlsrv.dll"
    unzip -p "${TMP_DL}/msphpsql.zip" Windows/php_sqlsrv_83_nts_x64.dll > "${PHP_WIN_DIR}/ext/php_sqlsrv.dll"

    rm -rf "${TMP_DL}"

    info "Creando php.ini optimizado..."
    cat << 'EOF' > "${PHP_WIN_DIR}/php.ini"
[PHP]
extension_dir = "ext"
extension=curl
extension=fileinfo
extension=mbstring
extension=openssl
extension=pdo
extension=pdo_sqlsrv
extension=sqlsrv

max_execution_time = 0
memory_limit = 512M
post_max_size = 64M
upload_max_filesize = 64M
date.timezone = America/Caracas
display_errors = Off
log_errors = On
error_log = "php_error.log"
EOF
    success "Runtime PHP Windows x64 y extensiones sqlsrv configuradas."
else
    success "Runtime PHP Windows x64 detectado y validado en electron/php-win/."
fi

# 4. Asegurar dependencias de Electron
info "Verificando dependencias en electron/node_modules..."
if [[ ! -d "${ELECTRON_DIR}/node_modules" ]]; then
    info "Instalando dependencias de Electron..."
    (cd "${ELECTRON_DIR}" && npm install)
fi
success "Dependencias de Electron listas."

# 5. Asegurar Iconos del Instalador
if [[ ! -f "${ELECTRON_DIR}/assets/icon.ico" ]]; then
    info "Generando icono corporativo (.ico)..."
    if command -v convert >/dev/null 2>&1; then
        convert -background none -density 300 -resize 256x256 "${ELECTRON_DIR}/assets/icon.svg" "${ELECTRON_DIR}/assets/icon.png"
        convert "${ELECTRON_DIR}/assets/icon.png" -define icon:auto-resize=256,128,64,48,32,16 "${ELECTRON_DIR}/assets/icon.ico"
    fi
fi

# 6. Preparar directorio de destino
mkdir -p "${DIST_WIN_DIR}"

# 7. Compilar aplicación con electron-builder para Windows (NSIS + Portable)
info "Empaquetando ejecutable de Windows con electron-builder..."
(
    cd "${ELECTRON_DIR}"
    ./node_modules/.bin/electron-builder --win --x64
)

# 8. Verificación final de entregables
info "Verificando entregables en dist_windows/..."
EXE_COUNT=$(find "${DIST_WIN_DIR}" -maxdepth 1 -type f -name "*.exe" | wc -l)

if [[ ${EXE_COUNT} -eq 0 ]]; then
    fail "No se generaron ejecutables .exe en ${DIST_WIN_DIR}."
fi

TOTAL_WIN_SIZE=$(du -sh "${DIST_WIN_DIR}" | awk '{print $1}')

echo -e "\n${CLR_BOLD}${CLR_GREEN}====================================================================${CLR_RESET}"
echo -e "${CLR_BOLD}${CLR_GREEN}  ¡DISTRIBUCIÓN DE ESCRITORIO WINDOWS GENERADA CON ÉXITO!          ${CLR_RESET}"
echo -e "${CLR_BOLD}${CLR_GREEN}====================================================================${CLR_RESET}"
echo -e "  - Directorio de salida  : ${CLR_BOLD}${DIST_WIN_DIR}${CLR_RESET}"
echo -e "  - Tamaño del paquete    : ${CLR_BOLD}${TOTAL_WIN_SIZE}${CLR_RESET}"
echo -e "  - Ejecutables generados :"
find "${DIST_WIN_DIR}" -maxdepth 1 -type f -name "*.exe" -exec ls -lh {} \; | awk '{print "    * " $9 " (" $5 ")"}'
echo -e "${CLR_BOLD}${CLR_BLUE}====================================================================${CLR_RESET}\n"
