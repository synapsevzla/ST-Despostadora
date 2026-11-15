<?php
declare(strict_types=1);

namespace Src\Auth;

use Config\Database;
use Config\Security;
use Src\Database\MigrationRunner;
use Src\Utils\AuditService;
use Src\Utils\View;
use PDO;
use Exception;

final class AuthController
{
    public static function showLogin(?string $error = null): void
    {
        Security::initSession();

        // Si ya está autenticado, redirigir al home
        if (!empty($_SESSION['user_codusua'])) {
            Security::redirect('/');
        }

        // Si no se pasó un error previo y la conexión a la base de datos falla, mostrar alerta
        if ($error === null && !Database::checkConnection()) {
            $error = Database::MSG_OFFLINE;
        }

        $csrfToken = Security::generateCsrfToken();
        View::render('login', [
            'csrf_token' => $csrfToken,
            'error' => $error,
            'title' => 'Iniciar Sesión - ST Despostadora'
        ]);
    }

    public static function handleLogin(): void
    {
        Security::initSession();

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            self::showLogin('Token de seguridad inválido o expirado. Intente de nuevo.');
            return;
        }

        $codusua = trim((string)($_POST['codusua'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $clave = (string)($_POST['clave'] ?? '');

        if ($codusua === '' || $email === '' || $clave === '') {
            self::showLogin('Por favor complete todos los campos requeridos.');
            return;
        }

        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $rateKey = "login_{$clientIp}_{$codusua}";

        if (!RateLimiter::check($rateKey, 5, 300)) {
            AuditService::log('SEGURIDAD', $codusua !== '' ? $codusua : 'DESCONOCIDO', 'BLOQUEO_INTENTOS', $codusua !== '' ? $codusua : 'SYSTEM', [
                'motivo' => 'Excedido límite de 5 intentos fallidos (bloqueo 5 min)',
                'ip' => $clientIp
            ]);
            self::showLogin('Demasiados intentos fallidos. Espere 5 minutos antes de reintentar.');
            return;
        }

        try {
            $pdo = Database::getConnection();

            $sql = "SELECT codusUa, descrip, email,
                RTRIM(LTRIM(SUBSTRING(SDATA3,175,1)+
                SUBSTRING(SDATA1,33,1)+
                SUBSTRING(SDATA2,90,1)+
                SUBSTRING(SDATA3,14,1)+
                SUBSTRING(SDATA1,207,1)+
                SUBSTRING(SDATA3,111,1)+
                SUBSTRING(SDATA3,145,1)+
                SUBSTRING(SDATA2,180,1)+
                SUBSTRING(SDATA2,9,1)+
                SUBSTRING(SDATA3,53,1))) AS Clave,
                RTRIM(LTRIM(SUBSTRING(SDATA2,193,1)+
                SUBSTRING(SDATA1,77,1))) AS Nivel,
                ISNULL(CodVend,'') AS CodVend 
                FROM ssusrs
                WHERE CodUsua = :codusua AND Email = :email";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':codusua', $codusua, PDO::PARAM_STR);
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch();

            if (!$user || !hash_equals((string)$user['Clave'], $clave)) {
                RateLimiter::hit($rateKey);
                AuditService::log('SEGURIDAD', $codusua, 'LOGIN_FALLIDO', $codusua, [
                    'email' => $email,
                    'motivo' => 'Credenciales inválidas',
                    'ip' => $clientIp
                ]);
                self::showLogin('Credenciales incorrectas. Verifique usuario, email o contraseña.');
                return;
            }

            // Validar restricción de acceso por nivel (solo nivel 01 y 02 autorizados)
            $nivel = trim((string)$user['Nivel']);
            $nivelNum = (int)$nivel;
            if ($nivel !== '01' && $nivel !== '02' && $nivelNum !== 1 && $nivelNum !== 2) {
                RateLimiter::hit($rateKey);
                AuditService::log('SEGURIDAD', $codusua, 'LOGIN_NIVEL_DENEGADO', $codusua, [
                    'nivel' => $nivel,
                    'motivo' => 'Nivel no permitido para la aplicación',
                    'ip' => $clientIp
                ]);
                self::showLogin('Acceso no autorizado. Esta aplicación solo permite el ingreso a usuarios de nivel 01 o 02.');
                return;
            }

            // Login exitoso
            RateLimiter::clear($rateKey);
            session_regenerate_id(true);

            $_SESSION['user_codusua'] = trim((string)$user['codusUa']);
            $_SESSION['user_descrip'] = trim((string)$user['descrip']);
            $_SESSION['user_email']   = trim((string)$user['email']);
            $_SESSION['user_nivel']   = trim((string)$user['Nivel']);
            $_SESSION['user_codvend'] = trim((string)$user['CodVend']);
            $_SESSION['logged_at']    = time();

            AuditService::log('SEGURIDAD', trim((string)$user['codusUa']), 'LOGIN_EXITOSO', trim((string)$user['codusUa']), [
                'nivel' => $nivel,
                'descrip' => trim((string)$user['descrip']),
                'email' => trim((string)$user['email']),
                'ip' => $clientIp
            ]);

            // Ejecución de migraciones pendientes tras login exitoso
            try {
                MigrationRunner::runPending();
            } catch (\Throwable $mEx) {
                error_log('[MIGRATION ERROR ON LOGIN] ' . $mEx->getMessage());
            }

            Security::redirect('/');
        } catch (\Throwable $e) {
            error_log('[LOGIN ERROR] ' . $e->getMessage());
            $errorMsg = Database::isConnectionError($e) 
                ? Database::MSG_OFFLINE 
                : 'No se pudo establecer conexión con el servidor de datos.';
            self::showLogin($errorMsg);
        }
    }

    public static function handleLogout(): void
    {
        Security::initSession();
        $codusua = (string)($_SESSION['user_codusua'] ?? '');
        if ($codusua !== '') {
            AuditService::log('SEGURIDAD', $codusua, 'LOGOUT', $codusua, [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        Security::redirect('/login');
    }

    public static function checkAuth(): array
    {
        Security::initSession();
        if (empty($_SESSION['user_codusua'])) {
            Security::redirect('/login');
        }

        return [
            'codusua' => $_SESSION['user_codusua'],
            'descrip' => $_SESSION['user_descrip'] ?? '',
            'email'   => $_SESSION['user_email'] ?? '',
            'nivel'   => $_SESSION['user_nivel'] ?? '',
            'codvend' => $_SESSION['user_codvend'] ?? '',
        ];
    }

    public static function isLevelOne(?string $nivel): bool
    {
        $n = trim((string)$nivel);
        return $n === '01' || $n === '1' || (int)$n === 1;
    }

    public static function requireAdmin(): array
    {
        $user = self::checkAuth();
        if (!self::isLevelOne($user['nivel'] ?? '')) {
            AuditService::log('SEGURIDAD', (string)($user['codusua'] ?? 'DESCONOCIDO'), 'ACCESO_403_DENEGADO', (string)($user['codusua'] ?? 'DESCONOCIDO'), [
                'url' => (string)($_SERVER['REQUEST_URI'] ?? ''),
                'nivel' => (string)($user['nivel'] ?? ''),
                'metodo' => (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
            http_response_code(403);
            View::render('errors/403', [
                'user' => $user,
                'title' => 'Acceso Denegado'
            ]);
            exit;
        }

        return $user;
    }

    public static function requireAdminApi(): array
    {
        Security::initSession();
        if (empty($_SESSION['user_codusua'])) {
            http_response_code(401);
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'error' => 'Sesión no iniciada o expirada. Por favor inicie sesión.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $user = [
            'codusua' => (string)$_SESSION['user_codusua'],
            'descrip' => (string)($_SESSION['user_descrip'] ?? ''),
            'email'   => (string)($_SESSION['user_email'] ?? ''),
            'nivel'   => (string)($_SESSION['user_nivel'] ?? ''),
            'codvend' => (string)($_SESSION['user_codvend'] ?? ''),
        ];

        if (!self::isLevelOne($user['nivel'])) {
            AuditService::log('SEGURIDAD', $user['codusua'], 'ACCESO_403_API_DENEGADO', $user['codusua'], [
                'url' => (string)($_SERVER['REQUEST_URI'] ?? ''),
                'nivel' => $user['nivel'],
                'metodo' => (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
            http_response_code(403);
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'error' => 'Acceso denegado: Privilegio de Administrador (Nivel 01 o 1) requerido para esta operación.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        return $user;
    }
}
