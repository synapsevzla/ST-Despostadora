<?php
declare(strict_types=1);

namespace Src\Database;

use Config\Database;
use PDO;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    private static string $migrationsDir = '';

    public static function init(): void
    {
        self::$migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
    }

    /**
     * Ejecuta las migraciones de forma segura y autónoma si la BD está configurada y accesible.
     * Retorna un arreglo con el resultado de la operación.
     *
     * @return array{success: bool, executed: array<string>, error?: string}
     */
    public static function runPending(): array
    {
        self::init();

        $dbname = Database::getEnv('DB_NAME', '');
        $host = Database::getEnv('DB_HOST', '');
        if ($dbname === '' || $host === '') {
            return ['success' => false, 'executed' => [], 'error' => 'Base de datos no configurada en .env'];
        }

        try {
            $pdo = Database::getConnection();
        } catch (Throwable $e) {
            return ['success' => false, 'executed' => [], 'error' => $e->getMessage()];
        }

        self::ensureMigrationsTable($pdo);

        $applied = self::getAppliedMigrations($pdo);
        $files = self::getMigrationFiles();

        $executed = [];

        foreach ($files as $file) {
            $filename = basename($file);

            // Validar si ya fue aplicada previamente
            if (isset($applied[$filename])) {
                $fileChecksum = hash_file('sha256', $file);
                // Si la migración ya aplicada fue alterada, lanzar excepción según regla 2
                if ($applied[$filename]['checksum'] !== '' && !hash_equals($applied[$filename]['checksum'], $fileChecksum)) {
                    throw new RuntimeException("La migración '{$filename}' ha sido modificada tras ser aplicada. Por regla del sistema, no se permite editar migraciones previas; debe crearse una nueva.");
                }
                continue;
            }

            // Aplicar nueva migración
            self::executeMigration($pdo, $file, $filename);
            $executed[] = $filename;
        }

        return ['success' => true, 'executed' => $executed];
    }

    private static function ensureMigrationsTable(PDO $pdo): void
    {
        // Tabla de control: ST_Despostadora_Migrations
        $sql = "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ST_Despostadora_Migrations')
        BEGIN
            CREATE TABLE ST_Despostadora_Migrations (
                id INT IDENTITY(1,1) PRIMARY KEY,
                migration_name VARCHAR(255) NOT NULL UNIQUE,
                checksum VARCHAR(64) NOT NULL,
                applied_at DATETIME2 NOT NULL DEFAULT GETDATE()
            );
        END";

        $pdo->exec($sql);
    }

    /**
     * @return array<string, array{id: int, checksum: string, applied_at: string}>
     */
    private static function getAppliedMigrations(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT id, migration_name, checksum, applied_at FROM ST_Despostadora_Migrations");
        $results = [];
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[(string)$row['migration_name']] = [
                    'id' => (int)$row['id'],
                    'checksum' => (string)($row['checksum'] ?? ''),
                    'applied_at' => (string)$row['applied_at'],
                ];
            }
        }
        return $results;
    }

    /**
     * @return list<string>
     */
    private static function getMigrationFiles(): array
    {
        if (!is_dir(self::$migrationsDir)) {
            return [];
        }

        $files = glob(self::$migrationsDir . '/*.sql');
        if ($files === false) {
            return [];
        }

        // Ordenar alfabéticamente/cronológicamente por estándar AAAAMMDD_HHMMSS_*.sql
        sort($files, SORT_STRING);
        return $files;
    }

    private static function executeMigration(PDO $pdo, string $filePath, string $filename): void
    {
        // Validar formato del nombre: AAAAMMDD_HHMMSS_NombreMigracion.sql
        if (!preg_match('/^\d{8}_\d{6}_[a-zA-Z0-9_]+\.sql$/i', $filename)) {
            throw new RuntimeException("El archivo '{$filename}' no cumple con el estándar requerido: AAAAMMDD_HHMMSS_NombreMigracion.sql");
        }

        $content = file_get_contents($filePath);
        if ($content === false || trim($content) === '') {
            return;
        }

        // Validar que no intente modificar o alterar tablas que no inicien con ST_ o STB_
        self::validateAllowedTables($content, $filename);

        $checksum = hash('sha256', $content);

        $pdo->beginTransaction();
        try {
            // Dividir por bloques GO si existen (estándar T-SQL)
            $batches = preg_split('/^\s*GO\s*$/mi', $content);
            if ($batches === false) {
                $batches = [$content];
            }

            foreach ($batches as $batch) {
                $cleanSql = trim($batch);
                if ($cleanSql !== '') {
                    $pdo->exec($cleanSql);
                }
            }

            // Registrar en el log de migraciones
            $stmt = $pdo->prepare("INSERT INTO ST_Despostadora_Migrations (migration_name, checksum, applied_at) VALUES (:name, :checksum, GETDATE())");
            $stmt->execute([
                ':name' => $filename,
                ':checksum' => $checksum,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException("Error aplicando migración '{$filename}': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Valida que cualquier creación, alteración o borrado afecte únicamente tablas que inicien con ST_ o STB_
     * y que las tablas creadas sigan el prefijo ST_Despostadora_
     */
    private static function validateAllowedTables(string $sql, string $filename): void
    {
        // 1. Validar CREATE TABLE: deben iniciar obligatoriamente con ST_Despostadora_
        // Reconoce formatos: CREATE TABLE [ST_Despostadora_X], CREATE TABLE dbo.ST_Despostadora_X, etc.
        if (preg_match_all('/\bCREATE\s+TABLE\s+(?:\[?dbo\]?\.)?\[?([a-zA-Z0-9_]+)\]?/i', $sql, $matches)) {
            foreach ($matches[1] as $table) {
                if (!str_starts_with($table, 'ST_Despostadora_')) {
                    throw new RuntimeException("Regla violada en '{$filename}': Toda tabla creada debe tener el prefijo 'ST_Despostadora_'. Tabla encontrada: '{$table}'");
                }
            }
        }

        // 2. Validar ALTER TABLE / DROP TABLE: solo permitidas en tablas que comiencen con ST_ o STB_
        if (preg_match_all('/\b(?:ALTER|DROP)\s+TABLE\s+(?:\[?dbo\]?\.)?\[?([a-zA-Z0-9_]+)\]?/i', $sql, $matches)) {
            foreach ($matches[1] as $table) {
                if (!str_starts_with($table, 'ST_') && !str_starts_with($table, 'STB_')) {
                    throw new RuntimeException("Regla de seguridad violada en '{$filename}': No está permitido modificar o eliminar tablas que no inicien por 'ST_' o 'STB_'. Tabla detectada: '{$table}'");
                }
            }
        }
    }
}
