<?php
declare(strict_types=1);

// Carga de configuración y seguridad
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/Assets.php';
require_once __DIR__ . '/../src/Utils/View.php';
require_once __DIR__ . '/../src/Utils/AuditService.php';
require_once __DIR__ . '/../src/Database/MigrationRunner.php';
require_once __DIR__ . '/../src/Auth/RateLimiter.php';
require_once __DIR__ . '/../src/Auth/AuthController.php';
require_once __DIR__ . '/../src/Deboning/CorrelativeService.php';
require_once __DIR__ . '/../src/Deboning/ConfigService.php';
require_once __DIR__ . '/../src/Deboning/ConfigController.php';
require_once __DIR__ . '/../src/Deboning/ReceptionService.php';
require_once __DIR__ . '/../src/Deboning/ReceptionController.php';
require_once __DIR__ . '/../src/Deboning/YieldCalculator.php';
require_once __DIR__ . '/../src/Deboning/DeboningService.php';
require_once __DIR__ . '/../src/Deboning/DeboningController.php';

use Config\Database;
use Config\Security;
use Src\Database\MigrationRunner;
use Src\Auth\AuthController;
use Src\Deboning\ConfigController;
use Src\Deboning\ReceptionController;
use Src\Deboning\DeboningController;
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

// Router minimalista nativo
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Manejo de rutas
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
        $user = AuthController::checkAuth();
        View::render('home', [
            'user' => $user
        ]);
        break;

    case '/configuracion':
        ConfigController::index();
        break;

    case '/recepcion':
        ReceptionController::index();
        break;

    case '/api/recepcion/crear':
        if ($requestMethod === 'POST') {
            ReceptionController::create();
        }
        break;

    case '/api/recepcion/detalle':
        ReceptionController::detail();
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

    case '/desposte':
    case '/deboning':
        DeboningController::index();
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

    case '/api/desposte/cerrar':
        if ($requestMethod === 'POST') {
            DeboningController::close();
        }
        break;

    case '/api/desposte/recepciones-disponibles':
        DeboningController::availableReceptions();
        break;

    case '/api/desposte/activas':
        DeboningController::active();
        break;

    default:
        http_response_code(404);
        echo "404 - Página no encontrada";
        break;
}
