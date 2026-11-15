<?php
declare(strict_types=1);

namespace Src\Deboning;

use Config\Database;
use PDO;
use RuntimeException;
use Src\Utils\AuditService;
use Throwable;

final class DeboningAnnulmentService
{
    /**
     * Anula una orden de desposte activa y restituye la existencia de la canal
     * a su recepción de origen según el tipo de operación / método de la especie.
     *
     * @param string $nroOrden Correlativo de la orden (DESP-...)
     * @param string $motivo Razón obligatoria de la anulación
     * @param string $codusua Usuario que realiza la anulación
     * @return array{
     *     nro_orden: string,
     *     nro_recepcion: string,
     *     codprod_canal: string,
     *     peso_restituido: float,
     *     metodo_entrada: string,
     *     cortes_anulados: int,
     *     motivo: string
     * }
     * @throws RuntimeException
     */
    public static function annulOrder(string $nroOrden, string $motivo, string $codusua): array
    {
        $pdo = Database::getConnection();
        $nroOrden = trim($nroOrden);
        $motivo = trim($motivo);
        $codusua = trim($codusua);

        if ($nroOrden === '') {
            throw new RuntimeException("Debe especificar el número de orden de desposte a anular.");
        }
        if (mb_strlen($motivo) < 5) {
            throw new RuntimeException("Debe indicar un motivo de anulación válido (mínimo 5 caracteres).");
        }
        if ($codusua === '') {
            throw new RuntimeException("Usuario no autenticado para realizar la anulación.");
        }

        $pdo->beginTransaction();
        try {
            // 1. Bloqueo pesimista y verificación estricta de estado EN_PROCESO
            $stmtOrd = $pdo->prepare("
                SELECT d.NroOrdenDesposte, d.NroRecepcion, d.CodProdCanal, 
                       CAST(d.PesoCanalEntrada AS FLOAT) AS PesoCanalEntrada,
                       d.Estado, 
                       CAST(ISNULL(d.DocDescargoEnviado, 0) AS INT) AS DocDescargoEnviado,
                       CAST(ISNULL(d.DocCargoEnviado, 0) AS INT) AS DocCargoEnviado,
                       r.CodUbic, r.MetodoEntrada,
                       CAST(r.PesoTotalNeto AS FLOAT) AS PesoTotalNeto,
                       CAST(r.PesoDisponible AS FLOAT) AS PesoDisponible
                FROM ST_Despostadora_Despostes d WITH (UPDLOCK, HOLDLOCK)
                INNER JOIN ST_Despostadora_Recepciones r ON r.NroRecepcion = d.NroRecepcion
                WHERE d.NroOrdenDesposte = :nro
            ");
            $stmtOrd->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
            $stmtOrd->execute();
            $ord = $stmtOrd->fetch(PDO::FETCH_ASSOC);

            if (!$ord) {
                throw new RuntimeException("La orden de desposte '{$nroOrden}' no existe.");
            }
            if ($ord['Estado'] !== 'EN_PROCESO') {
                throw new RuntimeException("Solo se pueden anular órdenes activas EN_PROCESO. Esta orden ya está en estado '{$ord['Estado']}'.");
            }
            if (!empty($ord['DocDescargoEnviado']) || !empty($ord['DocCargoEnviado'])) {
                throw new RuntimeException("La orden ya tiene documentos de desposte generados en Saint ERP y no puede anularse por este medio.");
            }

            $pesoCanal = (float)$ord['PesoCanalEntrada'];
            $nroRecepcion = (string)$ord['NroRecepcion'];
            $codUbic = (string)$ord['CodUbic'];
            $codProdCanal = (string)$ord['CodProdCanal'];
            $metodoEntrada = (string)$ord['MetodoEntrada'];

            // 2. Liberar stock comprometido en existencias internas
            $stmtStock = $pdo->prepare("
                UPDATE ST_Despostadora_Existencias
                SET StockComprometido = CASE 
                        WHEN StockComprometido >= :peso THEN StockComprometido - :peso 
                        ELSE 0 
                    END,
                    UltimaActualizacion = SYSUTCDATETIME()
                WHERE CodUbic = :ubic AND CodProd = :prod AND NroLote = :lote
            ");
            $stmtStock->bindValue(':peso', $pesoCanal, PDO::PARAM_STR);
            $stmtStock->bindValue(':ubic', $codUbic, PDO::PARAM_STR);
            $stmtStock->bindValue(':prod', $codProdCanal, PDO::PARAM_STR);
            $stmtStock->bindValue(':lote', $nroRecepcion, PDO::PARAM_STR);
            $stmtStock->execute();

            // 3. Restituir peso disponible en la recepción de origen
            $pesoTotalNeto = (float)$ord['PesoTotalNeto'];
            $pesoDispActual = (float)$ord['PesoDisponible'];
            $nuevoDisp = round($pesoDispActual + $pesoCanal, 4);
            if ($nuevoDisp > $pesoTotalNeto) {
                $nuevoDisp = $pesoTotalNeto;
            }
            $nuevoEstadoRec = (abs($nuevoDisp - $pesoTotalNeto) < 0.0001) ? 'DISPONIBLE' : 'EN_PROCESO';

            $stmtRec = $pdo->prepare("
                UPDATE ST_Despostadora_Recepciones
                SET PesoDisponible = :disp,
                    Estado = :est
                WHERE NroRecepcion = :nrorec
            ");
            $stmtRec->bindValue(':disp', $nuevoDisp, PDO::PARAM_STR);
            $stmtRec->bindValue(':est', $nuevoEstadoRec, PDO::PARAM_STR);
            $stmtRec->bindValue(':nrorec', $nroRecepcion, PDO::PARAM_STR);
            $stmtRec->execute();

            // 4. Si la especie ingresó bajo método PESO_INDIVIDUAL, liberar la pieza en RecepcionesDetalle
            if ($metodoEntrada === 'PESO_INDIVIDUAL') {
                $stmtDetRec = $pdo->prepare("
                    UPDATE ST_Despostadora_RecepcionesDetalle
                    SET EstadoDesposte = 'DISPONIBLE',
                        NroOrdenDesposte = NULL
                    WHERE NroOrdenDesposte = :nroord
                ");
                $stmtDetRec->bindValue(':nroord', $nroOrden, PDO::PARAM_STR);
                $stmtDetRec->execute();
            }

            // 5. Manejar cortes pesados previos en la orden si existiesen
            $stmtCortes = $pdo->prepare("
                SELECT ID, NroLote, CodProdCorte, CAST(PesoNeto AS FLOAT) AS PesoNeto
                FROM ST_Despostadora_DespostesDetalle
                WHERE NroOrdenDesposte = :nroord
            ");
            $stmtCortes->bindValue(':nroord', $nroOrden, PDO::PARAM_STR);
            $stmtCortes->execute();
            $cortes = $stmtCortes->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($cortes)) {
                $motivoAuditoria = 'Orden anulada: ' . mb_substr($motivo, 0, 200);
                foreach ($cortes as $c) {
                    DeboningCutsAuditService::log(
                        $pdo,
                        $nroOrden,
                        (int)$c['ID'],
                        (string)$c['NroLote'],
                        (string)$c['CodProdCorte'],
                        'ANULACION',
                        (float)$c['PesoNeto'],
                        0.0,
                        $motivoAuditoria,
                        $codusua
                    );
                }
                $stmtDelCortes = $pdo->prepare("DELETE FROM ST_Despostadora_DespostesDetalle WHERE NroOrdenDesposte = :nroord");
                $stmtDelCortes->bindValue(':nroord', $nroOrden, PDO::PARAM_STR);
                $stmtDelCortes->execute();
            }

            // 6. Asentar la anulación formalmente en el encabezado de la orden
            $obsAnulacion = "ANULADO: " . $motivo;
            $stmtUpdOrd = $pdo->prepare("
                UPDATE ST_Despostadora_Despostes
                SET Estado = 'ANULADO',
                    FechaHoraCierre = SYSUTCDATETIME(),
                    FechaHoraAnulacion = SYSUTCDATETIME(),
                    CodUsuaCierre = :codusua,
                    CodUsuaAnulacion = :codusua,
                    MotivoAnulacion = :motivo,
                    Observaciones = CASE 
                        WHEN Observaciones IS NULL OR Observaciones = '' THEN :obs 
                        ELSE CONCAT(Observaciones, ' | ', :obs) 
                    END,
                    PesoTotalCortes = 0,
                    PesoMerma = 0,
                    CostoPerdidaMerma = 0,
                    RendimientoPct = 0,
                    MermaPct = 0,
                    EstadoCruceERP = 'ANULADO',
                    Version = ISNULL(Version, 1) + 1,
                    UltimaModif = SYSUTCDATETIME(),
                    UltimoUsuarioModif = :codusua
                WHERE NroOrdenDesposte = :nroord AND Estado = 'EN_PROCESO'
            ");
            $stmtUpdOrd->bindValue(':codusua', $codusua, PDO::PARAM_STR);
            $stmtUpdOrd->bindValue(':motivo', $motivo, PDO::PARAM_STR);
            $stmtUpdOrd->bindValue(':obs', mb_substr($obsAnulacion, 0, 255), PDO::PARAM_STR);
            $stmtUpdOrd->bindValue(':nroord', $nroOrden, PDO::PARAM_STR);
            $stmtUpdOrd->execute();

            if ($stmtUpdOrd->rowCount() === 0) {
                throw new RuntimeException("La orden de desposte ya no se encuentra en proceso o fue modificada por otro usuario.");
            }

            // 7. Auditoría inmutable general del sistema
            AuditService::log('DESPOSTE', $nroOrden, 'ANULAR_ORDEN', $codusua, [
                'NroOrdenDesposte' => $nroOrden,
                'NroRecepcion' => $nroRecepcion,
                'CodProdCanal' => $codProdCanal,
                'MetodoEntrada' => $metodoEntrada,
                'PesoCanalDevuelto' => $pesoCanal,
                'Motivo' => $motivo,
                'CortesDescartados' => count($cortes)
            ], $pdo);

            $pdo->commit();

            return [
                'nro_orden' => $nroOrden,
                'nro_recepcion' => $nroRecepcion,
                'codprod_canal' => $codProdCanal,
                'peso_restituido' => $pesoCanal,
                'metodo_entrada' => $metodoEntrada,
                'cortes_anulados' => count($cortes),
                'motivo' => $motivo
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException("Error al anular la orden de desposte: " . $e->getMessage(), 0, $e);
        }
    }
}
