# Soluciones Totales Despostadora - Guía de Despliegue en XAMPP y Hostings Compartidos

Esta guía detalla el procedimiento para poner en marcha **ST Despostadora** en entornos **XAMPP (Windows / Linux)** o en **Hostings Compartidos (cPanel / DirectAdmin / Plesk)**.

---

## 1. Requisitos del Servidor Web

| Requisito | Versión Mínima | Detalle |
|---|---|---|
| **PHP** | 8.1+ (8.2 / 8.3 / 8.4 recomendados) | Con tipado estricto habilitado |
| **Microsoft SQL Server Driver** | `pdo_sqlsrv` (o `sqlsrv` / `pdo_dblib`) | Controlador oficial para conectar a la BD de Saint ERP |
| **Extensiones PHP** | `curl`, `openssl`, `mbstring`, `fileinfo`, `json`, `pdo` | Requeridas para licenciamiento, seguridad y operaciones |
| **Apache Web Server** | 2.4+ | Con módulos `mod_rewrite` y `mod_headers` habilitados |
| **Base de Datos** | Microsoft SQL Server 2012 / 2016 / 2019 / 2022 | Instancia donde reside la base de datos administrativa |

---

## 2. Instalación en XAMPP (Windows)

### Paso 1: Ubicación de Archivos
1. Descargue y descomprima el archivo `st_despostadora_xampp.zip` (o copie el contenido de `dist_webxampp/`).
2. Coloque la carpeta en la raíz web de XAMPP:
   `C:\xampp\htdocs\despostadora\`

### Paso 2: Habilitar los Controladores de Microsoft SQL Server
XAMPP en Windows no incluye `pdo_sqlsrv` de forma predeterminada.
1. Descargue los controladores oficiales de Microsoft para PHP:
   [Microsoft Drivers for PHP for SQL Server](https://github.com/microsoft/msphpsql/releases)
   *(Seleccione la versión compatible con su versión de PHP y arquitectura x64 **Thread Safe (TS)**)*.
2. Copie los archivos DLL en la carpeta de extensiones de PHP:
   - `php_pdo_sqlsrv_XX_ts_x64.dll`
   - `php_sqlsrv_XX_ts_x64.dll`
   en: `C:\xampp\php\ext\`
3. Abra `C:\xampp\php\php.ini` y agregue al final de la sección de extensiones:
   ```ini
   extension=php_pdo_sqlsrv_XX_ts_x64.dll
   extension=php_sqlsrv_XX_ts_x64.dll
   ```
4. Reinicie el servicio de Apache desde el Panel de Control de XAMPP.

### Paso 3: Configuración de Variables de Entorno (.env)
1. En la carpeta del proyecto (`C:\xampp\htdocs\despostadora\`), cree una copia del archivo `.env.example` y renómbrela a `.env`.
2. Configure los datos de conexión a su servidor SQL Server:
   ```env
   DB_DRIVER=sqlsrv
   DB_HOST=127.0.0.1
   DB_PORT=1433
   DB_NAME=EnterpriseAdminDBPruebastrigger
   DB_USER=sa
   DB_PASS=SuClaveSQLSegura
   DB_TIMEOUT=15
   ```
   *(Nota: El serial de licencia se toma automáticamente de la tabla `saconf` del ERP. No es necesario ni se utiliza la clave `APP_LICENSE` en `.env`)*

### Paso 4: Diagnóstico y Validación
1. Abra su navegador web y visite el script de diagnóstico:
   `http://localhost/despostadora/check_env.php`
2. Verifique que todos los ítems marquen **[Correcto]**.
3. Ingrese a la aplicación en:
   `http://localhost/despostadora/`

---

## 3. Instalación en Hosting Compartido (cPanel / Apache / LiteSpeed)

### Opción 1: Despliegue en la Raíz de un Dominio o Subdominio (Recomendada)
1. Ingrese a su **cPanel** > **Administrador de Archivos**.
2. Cargue `st_despostadora_xampp.zip` en la carpeta raíz de su dominio (ej. `public_html/` o `despostadora.suempresa.com/`).
3. Haga clic derecho en el archivo y seleccione **Extraer (Extract)**.
4. En cPanel, vaya a **Seleccionar Versión de PHP (Select PHP Version)**:
   - Seleccione **PHP 8.1, 8.2 o 8.3**.
   - En la pestaña **Extensiones**, asegúrese de marcar:
     `pdo_sqlsrv`, `curl`, `openssl`, `mbstring`, `fileinfo`, `json`.
5. Renombre `.env.example` a `.env` y ajuste las credenciales de conexión al servidor Microsoft SQL Server donde reside Saint ERP.
6. Ingrese a su dominio: `https://despostadora.suempresa.com/`.

### Opción 2: Despliegue en Subdirectorio (`public_html/despostadora/`)
1. Extraiga los archivos en `public_html/despostadora/`.
2. El archivo `.htaccess` raíz redirige de manera transparente todo el tráfico a `public/` y protege los directorios sensibles (`config/`, `database/`, `src/`, `views/`).
3. El enrutador detecta automáticamente el prefijo `/despostadora` y gestiona las rutas `/login`, `/recepcion`, `/deboning`, etc.
4. Acceda desde su navegador a:
   `https://suempresa.com/despostadora/`

---

## 4. Estructura de Seguridad en Apache

La distribución incluye una doble capa de protección mediante archivos `.htaccess`:

1. **`.htaccess` (Raíz):**
   - Desactiva el listado de directorios (`Options -Indexes`).
   - Bloquea terminantemente el acceso HTTP a archivos `.env`, `.git`, `.sql`, `.log`, `.sh`, `.md`, `.json`.
   - Deniega el acceso a directorios de lógica interna (`config/`, `database/`, `src/`, `views/`).
   - Enruta el tráfico dinámico hacia `public/`.

2. **`public/.htaccess`:**
   - Activa el motor de reescritura `mod_rewrite`.
   - Entrega archivos estáticos reales directamente (`css/`, `js/`, imágenes).
   - Dirige todas las solicitudes de rutas hacia el Front Controller `public/index.php`.
   - Inyecta cabeceras de seguridad HTTP (`X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`).
