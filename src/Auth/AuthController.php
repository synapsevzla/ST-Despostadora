<?php
declare(strict_types=1);

namespace Src\Auth;

use Config\Database;
use Config\Security;
use Src\Database\MigrationRunner;
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
            header('Location: /');
            exit;
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
                self::showLogin('Credenciales incorrectas. Verifique usuario, email o contraseña.');
                return;
            }

            // Validar restricción de acceso por nivel (solo nivel 01 y 02 autorizados)
            $nivel = trim((string)$user['Nivel']);
            $nivelNum = (int)$nivel;
            if ($nivel !== '01' && $nivel !== '02' && $nivelNum !== 1 && $nivelNum !== 2) {
                RateLimiter::hit($rateKey);
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

            // Ejecución de migraciones pendientes tras login exitoso
            try {
                MigrationRunner::runPending();
            } catch (\Throwable $mEx) {
                error_log('[MIGRATION ERROR ON LOGIN] ' . $mEx->getMessage());
            }

            header('Location: /');
            exit;
        } catch (Exception $e) {
            error_log('[LOGIN ERROR] ' . $e->getMessage());
            self::showLogin('No se pudo establecer conexión con el servidor de datos.');
        }
    }

    public static function handleLogout(): void
    {
        Security::initSession();
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
        header('Location: /login');
        exit;
    }

    public static function checkAuth(): array
    {
        Security::initSession();
        if (empty($_SESSION['user_codusua'])) {
            header('Location: /login');
            exit;
        }

        return [
            'codusua' => $_SESSION['user_codusua'],
            'descrip' => $_SESSION['user_descrip'] ?? '',
            'email'   => $_SESSION['user_email'] ?? '',
            'nivel'   => $_SESSION['user_nivel'] ?? '',
            'codvend' => $_SESSION['user_codvend'] ?? '',
        ];
    }
}
