#!/usr/bin/env bash
#==============================================================================
# Soluciones Totales Despostadora - Script de Empaquetado Linux (.deb)
# Archivo: dist_linux.sh
# Propósito: Empaquetar dist_web en un instalador Debian (.deb) para Linux
#            con Electron, servidor PHP, puerto dinámico y Wizard .env
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
DIST_LINUX_DIR="${PROJECT_ROOT}/dist_linux"

echo -e "${CLR_BOLD}${CLR_BLUE}====================================================================${CLR_RESET}"
echo -e "${CLR_BOLD}  ST DESPOSTADORA - GENERACIÓN DE INSTALADOR LINUX (.deb)${CLR_RESET}"
echo -e "${CLR_BOLD}${CLR_BLUE}====================================================================${CLR_RESET}"
info "Directorio del proyecto : ${PROJECT_ROOT}"
info "Destino de distribución : ${DIST_LINUX_DIR}"

# 1. Comprobación de herramientas necesarias
info "Verificando herramientas del sistema..."
command -v node >/dev/null 2>&1 || fail "Node.js no está instalado."
command -v npm >/dev/null 2>&1 || fail "npm no está instalado."
command -v dpkg-deb >/dev/null 2>&1 || fail "dpkg-deb no está instalado."
success "Herramientas del sistema verificadas."

# 2. Asegurar que dist_web esté recién compilado y validado
info "Compilando y verificando versión ofuscada dist_web..."
"${PROJECT_ROOT}/dist_web.sh"

[[ -d "${DIST_WEB_DIR}/public" ]] || fail "No se encontró ${DIST_WEB_DIR}/public tras ejecutar dist_web.sh"
success "Código fuente ofuscado listo en dist_web."

# 3. Asegurar dependencias de Electron
info "Verificando dependencias en electron/node_modules..."
if [[ ! -d "${ELECTRON_DIR}/node_modules" ]]; then
    info "Instalando dependencias de Electron..."
    (cd "${ELECTRON_DIR}" && npm install)
fi
success "Dependencias de Electron listas."

# 4. Asegurar Icono Corporativo para Linux
if [[ ! -f "${ELECTRON_DIR}/assets/icon.png" ]]; then
    info "Generando icono corporativo (.png)..."
    if command -v convert >/dev/null 2>&1; then
        convert -background none -density 300 -resize 256x256 "${ELECTRON_DIR}/assets/icon.svg" "${ELECTRON_DIR}/assets/icon.png"
    fi
fi

# 5. Preparar directorio de destino
mkdir -p "${DIST_LINUX_DIR}"

# 6. Compilar paquete .deb con electron-builder para Linux x64
info "Empaquetando instalador Debian (.deb) con electron-builder..."
(
    cd "${ELECTRON_DIR}"
    npm run build:linux
)

# 7. Verificación final de entregables
info "Verificando entregables en dist_linux/..."
DEB_COUNT=$(find "${DIST_LINUX_DIR}" -maxdepth 1 -type f -name "*.deb" | wc -l)

if [[ ${DEB_COUNT} -eq 0 ]]; then
    fail "No se generó el paquete .deb en ${DIST_LINUX_DIR}."
fi

TOTAL_LINUX_SIZE=$(du -sh "${DIST_LINUX_DIR}" | awk '{print $1}')

echo -e "\n${CLR_BOLD}${CLR_GREEN}====================================================================${CLR_RESET}"
echo -e "${CLR_BOLD}${CLR_GREEN}  ¡DISTRIBUCIÓN DEBITAN (.deb) PARA LINUX GENERADA CON ÉXITO!      ${CLR_RESET}"
echo -e "${CLR_BOLD}${CLR_GREEN}====================================================================${CLR_RESET}"
echo -e "  - Directorio de salida  : ${CLR_BOLD}${DIST_LINUX_DIR}${CLR_RESET}"
echo -e "  - Tamaño del paquete    : ${CLR_BOLD}${TOTAL_LINUX_SIZE}${CLR_RESET}"
echo -e "  - Paquetes generados    :"
find "${DIST_LINUX_DIR}" -maxdepth 1 -type f -name "*.deb" -exec ls -lh {} \; | awk '{print "    * " $9 " (" $5 ")"}'
echo -e "${CLR_BOLD}${CLR_BLUE}====================================================================${CLR_RESET}\n"
