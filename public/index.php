<?php
declare(strict_types=1);

// En el servidor embebido de PHP (CLI server), servir archivos estáticos existentes directamente
if (php_sapi_name() === 'cli-server') {
    $requestedPath = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (is_file($requestedPath)) {
        return false;
    }
}

// Autocarga nativa PSR-4 ligera para evitar errores de clases no encontradas
spl_autoload_register(function (string $class): void {
    $prefixes = [
        'Src\\' => dirname(__DIR__) . '/src/',
        'Config\\' => dirname(__DIR__) . '/config/',
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        if (str_starts_with($class, $prefix)) {
            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $baseDir . $relative . '.php';
            if (is_file($file)) {
                require_once $file;
                return;
            }
        }
    }
});

// Carga de configuración y seguridad
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/Assets.php';
require_once __DIR__ . '/../src/Utils/View.php';
require_once __DIR__ . '/../src/Utils/AuditService.php';
require_once __DIR__ . '/../src/Utils/BarcodeService.php';
require_once __DIR__ . '/../src/Database/MigrationRunner.php';
require_once __DIR__ . '/../src/Auth/RateLimiter.php';
require_once __DIR__ . '/../src/Auth/LicenseService.php';
require_once __DIR__ . '/../src/Auth/AuthController.php';
require_once __DIR__ . '/../src/Deboning/CorrelativeService.php';
require_once __DIR__ . '/../src/Deboning/ConfigService.php';
require_once __DIR__ . '/../src/Deboning/ConfigController.php';
require_once __DIR__ . '/../src/Deboning/ReceptionErpHoldService.php';
require_once __DIR__ . '/../src/Deboning/ReceptionService.php';
require_once __DIR__ . '/../src/Deboning/ReceptionController.php';
require_once __DIR__ . '/../src/Deboning/YieldCalculator.php';
require_once __DIR__ . '/../src/Deboning/DeboningErpHoldService.php';
require_once __DIR__ . '/../src/Deboning/DeboningCutsAuditService.php';
require_once __DIR__ . '/../src/Deboning/DeboningSyncService.php';
require_once __DIR__ . '/../src/Deboning/DeboningQueryService.php';
require_once __DIR__ . '/../src/Deboning/DeboningAnnulmentService.php';
require_once __DIR__ . '/../src/Deboning/DeboningService.php';
require_once __DIR__ . '/../src/Deboning/DeboningController.php';
require_once __DIR__ . '/../src/Deboning/DashboardService.php';
require_once __DIR__ . '/../src/Deboning/DashboardController.php';
require_once __DIR__ . '/../src/Audit/AuditQueryService.php';
require_once __DIR__ . '/../src/Audit/AuditController.php';

use Config\Database;
use Config\Security;
use Src\Database\MigrationRunner;
use Src\Auth\AuthController;
use Src\Auth\LicenseService;
use Src\Deboning\ConfigController;
use Src\Deboning\ReceptionController;
use Src\Deboning\DeboningController;
use Src\Deboning\DashboardController;
use Src\Audit\AuditController;
use Src\Utils\View;

// Iniciar sesión segura y cabeceras HTTP
Security::initSession();
Security::setSecurityHeaders();

// Cargar variables de entorno
Database::loadEnv(dirname(__DIR__) . '/.env');

// Ejecución autónoma de migraciones si .env está configurado y hay conexión
try {
    MigrationRunner::runPending();
} catch (\Throwable $e) {
    error_log('[MIGRATION ERROR] ' . $e->getMessage());
}

// Router minimalista nativo con soporte para subcarpetas (XAMPP / Hostings Compartidos)
$rawUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/.');
if ($baseDir !== '' && str_starts_with($rawUri, $baseDir)) {
    $rawUri = substr($rawUri, strlen($baseDir)) ?: '/';
} elseif (str_ends_with($baseDir, '/public')) {
    $parentBase = substr($baseDir, 0, -7);
    if ($parentBase !== '' && str_starts_with($rawUri, $parentBase)) {
        $rawUri = substr($rawUri, strlen($parentBase)) ?: '/';
    }
}

$requestUri = ($rawUri !== '/' && str_ends_with($rawUri, '/')) ? rtrim($rawUri, '/') : $rawUri;
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Comprobación de estado y vigencia de Licencia
$licenseInfo = LicenseService::check();

// Endpoints de licencia (disponibles siempre, incluso con licencia bloqueada)
if ($requestUri === '/api/licencia/status') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($licenseInfo['valid'] ? 200 : 403);
    echo json_encode($licenseInfo, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($requestUri === '/api/licencia/verificar') {
    if ($requestMethod === 'POST') {
        $result = LicenseService::verifyOnline();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($result['valid'] ? 200 : 400);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Si la licencia está bloqueada (vencida, inválida, reloj alterado o sin activación)
if ($licenseInfo['valid'] === false) {
    if ($requestUri === '/logout') {
        AuthController::handleLogout();
        exit;
    }

    $isApi = str_starts_with($requestUri, '/api/')
        || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    if ($isApi) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'blocked' => true,
            'error' => $licenseInfo['message'],
            'license_status' => $licenseInfo['status'],
            'license' => $licenseInfo
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(403);
    View::render('license_error', [
        'license' => $licenseInfo,
        'title' => 'Licencia Requerida - ST Despostadora'
    ]);
    exit;
}

// Manejo de rutas
try {
    switch ($requestUri) {
    case '/login':
        if ($requestMethod === 'POST') {
            AuthController::handleLogin();
        } else {
            AuthController::showLogin();
        }
        break;

    case '/logout':
        AuthController::handleLogout();
        break;

    case '/':
        DashboardController::index();
        break;

    case '/configuracion':
        ConfigController::index();
        break;

    case '/recepcion':
        ReceptionController::index();
        break;

    case '/recepcion/nueva':
        ReceptionController::nueva();
        break;

    case '/recepcion/imprimir':
        ReceptionController::imprimir();
        break;

    case '/api/recepcion/crear':
        if ($requestMethod === 'POST') {
            ReceptionController::create();
        }
        break;

    case '/api/recepcion/detalle':
        ReceptionController::detail();
        break;

    case '/api/recepcion/erp-status':
        ReceptionController::erpStatus();
        break;

    case '/api/config/general':
        if ($requestMethod === 'POST') {
            ConfigController::saveGeneral();
        }
        break;

    case '/api/config/correlativo':
        if ($requestMethod === 'POST') {
            ConfigController::updateCorrelative();
        }
        break;

    case '/api/config/productos':
        ConfigController::searchProducts();
        break;

    case '/api/config/especie':
        if ($requestMethod === 'POST') {
            ConfigController::saveSpecies();
        }
        break;

    case '/api/config/cortes':
        ConfigController::getCuts();
        break;

    case '/api/config/cortes/agregar':
        if ($requestMethod === 'POST') {
            ConfigController::addCut();
        }
        break;

    case '/api/config/cortes/eliminar':
        if ($requestMethod === 'POST') {
            ConfigController::removeCut();
        }
        break;

    case '/api/config/mesas':
        ConfigController::getMesas();
        break;

    case '/api/config/mesas/guardar':
        if ($requestMethod === 'POST') {
            ConfigController::saveMesa();
        }
        break;

    case '/api/config/mesas/estado':
        if ($requestMethod === 'POST') {
            ConfigController::toggleMesaStatus();
        }
        break;

    case '/api/config/mesas/eliminar':
        if ($requestMethod === 'POST') {
            ConfigController::deleteMesa();
        }
        break;

    case '/desposte':
    case '/deboning':
        DeboningController::index();
        break;

    case '/desposte/nueva':
    case '/deboning/nueva':
        DeboningController::nueva();
        break;

    case '/desposte/imprimir':
    case '/deboning/imprimir':
        DeboningController::imprimir();
        break;

    case '/api/desposte/abrir':
        if ($requestMethod === 'POST') {
            DeboningController::open();
        }
        break;

    case '/api/desposte/pesar':
        if ($requestMethod === 'POST') {
            DeboningController::weigh();
        }
        break;

    case '/api/desposte/eliminar-corte':
        if ($requestMethod === 'POST') {
            DeboningController::deleteCut();
        }
        break;

    case '/api/desposte/balance':
        DeboningController::balance();
        break;

    case '/api/desposte/sync':
        DeboningController::sync();
        break;

    case '/api/desposte/modificar-corte':
        if ($requestMethod === 'POST') {
            DeboningController::updateCutWeight();
        }
        break;

    case '/api/desposte/bitacora':
        DeboningController::auditLog();
        break;

    case '/api/desposte/erp-status':
        DeboningController::erpStatus();
        break;

    case '/api/desposte/cerrar':
        if ($requestMethod === 'POST') {
            DeboningController::close();
        }
        break;

    case '/api/desposte/anular':
        if ($requestMethod === 'POST') {
            DeboningController::annul();
        }
        break;

    case '/api/desposte/recepciones-disponibles':
        DeboningController::availableReceptions();
        break;

    case '/api/desposte/activas':
        DeboningController::active();
        break;

    case '/api/dashboard/kpis':
        DashboardController::apiSummary();
        break;

    case '/auditoria':
        AuditController::index();
        break;

    case '/api/auditoria/kpis':
        AuditController::apiKpis();
        break;

    case '/api/auditoria/eventos':
        AuditController::apiEvents();
        break;

    case '/api/auditoria/pesajes-log':
        AuditController::apiCutsLog();
        break;

    case '/api/auditoria/mermas-criticas':
        AuditController::apiYieldAnomalies();
        break;

    case '/api/auditoria/seguridad':
        AuditController::apiSecurity();
        break;

    case '/api/auditoria/trazabilidad':
        AuditController::apiTraceability();
        break;

    case '/api/auditoria/exportar-csv':
        AuditController::exportCsv();
        break;

    default:
        http_response_code(404);
        echo "404 - Página no encontrada";
        break;
    }
} catch (\Throwable $e) {
    if (Database::isConnectionError($e)) {
        error_log('[DB CONNECTION ERROR] ' . $e->getMessage());
        $isApi = str_starts_with($requestUri, '/api/')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        if ($isApi) {
            Database::handleApiError($e);
        } else {
            Database::renderErrorView(Database::MSG_OFFLINE);
        }
        exit;
    }

    error_log('[SERVER ERROR] ' . $e->getMessage());
    http_response_code(500);
    echo "500 - Error interno del servidor.";
}
