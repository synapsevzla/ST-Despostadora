<?php
declare(strict_types=1);

namespace Src\Deboning;

use Config\Database;
use PDO;
use RuntimeException;

final class DeboningSyncService
{
    /**
     * Resuelve el estado de sincronización y delta de una orden de desposte.
     * Si la versión del cliente coincide y la orden sigue EN_PROCESO, responde
     * ultra-rápido (<15ms) sin calcular balances.
     *
     * @param string $nroOrden
     * @param int $clientVersion
     * @return array<string, mixed>
     */
    public static function getSyncStatus(string $nroOrden, int $clientVersion): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT NroOrdenDesposte, Estado,
                                      CAST(ISNULL(Version, 1) AS BIGINT) AS Version,
                                      UltimoUsuarioModif, UltimaModif,
                                      CodUsuaCierre, FechaHoraCierre
                               FROM ST_Despostadora_Despostes
                               WHERE NroOrdenDesposte = :nro");
        $stmt->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException("La orden de desposte '{$nroOrden}' no existe.");
        }

        $currentVersion = (int)$row['Version'];
        $estado = (string)$row['Estado'];

        // Si el cliente está al día y la orden sigue activa, responder sin procesar balances
        if ($clientVersion === $currentVersion && $estado === 'EN_PROCESO') {
            return [
                'success' => true,
                'has_changes' => false,
                'version' => $currentVersion,
                'estado' => $estado
            ];
        }

        // Si la versión difiere o la orden ya fue cerrada, compilar balance y último evento
        $balance = DeboningService::getYieldAndCosting($nroOrden);

        // Obtener el último evento auditado para notificar al operario
        $stmtLog = $pdo->prepare("SELECT TOP 1 log.TipoAccion, log.NroLote, log.CodProdCorte,
                                         CAST(log.PesoAnterior AS FLOAT) AS PesoAnterior,
                                         CAST(log.PesoNuevo AS FLOAT) AS PesoNuevo,
                                         CAST(log.DiferenciaPeso AS FLOAT) AS DiferenciaPeso,
                                         log.Motivo, log.CodUsua, log.FechaHora,
                                         LTRIM(RTRIM(CONCAT(p.Descrip, ' ', p.Descrip2, ' ', p.Descrip3))) AS NombreCorte,
                                         ISNULL(u.Descrip, log.CodUsua) AS NombreUsuario
                                  FROM ST_Despostadora_DespostesCortesLog log
                                  LEFT JOIN SAPROD p ON p.CodProd = log.CodProdCorte
                                  LEFT JOIN ssusrs u ON u.CodUsua = log.CodUsua
                                  WHERE log.NroOrdenDesposte = :nro
                                  ORDER BY log.FechaHora DESC, log.ID DESC");
        $stmtLog->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
        $stmtLog->execute();
        $lastLog = $stmtLog->fetch(PDO::FETCH_ASSOC) ?: null;

        return [
            'success' => true,
            'has_changes' => true,
            'version' => $currentVersion,
            'estado' => $estado,
            'balance' => $balance,
            'last_log' => $lastLog,
            'closed_by' => $row['CodUsuaCierre'] ?? null,
            'closed_at' => $row['FechaHoraCierre'] ?? null
        ];
    }
}
