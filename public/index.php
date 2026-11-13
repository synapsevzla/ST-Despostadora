<?php
declare(strict_types=1);

// Carga de configuración y seguridad
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/Assets.php';
require_once __DIR__ . '/../src/Utils/View.php';
require_once __DIR__ . '/../src/Database/MigrationRunner.php';
require_once __DIR__ . '/../src/Auth/RateLimiter.php';
require_once __DIR__ . '/../src/Auth/AuthController.php';

use Config\Database;
use Config\Security;
use Src\Database\MigrationRunner;
use Src\Auth\AuthController;
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

    case '/deboning':
        $user = AuthController::checkAuth();
        // Placeholder para la sala de desposte
        View::render('home', [
            'user' => $user
        ]);
        break;

    default:
        http_response_code(404);
        echo "404 - Página no encontrada";
        break;
}
