<?php
declare(strict_types=1);

/**
 * Soluciones Totales Despostadora
 * Script de Diagnóstico de Entorno (XAMPP / Hosting Compartido / Apache / CLI)
 * 
 * Uso en consola : php check_env.php
 * Uso en navegador: http://localhost/despostadora/check_env.php
 */

$isCli = (php_sapi_name() === 'cli');

$checks = [];

// 1. Versión de PHP
$phpVersion = PHP_VERSION;
$phpOk = version_compare($phpVersion, '8.1.0', '>=');
$checks[] = [
    'name' => 'Versión de PHP',
    'status' => $phpOk,
    'detail' => "PHP {$phpVersion}" . ($phpOk ? ' (Compatible con PHP 8.1+)' : ' (Se requiere PHP 8.1 o superior)'),
    'critical' => true
];

// 2. Extensiones Base Requeridas
$reqExts = [
    'pdo' => 'Capa de abstracción PDO',
    'curl' => 'Cliente cURL (Licenciamiento Firebase REST)',
    'openssl' => 'Criptografía OpenSSL y HTTPS',
    'mbstring' => 'Cadenas Multibyte UTF-8',
    'fileinfo' => 'Detección MIME Fileinfo',
    'json' => 'Serialización y parser JSON'
];

foreach ($reqExts as $ext => $desc) {
    $loaded = extension_loaded($ext);
    $checks[] = [
        'name' => "Extensión PHP: {$ext}",
        'status' => $loaded,
        'detail' => $loaded ? "Habilitada ({$desc})" : "FALTA: Habilite 'extension={$ext}' en su php.ini",
        'critical' => true
    ];
}

// 3. Controlador de Microsoft SQL Server
$sqlsrvDrivers = [];
if (extension_loaded('pdo_sqlsrv')) $sqlsrvDrivers[] = 'pdo_sqlsrv';
if (extension_loaded('sqlsrv')) $sqlsrvDrivers[] = 'sqlsrv';
if (extension_loaded('pdo_dblib')) $sqlsrvDrivers[] = 'pdo_dblib';

$hasSqlsrv = !empty($sqlsrvDrivers);
$checks[] = [
    'name' => 'Controlador SQL Server (Saint ERP)',
    'status' => $hasSqlsrv,
    'detail' => $hasSqlsrv 
        ? 'Detectado: ' . implode(', ', $sqlsrvDrivers)
        : "FALTA: Instale los controladores Microsoft SQL Server para PHP (php_pdo_sqlsrv)",
    'critical' => true
];

// 4. Módulos de Apache (si está disponible apache_get_modules)
if (function_exists('apache_get_modules')) {
    $apacheMods = apache_get_modules();
    $rewriteOk = in_array('mod_rewrite', $apacheMods, true);
    $headersOk = in_array('mod_headers', $apacheMods, true);

    $checks[] = [
        'name' => 'Módulo Apache mod_rewrite',
        'status' => $rewriteOk,
        'detail' => $rewriteOk ? 'Cargado y activo' : 'FALTA: Descomente "LoadModule rewrite_module modules/mod_rewrite.so" en httpd.conf',
        'critical' => true
    ];

    $checks[] = [
        'name' => 'Módulo Apache mod_headers',
        'status' => $headersOk,
        'detail' => $headersOk ? 'Cargado y activo' : 'Opcional: Descomente "LoadModule headers_module modules/mod_headers.so" en httpd.conf',
        'critical' => false
    ];
}

// 5. Archivo de Configuración .env
$envFile = __DIR__ . '/.env';
$envExists = is_file($envFile) && is_readable($envFile);
$checks[] = [
    'name' => 'Archivo de Configuración (.env)',
    'status' => $envExists,
    'detail' => $envExists 
        ? 'Archivo .env presente y legible' 
        : "No encontrado. Copie '.env.example' como '.env' y configure las credenciales de su SQL Server",
    'critical' => true
];

// 6. Prueba de Conexión a Base de Datos SQL Server
$dbConnected = false;
$dbMessage = 'No evaluada (se requiere .env y pdo_sqlsrv)';
$saconfSerial = '';
$saconfDescrip = '';

if ($envExists && $hasSqlsrv) {
    try {
        $dbFile = __DIR__ . '/config/database.php';
        if (is_file($dbFile)) {
            require_once $dbFile;
            \Config\Database::loadEnv($envFile);
            $pdo = \Config\Database::getConnection();
            $stmt = $pdo->query("SELECT @@VERSION AS ver, DB_NAME() AS dbname");
            $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
            $dbConnected = true;
            $dbName = (string)($row['dbname'] ?? 'SQL Server');
            $rawVer = (string)($row['ver'] ?? '');
            $shortVer = preg_match('/Microsoft SQL Server \d{4}/', $rawVer, $m) ? $m[0] : 'Microsoft SQL Server';
            $dbMessage = "Conexión exitosa a {$shortVer} | Base de datos: '{$dbName}'";

            // Consultar serial y razón social desde saconf
            try {
                $stmtSaconf = $pdo->query("SELECT TOP 1 NroSerial, Descrip FROM saconf ORDER BY CASE WHEN CodSucu = '00000' THEN 0 ELSE 1 END");
                if ($stmtSaconf) {
                    $rowSaconf = $stmtSaconf->fetch(\PDO::FETCH_ASSOC);
                    if (is_array($rowSaconf)) {
                        $saconfSerial = trim((string)($rowSaconf['NroSerial'] ?? ''));
                        $saconfDescrip = trim((string)($rowSaconf['Descrip'] ?? ''));
                    }
                }
            } catch (\Throwable) {
                $saconfSerial = '';
            }
        } else {
            $dbMessage = 'No se encontró config/database.php';
        }
    } catch (\Throwable $e) {
        $dbConnected = false;
        $dbMessage = 'Error al conectar: ' . $e->getMessage();
    }

    $checks[] = [
        'name' => 'Conexión a Microsoft SQL Server',
        'status' => $dbConnected,
        'detail' => $dbMessage,
        'critical' => true
    ];

    if ($dbConnected) {
        $checks[] = [
            'name' => 'Serial Saint ERP en SACONF (Licenciamiento)',
            'status' => ($saconfSerial !== ''),
            'detail' => ($saconfSerial !== '')
                ? "Serial: {$saconfSerial} | Empresa: {$saconfDescrip}"
                : "No se encontró el serial en la tabla saconf. Verifique la base de datos de Saint ERP.",
            'critical' => true
        ];
    }
}

// 7. Permisos de Escritura para Caché Local
$cacheDir = __DIR__;
$cacheWritable = is_writable($cacheDir) || (is_file($cacheDir . '/license_cache.json') && is_writable($cacheDir . '/license_cache.json'));
$checks[] = [
    'name' => 'Permisos de Escritura (Caché local de Licencia)',
    'status' => $cacheWritable,
    'detail' => $cacheWritable ? 'Directorio raíz con permisos de escritura' : 'Aviso: Se requiere permiso de escritura para license_cache.json',
    'critical' => false
];

// 8. Detección de URL Base / Subcarpeta
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$subDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/.');
$checks[] = [
    'name' => 'Entorno Web y Ruta Base Detectada',
    'status' => true,
    'detail' => $isCli ? 'Ejecución por línea de comandos (CLI)' : 'Ruta base web: ' . ($subDir !== '' ? $subDir : '/ (Raíz de Dominio / VirtualHost)'),
    'critical' => false
];

// Salida para CLI (Consola de terminal)
if ($isCli) {
    echo "\n====================================================================\n";
    echo "  ST DESPOSTADORA - DIAGNÓSTICO DE COMPATIBILIDAD DE ENTORNO\n";
    echo "====================================================================\n\n";

    $allPassed = true;
    foreach ($checks as $c) {
        $tag = $c['status'] ? "\033[0;32m[OK]\033[0m" : ($c['critical'] ? "\033[0;31m[ERROR]\033[0m" : "\033[1;33m[AVISO]\033[0m");
        echo sprintf("%-8s %-40s\n         %s\n\n", $tag, $c['name'], $c['detail']);
        if (!$c['status'] && $c['critical']) {
            $allPassed = false;
        }
    }

    if ($allPassed) {
        echo "\033[0;32m✔ El entorno cumple con todos los requisitos necesarios para operar ST Despostadora.\033[0m\n\n";
    } else {
        echo "\033[0;31m✘ Existen dependencias críticas que deben corregirse antes de continuar.\033[0m\n\n";
    }
    exit($allPassed ? 0 : 1);
}

// Salida para Navegador Web
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Entorno - ST Despostadora</title>
    <style>
        :root {
            --bg-body: #12100e;
            --bg-card: #1c1815;
            --border: #2e2620;
            --text-main: #f5f2ed;
            --text-muted: #9e9389;
            --primary: #c2410c;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #d97706;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background: var(--bg-body); color: var(--text-main); padding: 2rem 1rem; line-height: 1.5; }
        .container { max-width: 800px; margin: 0 auto; }
        .header { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem 2rem; margin-bottom: 1.5rem; text-align: center; }
        .header h1 { font-size: 1.5rem; color: #fff; margin-bottom: 0.25rem; }
        .header p { color: var(--text-muted); font-size: 0.95rem; }
        .card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 1.5rem; }
        .check-item { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); }
        .check-item:last-child { border-bottom: none; }
        .check-title { font-weight: 600; font-size: 0.95rem; margin-bottom: 0.15rem; }
        .check-detail { font-size: 0.85rem; color: var(--text-muted); }
        .badge { padding: 0.3rem 0.65rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; flex-shrink: 0; }
        .badge-success { background: rgba(22, 163, 74, 0.15); color: #4ade80; border: 1px solid rgba(22, 163, 74, 0.3); }
        .badge-danger { background: rgba(220, 38, 38, 0.15); color: #f87171; border: 1px solid rgba(220, 38, 38, 0.3); }
        .badge-warning { background: rgba(217, 119, 6, 0.15); color: #fbbf24; border: 1px solid rgba(217, 119, 6, 0.3); }
        .actions { text-align: center; margin-top: 1.5rem; }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; border-radius: 8px; font-size: 0.95rem; font-weight: 600; text-decoration: none; color: #fff; background: var(--primary); transition: opacity 0.2s; }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>ST Despostadora - Diagnóstico de Entorno</h1>
            <p>Verificación de compatibilidad para XAMPP, Apache y Hosting Compartido</p>
        </div>

        <div class="card">
            <?php foreach ($checks as $c): ?>
                <div class="check-item">
                    <div>
                        <div class="check-title"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="check-detail"><?= htmlspecialchars($c['detail'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <?php if ($c['status']): ?>
                        <span class="badge badge-success">Correcto</span>
                    <?php elseif ($c['critical']): ?>
                        <span class="badge badge-danger">Falta</span>
                    <?php else: ?>
                        <span class="badge badge-warning">Aviso</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="actions">
            <a href="public/" class="btn">Continuar a ST Despostadora</a>
        </div>
    </div>
</body>
</html>
