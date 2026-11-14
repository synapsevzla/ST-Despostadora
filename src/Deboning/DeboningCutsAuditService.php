<?php
declare(strict_types=1);

namespace Src\Deboning;

use Config\Database;
use Config\Security;
use PDO;
use RuntimeException;
use Throwable;

final class DeboningCutsAuditService
{
    /**
     * Registra un evento inmutable en el log de auditoría y trazabilidad de pesajes.
     */
    public static function log(
        PDO $pdo,
        string $nroOrden,
        int $idDetalle,
        string $nroLote,
        string $codProdCorte,
        string $tipoAccion,
        ?float $pesoAnterior,
        ?float $pesoNuevo,
        ?string $motivo,
        string $codusua,
        ?string $ip = null
    ): void {
        $diferencia = null;
        if ($pesoAnterior !== null && $pesoNuevo !== null) {
            $diferencia = round($pesoNuevo - $pesoAnterior, 4);
        }

        $ipAddress = $ip ?? (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        $sql = "INSERT INTO ST_Despostadora_DespostesCortesLog
            (NroOrdenDesposte, IDDetalle, NroLote, CodProdCorte, TipoAccion,
             PesoAnterior, PesoNuevo, DiferenciaPeso, Motivo, CodUsua, IP, FechaHora)
            VALUES
            (:nro, :iddet, :lote, :corte, :accion,
             :pesoant, :pesonew, :dif, :motivo, :codusua, :ip, SYSUTCDATETIME())";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
        $stmt->bindValue(':iddet', $idDetalle, PDO::PARAM_INT);
        $stmt->bindValue(':lote', $nroLote, PDO::PARAM_STR);
        $stmt->bindValue(':corte', $codProdCorte, PDO::PARAM_STR);
        $stmt->bindValue(':accion', $tipoAccion, PDO::PARAM_STR);

        if ($pesoAnterior !== null) {
            $stmt->bindValue(':pesoant', $pesoAnterior, PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':pesoant', null, PDO::PARAM_NULL);
        }

        if ($pesoNuevo !== null) {
            $stmt->bindValue(':pesonew', $pesoNuevo, PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':pesonew', null, PDO::PARAM_NULL);
        }

        if ($diferencia !== null) {
            $stmt->bindValue(':dif', $diferencia, PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':dif', null, PDO::PARAM_NULL);
        }

        if ($motivo !== null && trim($motivo) !== '') {
            $stmt->bindValue(':motivo', trim($motivo), PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':motivo', null, PDO::PARAM_NULL);
        }

        $stmt->bindValue(':codusua', $codusua, PDO::PARAM_STR);
        $stmt->bindValue(':ip', $ipAddress, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Modifica el peso de un corte existente registrando la trazabilidad completa.
     * @param array<string, mixed> $data
     * @param string $codusua
     * @return array<string, mixed>
     */
    public static function updateCutWeight(array $data, string $codusua): array
    {
        $pdo = Database::getConnection();

        $nroOrden = trim((string)($data['nro_orden'] ?? ''));
        $idDetalle = (int)($data['id'] ?? 0);
        $nuevoPeso = round((float)($data['nuevo_peso'] ?? 0.0), 4);
        $motivo = trim((string)($data['motivo'] ?? ''));

        if ($nroOrden === '' || $idDetalle <= 0) {
            throw new RuntimeException("Parámetros de orden o pesaje inválidos.");
        }
        if ($nuevoPeso <= 0) {
            throw new RuntimeException("El nuevo peso debe ser mayor a cero.");
        }
        if ($motivo === '') {
            throw new RuntimeException("Debe especificar el motivo del cambio de peso para auditoría.");
        }

        $pdo->beginTransaction();
        try {
            // Verificar estado de la orden con bloqueo
            $stmtOrd = $pdo->prepare("SELECT Estado, PesoCanalEntrada FROM ST_Despostadora_Despostes WITH (UPDLOCK, HOLDLOCK) WHERE NroOrdenDesposte = :nro");
            $stmtOrd->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
            $stmtOrd->execute();
            $ord = $stmtOrd->fetch(PDO::FETCH_ASSOC);

            if (!$ord || $ord['Estado'] !== 'EN_PROCESO') {
                throw new RuntimeException("Solo se pueden modificar cortes en órdenes activas EN_PROCESO.");
            }

            // Buscar detalle actual
            $stmtDet = $pdo->prepare("SELECT ID, NroItem, NroLote, CodProdCorte, CAST(PesoNeto AS FLOAT) AS PesoNeto
                                      FROM ST_Despostadora_DespostesDetalle WITH (UPDLOCK, HOLDLOCK)
                                      WHERE ID = :id AND NroOrdenDesposte = :nro");
            $stmtDet->bindValue(':id', $idDetalle, PDO::PARAM_INT);
            $stmtDet->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
            $stmtDet->execute();
            $det = $stmtDet->fetch(PDO::FETCH_ASSOC);

            if (!$det) {
                throw new RuntimeException("El pesaje indicado no existe o ya fue eliminado por otro usuario.");
            }

            $pesoAnterior = (float)$det['PesoNeto'];
            $pesoCanalEntrada = (float)$ord['PesoCanalEntrada'];
            $nuevoRendimiento = $pesoCanalEntrada > 0 ? round(($nuevoPeso / $pesoCanalEntrada) * 100, 2) : 0.0;

            // Actualizar fila del corte
            $stmtUpd = $pdo->prepare("UPDATE ST_Despostadora_DespostesDetalle
                SET PesoNeto = :peso, RendimientoCortePct = :rend,
                    CodUsuaModif = :codusua, FechaHoraModif = SYSUTCDATETIME()
                WHERE ID = :id");
            $stmtUpd->bindValue(':peso', $nuevoPeso, PDO::PARAM_STR);
            $stmtUpd->bindValue(':rend', $nuevoRendimiento, PDO::PARAM_STR);
            $stmtUpd->bindValue(':codusua', $codusua, PDO::PARAM_STR);
            $stmtUpd->bindValue(':id', $idDetalle, PDO::PARAM_INT);
            $stmtUpd->execute();

            // Registrar log inmutable
            self::log(
                $pdo,
                $nroOrden,
                $idDetalle,
                (string)$det['NroLote'],
                (string)$det['CodProdCorte'],
                'MODIFICACION_PESO',
                $pesoAnterior,
                $nuevoPeso,
                $motivo,
                $codusua
            );

            // Recalcular orden y avanzar versión
            DeboningService::recalculateOrderWithVersion($pdo, $nroOrden, $codusua);

            $pdo->commit();

            return [
                'id' => $idDetalle,
                'nro_lote' => (string)$det['NroLote'],
                'peso_anterior' => $pesoAnterior,
                'peso_nuevo' => $nuevoPeso,
                'diferencia' => round($nuevoPeso - $pesoAnterior, 4),
                'motivo' => $motivo
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException("Error modificando peso de corte: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Obtiene el listado cronológico de trazabilidad y auditoría de la orden.
     * @return list<array<string, mixed>>
     */
    public static function getAuditLog(string $nroOrden): array
    {
        $pdo = Database::getConnection();

        $sql = "SELECT log.ID, log.NroOrdenDesposte, log.IDDetalle, log.NroLote, log.CodProdCorte,
                       log.TipoAccion,
                       CAST(log.PesoAnterior AS FLOAT) AS PesoAnterior,
                       CAST(log.PesoNuevo AS FLOAT) AS PesoNuevo,
                       CAST(log.DiferenciaPeso AS FLOAT) AS DiferenciaPeso,
                       log.Motivo, log.CodUsua, log.IP, log.FechaHora,
                       LTRIM(RTRIM(CONCAT(p.Descrip, ' ', p.Descrip2, ' ', p.Descrip3))) AS NombreCorte,
                       ISNULL(u.Descrip, log.CodUsua) AS NombreUsuario
                FROM ST_Despostadora_DespostesCortesLog log
                LEFT JOIN SAPROD p ON p.CodProd = log.CodProdCorte
                LEFT JOIN ssusrs u ON u.CodUsua = log.CodUsua
                WHERE log.NroOrdenDesposte = :nro
                ORDER BY log.FechaHora DESC, log.ID DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
