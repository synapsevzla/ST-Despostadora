<?php
declare(strict_types=1);

namespace Config;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;
use Src\Utils\View;

final class Database
{
    public const MSG_OFFLINE = 'Base de datos o servidor fuera de línea por favor contacte a soporte técnico';

    private static ?PDO $instance = null;
    private static bool $connectionFailed = false;
    private static ?Throwable $lastConnectionError = null;
    /** @var array<string, string> */
    private static array $env = [];

    private function __construct() {}

    public static function loadEnv(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $parts = explode('=', $trimmed, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $val = trim($parts[1]);
                $val = trim($val, "\"'");
                self::$env[$key] = $val;
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
            }
        }
    }

    public static function getEnv(string $key, string $default = ''): string
    {
        return self::$env[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }

    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        if (self::$connectionFailed) {
            throw self::$lastConnectionError ?? new RuntimeException(self::MSG_OFFLINE);
        }

        $host = self::getEnv('DB_HOST', '127.0.0.1');
        $port = self::getEnv('DB_PORT', '1433');
        $dbname = self::getEnv('DB_NAME', '');
        $user = self::getEnv('DB_USER', '');
        $pass = self::getEnv('DB_PASS', '');
        $driver = self::getEnv('DB_DRIVER', 'sqlsrv');
        $timeout = self::getEnv('DB_TIMEOUT', '4');

        if ($driver === 'sqlsrv') {
            $dsn = "sqlsrv:Server={$host},{$port};Database={$dbname};TrustServerCertificate=true;LoginTimeout={$timeout}";
        } else {
            $dsn = "dblib:host={$host}:{$port};dbname={$dbname};timeout={$timeout}";
        }

        try {
            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return self::$instance;
        } catch (PDOException $e) {
            self::$connectionFailed = true;
            error_log('[DB ERROR] ' . $e->getMessage());
            self::$lastConnectionError = new RuntimeException(self::MSG_OFFLINE, 0, $e);
            throw self::$lastConnectionError;
        }
    }

    public static function checkConnection(): bool
    {
        try {
            self::getConnection();
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function isConnectionError(Throwable $e): bool
    {
        if ($e->getMessage() === self::MSG_OFFLINE) {
            return true;
        }

        $prev = $e->getPrevious();
        if ($prev !== null && self::isConnectionError($prev)) {
            return true;
        }

        $code = (string)$e->getCode();
        if (in_array($code, ['08001', '08003', '08004', '08S01', 'HYT00', '20002', 'IM002'], true)) {
            return true;
        }

        $msg = mb_strtolower($e->getMessage(), 'UTF-8');
        $keywords = [
            'fuera de línea',
            'base de datos o servidor',
            'server is unavailable',
            'connection refused',
            'tcp provider',
            'login timeout',
            'network error',
            'network-related',
            'could not connect',
            'unable to connect',
            'no connection',
            'conexión',
            'comunicación',
            'sqlsrv',
            'dblib',
        ];

        foreach ($keywords as $kw) {
            if (str_contains($msg, $kw)) {
                return true;
            }
        }

        return false;
    }

    public static function filterErrorMessage(Throwable $e): string
    {
        if (self::isConnectionError($e)) {
            return self::MSG_OFFLINE;
        }
        return $e->getMessage();
    }

    public static function renderErrorView(?string $message = null): void
    {
        http_response_code(503);
        if (!class_exists(View::class, false)) {
            require_once dirname(__DIR__) . '/src/Utils/View.php';
        }
        View::render('db_error', [
            'title' => 'Error de Conexión - ST Despostadora',
            'message' => $message ?? self::MSG_OFFLINE,
        ]);
        exit;
    }

    public static function handleApiError(Throwable $e): void
    {
        error_log('[API DB ERROR] ' . $e->getMessage());
        http_response_code(503);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => self::MSG_OFFLINE
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
