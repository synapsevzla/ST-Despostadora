<?php
declare(strict_types=1);

namespace Src\Utils;

use Config\Database;
use PDO;
use Throwable;

final class AuditService
{
    /**
     * Registra un evento inmutable en ST_Despostadora_Auditoria.
     *
     * @param string $modulo Módulo que origina la acción (RECEPCION, DESPOSTE, CONFIGURACION, etc.)
     * @param string $documentoId Identificador del documento o clave afectada
     * @param string $accion Acción realizada (CREAR, MODIFICAR, CERRAR, ANULAR, etc.)
     * @param string $codusua Código del usuario autenticado
     * @param array<string, mixed>|null $detalle Datos estructurados previos/nuevos para auditoría
     * @param PDO|null $pdo Conexión PDO opcional si ya se encuentra en una transacción
     */
    public static function log(
        string $modulo,
        string $documentoId,
        string $accion,
        string $codusua,
        ?array $detalle = null,
        ?PDO $pdo = null
    ): void {
        try {
            $conn = $pdo ?? Database::getConnection();
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            $detalleJson = $detalle !== null ? json_encode($detalle, JSON_UNESCAPED_UNICODE) : null;

            $sql = "INSERT INTO ST_Despostadora_Auditoria 
                (Modulo, DocumentoID, Accion, CodUsua, FechaHora, IP, DetalleJSON)
                VALUES 
                (:modulo, :docid, :accion, :codusua, SYSUTCDATETIME(), :ip, :detalle)";

            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':modulo', mb_substr(trim($modulo), 0, 30), PDO::PARAM_STR);
            $stmt->bindValue(':docid', mb_substr(trim($documentoId), 0, 30), PDO::PARAM_STR);
            $stmt->bindValue(':accion', mb_substr(trim($accion), 0, 30), PDO::PARAM_STR);
            $stmt->bindValue(':codusua', mb_substr(trim($codusua), 0, 20), PDO::PARAM_STR);
            $stmt->bindValue(':ip', mb_substr($ip, 0, 45), PDO::PARAM_STR);
            $stmt->bindValue(':detalle', $detalleJson, $detalleJson !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->execute();
        } catch (Throwable $e) {
            // El fallo de auditoría nunca debe romper la operativa principal, pero se loguea
            error_log('[AUDIT SERVICE ERROR] ' . $e->getMessage());
        }
    }
}
