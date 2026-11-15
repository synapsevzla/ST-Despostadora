#!/usr/bin/env bash
#==============================================================================
# Soluciones Totales Despostadora - Script de Distribución XAMPP y Hostings
# Archivo: dist_webxampp.sh
# Propósito: Empaquetar dist_web con configuraciones Apache (.htaccess),
#            fallback front-controller, diagnóstico de entorno, plantilla
#            de VirtualHost y archivo ZIP listo para cPanel / XAMPP.
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
DIST_WEB_DIR="${PROJECT_ROOT}/dist_web"
DIST_XAMPP_DIR="${PROJECT_ROOT}/dist_webxampp"
DIST_WEB_SCRIPT="${PROJECT_ROOT}/dist_web.sh"
ZIP_FILE="${DIST_XAMPP_DIR}/st_despostadora_xampp.zip"

echo -e "${CLR_BOLD}${CLR_BLUE}====================================================================${CLR_RESET}"
echo -e "${CLR_BOLD}  ST DESPOSTADORA - GENERACIÓN DISTRIBUCIÓN XAMPP / HOSTING WEB    ${CLR_RESET}"
echo -e "${CLR_BOLD}${CLR_BLUE}====================================================================${CLR_RESET}"
info "Directorio del proyecto : ${PROJECT_ROOT}"
info "Destino de distribución : ${DIST_XAMPP_DIR}"

# 2. Verificación de dependencias del entorno
info "Verificando dependencias del sistema..."
command -v bash >/dev/null 2>&1 || fail "Bash no está disponible."
command -v php >/dev/null 2>&1 || fail "PHP no está instalado o no está en el PATH."
command -v node >/dev/null 2>&1 || fail "Node.js no está instalado o no está en el PATH."
command -v npx >/dev/null 2>&1 || fail "npx no está disponible."

PHP_VER=$(php -r 'echo PHP_VERSION;')
NODE_VER=$(node -v)
info "PHP Detectado : ${PHP_VER}"
info "Node Detectado: ${NODE_VER}"
success "Herramientas de entorno verificadas."

# 3. Compilar y ofuscar la base con dist_web.sh
info "Compilando y verificando versión ofuscada con dist_web.sh..."
[[ -f "${DIST_WEB_SCRIPT}" ]] || fail "No se encontró el script base: ${DIST_WEB_SCRIPT}"
chmod +x "${DIST_WEB_SCRIPT}"
"${DIST_WEB_SCRIPT}"

[[ -d "${DIST_WEB_DIR}/public" ]] || fail "No se encontró ${DIST_WEB_DIR}/public tras ejecutar dist_web.sh"
success "Código fuente ofuscado base listo en dist_web."

# 4. Preparación de dist_webxampp/
if [[ -d "${DIST_XAMPP_DIR}" ]]; then
    warn "Limpiando directorio dist_webxampp previo..."
    rm -rf "${DIST_XAMPP_DIR}"
fi
mkdir -p "${DIST_XAMPP_DIR}"

info "Copiando estructura ofuscada base hacia dist_webxampp..."
cp -a "${DIST_WEB_DIR}/." "${DIST_XAMPP_DIR}/"

# 5. Configurar Apache .htaccess en la raíz de dist_webxampp
info "Configurando seguridad y enrutador Apache en la raíz (.htaccess)..."
cat << 'EOF' > "${DIST_XAMPP_DIR}/.htaccess"
#==============================================================================
# Soluciones Totales Despostadora - Configuración de Raíz Apache
# Archivo: .htaccess (Raíz de Proyecto para XAMPP y Hostings Compartidos)
#==============================================================================

# Deshabilitar listado de directorios
Options -Indexes
Options +FollowSymLinks

# Proteger archivos sensibles, logs, configuraciones y documentación
<FilesMatch "^\.env|^\.git|\.(sql|json|log|sh|md|bak|conf|dist)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
</FilesMatch>

# Enrutamiento hacia public/ y bloqueo de carpetas protegidas
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Bloquear acceso directo a directorios protegidos
    RewriteRule ^(config|database|src|views|scratch|electron|yakpro-po)(\/|$) - [F,L,NC]
    RewriteRule ^\.env - [F,L,NC]

    # Reenviar peticiones que no ingresen por public/ hacia public/
    RewriteCond %{REQUEST_URI} !^.*/public/ [NC]
    RewriteRule ^(.*)$ public/$1 [L,QSA]
</IfModule>

<IfModule !mod_rewrite.c>
    DirectoryIndex index.php public/index.php
</IfModule>
EOF

# 6. Configurar fallback Front Controller en la raíz (index.php)
info "Configurando fallback Front Controller en la raíz (index.php)..."
cat << 'EOF' > "${DIST_XAMPP_DIR}/index.php"
<?php
declare(strict_types=1);

/**
 * Soluciones Totales Despostadora
 * Fallback Front Controller para Apache / XAMPP / Hosting Compartido
 */
$publicIndex = __DIR__ . '/public/index.php';
if (is_file($publicIndex)) {
    require_once $publicIndex;
} else {
    http_response_code(500);
    echo 'Error: public/index.php no encontrado.';
}
EOF

# 7. Configurar Apache .htaccess en public/
info "Configurando enrutador Apache en public/.htaccess..."
cat << 'EOF' > "${DIST_XAMPP_DIR}/public/.htaccess"
#==============================================================================
# Soluciones Totales Despostadora - Enrutador Apache Front Controller
# Archivo: public/.htaccess
#==============================================================================

# Deshabilitar listado de directorios
Options -Indexes
Options +FollowSymLinks

# Establecer juego de caracteres UTF-8 predeterminado
AddDefaultCharset UTF-8

<IfModule mod_rewrite.c>
    RewriteEngine On

    # Servir archivos o directorios estáticos existentes directamente
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]

    # Redirigir el resto de peticiones al front controller index.php
    RewriteRule ^ index.php [QSA,L]
</IfModule>

<IfModule !mod_rewrite.c>
    ErrorDocument 404 index.php
</IfModule>

# Cabeceras de seguridad y optimización de caché para estáticos
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"

    <FilesMatch "\.(css|js|svg|png|jpg|jpeg|gif|ico|woff|woff2|ttf|eot)$">
        Header set Cache-Control "max-age=604800, public"
    </FilesMatch>
</IfModule>
EOF

# 8. Sincronizar utilidades, plantillas de configuración y documentación
info "Incorporando herramientas de diagnóstico, plantilla VirtualHost y manuales..."

# Diagnóstico de entorno (check_env.php)
if [[ -f "${PROJECT_ROOT}/check_env.php" ]]; then
    cp -f "${PROJECT_ROOT}/check_env.php" "${DIST_XAMPP_DIR}/check_env.php"
fi

# Plantilla de VirtualHost
if [[ -f "${PROJECT_ROOT}/config/apache_vhost.example.conf" ]]; then
    cp -f "${PROJECT_ROOT}/config/apache_vhost.example.conf" "${DIST_XAMPP_DIR}/apache_vhost.example.conf"
fi

# Manual Markdown
if [[ -f "${PROJECT_ROOT}/README_XAMPP.md" ]]; then
    cp -f "${PROJECT_ROOT}/README_XAMPP.md" "${DIST_XAMPP_DIR}/README_XAMPP.md"
fi

# Manual de texto plano para técnicos de soporte
cat << 'EOF' > "${DIST_XAMPP_DIR}/LEAME_INSTALACION_XAMPP.txt"
==============================================================================
SOLUCIONES TOTALES DESPOSTADORA - GUIA DE INSTALACION RAPIDA XAMPP / HOSTING
==============================================================================

1. REQUISITOS DEL SISTEMA:
--------------------------
* PHP 8.1 o superior (8.2 / 8.3 / 8.4 recomendado).
* Microsoft SQL Server 2012 o superior (instancia donde reside Saint ERP).
* Controladores PHP para SQL Server habilitados:
  - Windows: php_pdo_sqlsrv_XX_ts_x64.dll y php_sqlsrv_XX_ts_x64.dll
  - Linux  : pdo_sqlsrv (o sqlsrv / pdo_dblib)
* Extensiones PHP requeridas: curl, openssl, mbstring, fileinfo, json, pdo.
* Apache con mod_rewrite y mod_headers activos.

2. INSTALACION EN XAMPP (WINDOWS):
----------------------------------
a) Extraiga esta carpeta en:
   C:\xampp\htdocs\despostadora\

b) Copie '.env.example' como '.env' y configure las credenciales de SQL Server:
   DB_DRIVER=sqlsrv
   DB_HOST=127.0.0.1
   DB_PORT=1433
   DB_DATABASE=EnterpriseAdminDB
   DB_USERNAME=sa
   DB_PASSWORD=SuClaveSQL
   DB_ENCRYPT=false
   DB_TRUST_SERVER_CERT=true

c) Verifique que su php.ini (C:\xampp\php\php.ini) tenga activos los controladores SQL:
   extension=php_pdo_sqlsrv
   extension=php_sqlsrv

d) Compruebe el estado del entorno en su navegador:
   http://localhost/despostadora/check_env.php

e) Ingrese al sistema:
   http://localhost/despostadora/

3. INSTALACION EN HOSTING COMPARTIDO (cPanel):
----------------------------------------------
a) Cargue el archivo 'st_despostadora_xampp.zip' a cPanel > Administrador de Archivos.
b) Extraigalo en la raiz de su dominio (public_html/) o en una subcarpeta (public_html/despostadora/).
c) En cPanel > Seleccionar Version de PHP, asegurese de seleccionar PHP 8.1+ y
   habilitar las extensiones: pdo_sqlsrv, curl, openssl, mbstring, fileinfo.
d) Copie '.env.example' como '.env' con las credenciales de su servidor de base de datos.
e) Ingrese desde su navegador a su dominio o subdirectorio.

Para soporte tecnico adicional, contacte al distribuidor autorizado de Soluciones Totales.
==============================================================================
EOF

# 9. Limpieza de artefactos de desarrollo en dist_webxampp
info "Depurando artefactos innecesarios..."
rm -f "${DIST_XAMPP_DIR}/GEMINI.md" \
     "${DIST_XAMPP_DIR}/README.md" \
     "${DIST_XAMPP_DIR}/.gitignore" \
     "${DIST_XAMPP_DIR}/.env" \
     "${DIST_XAMPP_DIR}/license_cache.json" \
     "${DIST_XAMPP_DIR}/dist_web.sh" \
     "${DIST_XAMPP_DIR}/dist_webxampp.sh" \
     "${DIST_XAMPP_DIR}/dist_windows.sh" \
     "${DIST_XAMPP_DIR}/dist_linux.sh" 2>/dev/null || true

# 10. Verificación de Integridad Sintáctica de PHP
info "Ejecutando verificación sintáctica estática (php -l)..."
PHP_FAIL_COUNT=0
while IFS= read -r -d '' php_file; do
    if ! php -l "${php_file}" >/dev/null 2>&1; then
        echo -e "${CLR_RED}[SYNTAX ERROR]${CLR_RESET} Archivo PHP corrupto: ${php_file}" >&2
        PHP_FAIL_COUNT=$((PHP_FAIL_COUNT + 1))
    fi
done < <(find "${DIST_XAMPP_DIR}" -type f -name "*.php" -print0)

if [[ ${PHP_FAIL_COUNT} -gt 0 ]]; then
    fail "Se detectaron ${PHP_FAIL_COUNT} errores sintácticos en archivos PHP."
fi
success "Validación sintáctica superada sin errores."

# 11. Ajuste de Permisos del Directorio
find "${DIST_XAMPP_DIR}" -type d -exec chmod 755 {} +
find "${DIST_XAMPP_DIR}" -type f -exec chmod 644 {} +

# 12. Generación del Paquete ZIP Portátil (para cPanel y XAMPP)
info "Generando paquete ZIP portátil para despliegue (st_despostadora_xampp.zip)..."
rm -f "${ZIP_FILE}" 2>/dev/null || true

if command -v zip >/dev/null 2>&1; then
    (
        cd "${DIST_XAMPP_DIR}"
        zip -rq "${ZIP_FILE}" . -x "st_despostadora_xampp.zip"
    )
else
    # Fallback nativo con PHP ZipArchive
    php -r "
        \$zip = new ZipArchive();
        if (\$zip->open('${ZIP_FILE}', ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            \$files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator('${DIST_XAMPP_DIR}', RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach (\$files as \$file) {
                \$filePath = \$file->getRealPath();
                if (basename(\$filePath) === 'st_despostadora_xampp.zip') continue;
                \$relativePath = substr(\$filePath, strlen('${DIST_XAMPP_DIR}') + 1);
                \$zip->addFile(\$filePath, \$relativePath);
            }
            \$zip->close();
        }
    "
fi

[[ -f "${ZIP_FILE}" ]] || fail "No se pudo generar el archivo ZIP de distribución."
chmod 644 "${ZIP_FILE}"
success "Paquete ZIP generado exitosamente."

# 13. Métricas y Resumen Final de Compilación
PHP_COUNT=$(find "${DIST_XAMPP_DIR}" -type f -name "*.php" | wc -l)
JS_COUNT=$(find "${DIST_XAMPP_DIR}/public/js" -type f -name "*.js" | wc -l)
CSS_COUNT=$(find "${DIST_XAMPP_DIR}/public/css" -type f -name "*.css" | wc -l)
SQL_COUNT=$(find "${DIST_XAMPP_DIR}/database/migrations" -type f -name "*.sql" | wc -l)
TOTAL_DIR_SIZE=$(du -sh "${DIST_XAMPP_DIR}" | awk '{print $1}')
ZIP_SIZE=$(du -sh "${ZIP_FILE}" | awk '{print $1}')

echo -e "\n${CLR_BOLD}${CLR_GREEN}====================================================================${CLR_RESET}"
echo -e "${CLR_BOLD}${CLR_GREEN}  ¡DISTRIBUCIÓN XAMPP / HOSTING GENERADA Y VALIDADA CON ÉXITO!     ${CLR_RESET}"
echo -e "${CLR_BOLD}${CLR_GREEN}====================================================================${CLR_RESET}"
echo -e "  - Carpeta resultante    : ${CLR_BOLD}${DIST_XAMPP_DIR}${CLR_RESET}"
echo -e "  - Archivo ZIP portátil  : ${CLR_BOLD}${ZIP_FILE}${CLR_RESET}"
echo -e "  - Tamaño carpeta        : ${CLR_BOLD}${TOTAL_DIR_SIZE}${CLR_RESET}"
echo -e "  - Tamaño archivo ZIP    : ${CLR_BOLD}${ZIP_SIZE}${CLR_RESET}"
echo -e "  - Archivos PHP          : ${CLR_BOLD}${PHP_COUNT}${CLR_RESET} (Ofuscados con yakpro-po)"
echo -e "  - Archivos JS           : ${CLR_BOLD}${JS_COUNT}${CLR_RESET} (Ofuscados con javascript-obfuscator)"
echo -e "  - Hojas de estilo CSS   : ${CLR_BOLD}${CSS_COUNT}${CLR_RESET}"
echo -e "  - Migraciones SQL       : ${CLR_BOLD}${SQL_COUNT}${CLR_RESET}"
echo -e "  - Seguridad Apache      : ${CLR_BOLD}Doble .htaccess (Raíz y public/) + index.php fallback${CLR_RESET}"
echo -e "  - Herramientas extra    : ${CLR_BOLD}check_env.php, apache_vhost.example.conf, LEAME${CLR_RESET}"
echo -e "${CLR_BOLD}${CLR_BLUE}====================================================================${CLR_RESET}\n"
