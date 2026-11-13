<?php
declare(strict_types=1);

namespace Config;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $instance = null;
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

        $host = self::getEnv('DB_HOST', '127.0.0.1');
        $port = self::getEnv('DB_PORT', '1433');
        $dbname = self::getEnv('DB_NAME', '');
        $user = self::getEnv('DB_USER', '');
        $pass = self::getEnv('DB_PASS', '');
        $driver = self::getEnv('DB_DRIVER', 'sqlsrv');

        if ($driver === 'sqlsrv') {
            $dsn = "sqlsrv:Server={$host},{$port};Database={$dbname};TrustServerCertificate=true";
        } else {
            $dsn = "dblib:host={$host}:{$port};dbname={$dbname}";
        }

        try {
            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return self::$instance;
        } catch (PDOException $e) {
            error_log('[DB ERROR] ' . $e->getMessage());
            throw new RuntimeException('Error de conexión a la base de datos SQL Server.');
        }
    }
}
