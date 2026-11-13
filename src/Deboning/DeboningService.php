<?php
declare(strict_types=1);

namespace Src\Deboning;

use Config\Database;
use Src\Utils\AuditService;
use PDO;
use RuntimeException;
use Throwable;

final class DeboningService
{
    /**
     * Abre una nueva orden de desposte en una mesa o línea de trabajo.
     * @param array<string, mixed> $data
     * @param string $codusua
     * @return string NroOrdenDesposte generado
     */
    public static function openOrder(array $data, string $codusua): string
    {
        $pdo = Database::getConnection();

        $nroRecepcion = trim((string)($data['nro_recepcion'] ?? ''));
        $lineaMesa = trim((string)($data['linea_mesa'] ?? ''));
        $nroEspecieDetalle = isset($data['nro_especie']) ? (int)$data['nro_especie'] : null;
        $pesoEntradaManual = isset($data['peso_entrada']) ? (float)$data['peso_entrada'] : 0.0;
        $observaciones = trim((string)($data['observaciones'] ?? ''));

        if ($nroRecepcion === '') {
            throw new RuntimeException("Debe seleccionar una recepción de carne en canal de origen.");
        }

        $stmtRec = $pdo->prepare("SELECT NroRecepcion, CodUbic, CodProdCanal, CostActCanal, MetodoEntrada,
                                         CantidadEspecies, PesoTotalNeto, PesoDisponible, Estado
                                  FROM ST_Despostadora_Recepciones
                                  WHERE NroRecepcion = :nro");
        $stmtRec->bindValue(':nro', $nroRecepcion, PDO::PARAM_STR);
        $stmtRec->execute();
        $rec = $stmtRec->fetch(PDO::FETCH_ASSOC);

        if (!$rec) {
            throw new RuntimeException("La recepción '{$nroRecepcion}' no existe.");
        }
        if ($rec['Estado'] === 'DESPOSTADO' || (float)$rec['PesoDisponible'] <= 0) {
            throw new RuntimeException("La recepción '{$nroRecepcion}' ya no tiene peso disponible.");
        }

        $codProdCanal = (string)$rec['CodProdCanal'];
        $costActCanal = (float)$rec['CostActCanal'];
        $codUbic = (string)$rec['CodUbic'];
        $metodoEntrada = (string)$rec['MetodoEntrada'];

        $pesoCanalEntrada = 0.0;
        $cantidadProcesada = 1;

        if ($metodoEntrada === 'PESO_INDIVIDUAL' && $nroEspecieDetalle !== null && $nroEspecieDetalle > 0) {
            $stmtDet = $pdo->prepare("SELECT NroRecepcion, NroEspecie, PesoNeto, EstadoDesposte
                                      FROM ST_Despostadora_RecepcionesDetalle
                                      WHERE NroRecepcion = :nro AND NroEspecie = :esp");
            $stmtDet->bindValue(':nro', $nroRecepcion, PDO::PARAM_STR);
            $stmtDet->bindValue(':esp', $nroEspecieDetalle, PDO::PARAM_INT);
            $stmtDet->execute();
            $det = $stmtDet->fetch(PDO::FETCH_ASSOC);

            if (!$det || $det['EstadoDesposte'] !== 'DISPONIBLE') {
                throw new RuntimeException("La pieza #{$nroEspecieDetalle} no está disponible para desposte.");
            }
            $pesoCanalEntrada = (float)$det['PesoNeto'];
        } else {
            if ($pesoEntradaManual > 0) {
                if ($pesoEntradaManual > (float)$rec['PesoDisponible']) {
                    throw new RuntimeException("El peso solicitado ({$pesoEntradaManual} kg) supera el disponible ({$rec['PesoDisponible']} kg).");
                }
                $pesoCanalEntrada = $pesoEntradaManual;
            } else {
                $pesoCanalEntrada = (float)$rec['PesoDisponible'];
            }
            $cantidadProcesada = (int)$rec['CantidadEspecies'] > 0 ? (int)$rec['CantidadEspecies'] : 1;
        }

        if ($pesoCanalEntrada <= 0) {
            throw new RuntimeException("El peso de la canal para desposte debe ser mayor a cero.");
        }

        $costoTotalCanal = round($pesoCanalEntrada * $costActCanal, 4);

        $pdo->beginTransaction();
        try {
            $nroOrdenDesposte = CorrelativeService::getNext($pdo, 'DESPOSTE');

            $sqlInsOrd = "INSERT INTO ST_Despostadora_Despostes
                (NroOrdenDesposte, NroRecepcion, LineaMesa, FechaHoraInicio, CodUsua, CodProdCanal,
                 CostActCanal, CantidadProcesada, PesoCanalEntrada, CostoTotalCanal, PesoTotalCortes,
                 PesoMerma, CostoPerdidaMerma, RendimientoPct, MermaPct, Estado, Observaciones)
                VALUES
                (:nroord, :nrorec, :linea, SYSUTCDATETIME(), :codusua, :codprod,
                 :costact, :cant, :pesocanal, :costotot, 0,
                 0, 0, 0, 0, 'EN_PROCESO', :obs)";

            $stmtIns = $pdo->prepare($sqlInsOrd);
            $stmtIns->bindValue(':nroord', $nroOrdenDesposte, PDO::PARAM_STR);
            $stmtIns->bindValue(':nrorec', $nroRecepcion, PDO::PARAM_STR);
            $stmtIns->bindValue(':linea', $lineaMesa, PDO::PARAM_STR);
            $stmtIns->bindValue(':codusua', $codusua, PDO::PARAM_STR);
            $stmtIns->bindValue(':codprod', $codProdCanal, PDO::PARAM_STR);
            $stmtIns->bindValue(':costact', $costActCanal, PDO::PARAM_STR);
            $stmtIns->bindValue(':cant', $cantidadProcesada, PDO::PARAM_INT);
            $stmtIns->bindValue(':pesocanal', $pesoCanalEntrada, PDO::PARAM_STR);
            $stmtIns->bindValue(':costotot', $costoTotalCanal, PDO::PARAM_STR);
            $stmtIns->bindValue(':obs', $observaciones !== '' ? $observaciones : null, $observaciones !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmtIns->execute();

            $nuevoDisp = (float)$rec['PesoDisponible'] - $pesoCanalEntrada;
            if ($nuevoDisp < 0.0001) {
                $nuevoDisp = 0.0;
            }
            $nuevoEstadoRec = ($nuevoDisp <= 0) ? 'DESPOSTADO' : 'EN_PROCESO';

            $stmtUpdRec = $pdo->prepare("UPDATE ST_Despostadora_Recepciones SET PesoDisponible = :disp, Estado = :est WHERE NroRecepcion = :nro");
            $stmtUpdRec->bindValue(':disp', $nuevoDisp, PDO::PARAM_STR);
            $stmtUpdRec->bindValue(':est', $nuevoEstadoRec, PDO::PARAM_STR);
            $stmtUpdRec->bindValue(':nro', $nroRecepcion, PDO::PARAM_STR);
            $stmtUpdRec->execute();

            if ($metodoEntrada === 'PESO_INDIVIDUAL' && $nroEspecieDetalle !== null && $nroEspecieDetalle > 0) {
                $stmtUpdDet = $pdo->prepare("UPDATE ST_Despostadora_RecepcionesDetalle SET EstadoDesposte = 'EN_DESPOSTE', NroOrdenDesposte = :nroord WHERE NroRecepcion = :nro AND NroEspecie = :esp");
                $stmtUpdDet->bindValue(':nroord', $nroOrdenDesposte, PDO::PARAM_STR);
                $stmtUpdDet->bindValue(':nro', $nroRecepcion, PDO::PARAM_STR);
                $stmtUpdDet->bindValue(':esp', $nroEspecieDetalle, PDO::PARAM_INT);
                $stmtUpdDet->execute();
            }

            $stmtComp = $pdo->prepare("UPDATE ST_Despostadora_Existencias
                                       SET StockComprometido = StockComprometido + :peso, UltimaActualizacion = SYSUTCDATETIME()
                                       WHERE CodUbic = :ubic AND CodProd = :prod AND NroLote = :lote");
            $stmtComp->bindValue(':peso', $pesoCanalEntrada, PDO::PARAM_STR);
            $stmtComp->bindValue(':ubic', $codUbic, PDO::PARAM_STR);
            $stmtComp->bindValue(':prod', $codProdCanal, PDO::PARAM_STR);
            $stmtComp->bindValue(':lote', $nroRecepcion, PDO::PARAM_STR);
            $stmtComp->execute();

            AuditService::log('DESPOSTE', $nroOrdenDesposte, 'ABRIR_ORDEN', $codusua, [
                'NroOrdenDesposte' => $nroOrdenDesposte,
                'NroRecepcion' => $nroRecepcion,
                'LineaMesa' => $lineaMesa,
                'PesoCanalEntrada' => $pesoCanalEntrada
            ], $pdo);

            $pdo->commit();
            return $nroOrdenDesposte;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException("Error abriendo orden de desposte: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Registra el pesaje de un corte cárnico dentro de una orden de desposte activa.
     * Genera automáticamente el NroLote y calcula fechas.
     * @param array<string, mixed> $data
     * @param string $codusua
     * @return array<string, mixed>
     */
    public static function weighCut(array $data, string $codusua): array
    {
        $pdo = Database::getConnection();

        $nroOrden = trim((string)($data['nro_orden'] ?? ''));
        $codProdCorte = trim((string)($data['codprod_corte'] ?? ''));
        $pesoNeto = (float)($data['peso_neto'] ?? 0.0);

        if ($nroOrden === '' || $codProdCorte === '') {
            throw new RuntimeException("Especifique la orden y el código del corte.");
        }
        if ($pesoNeto <= 0) {
            throw new RuntimeException("El peso neto debe ser mayor a cero.");
        }

        $stmtOrd = $pdo->prepare("SELECT NroOrdenDesposte, CodProdCanal, CostActCanal, PesoCanalEntrada, Estado
                                  FROM ST_Despostadora_Despostes WHERE NroOrdenDesposte = :nro");
        $stmtOrd->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
        $stmtOrd->execute();
        $ord = $stmtOrd->fetch(PDO::FETCH_ASSOC);

        if (!$ord || $ord['Estado'] !== 'EN_PROCESO') {
            throw new RuntimeException("La orden '{$nroOrden}' no está activa.");
        }

        $codProdCanal = (string)$ord['CodProdCanal'];
        $pesoCanalEntrada = (float)$ord['PesoCanalEntrada'];

        $stmtMatriz = $pdo->prepare("SELECT c.CodProdCorte, c.DiasVencimiento,
                                            CAST(ISNULL(p.CostAct, 0) AS FLOAT) AS CostActCorte,
                                            LTRIM(RTRIM(CONCAT(p.Descrip, ' ', p.Descrip2, ' ', p.Descrip3))) AS NombreCorte
                                     FROM ST_Despostadora_EspeciesCortes c
                                     INNER JOIN SAPROD p ON p.CodProd = c.CodProdCorte
                                     WHERE c.CodProdCanal = :canal AND c.CodProdCorte = :corte AND c.Activo = 1");
        $stmtMatriz->bindValue(':canal', $codProdCanal, PDO::PARAM_STR);
        $stmtMatriz->bindValue(':corte', $codProdCorte, PDO::PARAM_STR);
        $stmtMatriz->execute();
        $matriz = $stmtMatriz->fetch(PDO::FETCH_ASSOC);

        if (!$matriz) {
            throw new RuntimeException("El corte '{$codProdCorte}' no está asignado a la especie.");
        }

        $costActBase = (float)$matriz['CostActCorte'];
        $diasVencimiento = (int)$matriz['DiasVencimiento'] > 0 ? (int)$matriz['DiasVencimiento'] : 30;

        $stmtItem = $pdo->prepare("SELECT ISNULL(MAX(NroItem), 0) + 1 AS NextItem FROM ST_Despostadora_DespostesDetalle WHERE NroOrdenDesposte = :nro");
        $stmtItem->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
        $stmtItem->execute();
        $nextItem = (int)($stmtItem->fetch(PDO::FETCH_ASSOC)['NextItem'] ?? 1);

        $nroLote = CorrelativeService::generateBatchNumber($pdo, $nroOrden, $nextItem);
        $fechaElab = date('Y-m-d');
        $fechaVenc = date('Y-m-d', strtotime("+{$diasVencimiento} days"));
        $rendimientoCortePct = $pesoCanalEntrada > 0 ? round(($pesoNeto / $pesoCanalEntrada) * 100, 2) : 0.0;
        $margenDefault = (float)ConfigService::getConfig('MARGEN_UTILIDAD_DEFAULT', '30.00');

        $pdo->beginTransaction();
        try {
            $sqlInsDet = "INSERT INTO ST_Despostadora_DespostesDetalle
                (NroOrdenDesposte, NroItem, NroLote, CodProdCorte, PesoNeto, RendimientoCortePct,
                 CostActBase, CostoMermaAbsorbidoKg, CostoRealUnitario, CostoRealTotal, MargenUtilidadPct,
                 PrecioVentaSugerido, FechaElaboracion, FechaVencimiento, FechaHoraPesaje)
                VALUES
                (:nroord, :item, :lote, :corte, :peso, :rend,
                 :costbase, 0, :costunit, :costtot, :margen,
                 :preciosug, :elab, :venc, SYSUTCDATETIME())";

            $costoRealTotalPrelim = round($pesoNeto * $costActBase, 4);
            $precioSugPrelim = ($margenDefault < 100 && $costActBase > 0)
                ? round($costActBase / (1 - ($margenDefault / 100)), 4)
                : $costActBase;

            $stmtInsDet = $pdo->prepare($sqlInsDet);
            $stmtInsDet->bindValue(':nroord', $nroOrden, PDO::PARAM_STR);
            $stmtInsDet->bindValue(':item', $nextItem, PDO::PARAM_INT);
            $stmtInsDet->bindValue(':lote', $nroLote, PDO::PARAM_STR);
            $stmtInsDet->bindValue(':corte', $codProdCorte, PDO::PARAM_STR);
            $stmtInsDet->bindValue(':peso', $pesoNeto, PDO::PARAM_STR);
            $stmtInsDet->bindValue(':rend', $rendimientoCortePct, PDO::PARAM_STR);
            $stmtInsDet->bindValue(':costbase', $costActBase, PDO::PARAM_STR);
            $stmtInsDet->bindValue(':costunit', $costActBase, PDO::PARAM_STR);
            $stmtInsDet->bindValue(':costtot', $costoRealTotalPrelim, PDO::PARAM_STR);
            $stmtInsDet->bindValue(':margen', $margenDefault, PDO::PARAM_STR);
            $stmtInsDet->bindValue(':preciosug', $precioSugPrelim, PDO::PARAM_STR);
            $stmtInsDet->bindValue(':elab', $fechaElab, PDO::PARAM_STR);
            $stmtInsDet->bindValue(':venc', $fechaVenc, PDO::PARAM_STR);
            $stmtInsDet->execute();

            self::recalculateOrderHeader($pdo, $nroOrden);
            $pdo->commit();

            return [
                'nro_lote' => $nroLote,
                'nro_item' => $nextItem,
                'codprod_corte' => $codProdCorte,
                'nombre_corte' => $matriz['NombreCorte'],
                'peso_neto' => $pesoNeto,
                'rendimiento_pct' => $rendimientoCortePct
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException("Error registrando pesaje: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Elimina un pesaje de corte antes del cierre.
     */
    public static function deleteCut(string $nroOrden, int $itemId, string $codusua): void
    {
        $pdo = Database::getConnection();

        $stmtOrd = $pdo->prepare("SELECT Estado FROM ST_Despostadora_Despostes WHERE NroOrdenDesposte = :nro");
        $stmtOrd->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
        $stmtOrd->execute();
        $ord = $stmtOrd->fetch(PDO::FETCH_ASSOC);

        if (!$ord || $ord['Estado'] !== 'EN_PROCESO') {
            throw new RuntimeException("Solo se pueden anular pesajes en órdenes EN_PROCESO.");
        }

        $pdo->beginTransaction();
        try {
            $stmtDel = $pdo->prepare("DELETE FROM ST_Despostadora_DespostesDetalle WHERE NroOrdenDesposte = :nro AND ID = :id");
            $stmtDel->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
            $stmtDel->bindValue(':id', $itemId, PDO::PARAM_INT);
            $stmtDel->execute();

            self::recalculateOrderHeader($pdo, $nroOrden);
            AuditService::log('DESPOSTE', $nroOrden, 'ELIMINAR_CORTE', $codusua, ['IDDetalle' => $itemId], $pdo);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException("Error eliminando pesaje: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Obtiene el balance y tabla de costeo detallada para la orden.
     * @param string $nroOrden
     * @return array<string, mixed>
     */
    public static function getYieldAndCosting(string $nroOrden): array
    {
        $pdo = Database::getConnection();

        $stmtOrd = $pdo->prepare("SELECT d.NroOrdenDesposte, d.NroRecepcion, d.LineaMesa, d.FechaHoraInicio,
                                         d.CodProdCanal, d.CostActCanal, d.PesoCanalEntrada, d.CostoTotalCanal,
                                         d.Estado, r.CodUbic,
                                         LTRIM(RTRIM(CONCAT(p.Descrip, ' ', p.Descrip2, ' ', p.Descrip3))) AS NombreCanal
                                  FROM ST_Despostadora_Despostes d
                                  INNER JOIN ST_Despostadora_Recepciones r ON r.NroRecepcion = d.NroRecepcion
                                  INNER JOIN SAPROD p ON p.CodProd = d.CodProdCanal
                                  WHERE d.NroOrdenDesposte = :nro");
        $stmtOrd->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
        $stmtOrd->execute();
        $ord = $stmtOrd->fetch(PDO::FETCH_ASSOC);

        if (!$ord) {
            throw new RuntimeException("La orden '{$nroOrden}' no existe.");
        }

        $pesoCanal = (float)$ord['PesoCanalEntrada'];
        $costActCanal = (float)$ord['CostActCanal'];

        $stmtCuts = $pdo->prepare("SELECT det.ID, det.NroItem, det.NroLote, det.CodProdCorte,
                                          CAST(det.PesoNeto AS FLOAT) AS PesoNeto,
                                          CAST(det.CostActBase AS FLOAT) AS CostActBase,
                                          CAST(det.MargenUtilidadPct AS FLOAT) AS MargenUtilidadPct,
                                          det.FechaElaboracion, det.FechaVencimiento,
                                          LTRIM(RTRIM(CONCAT(p.Descrip, ' ', p.Descrip2, ' ', p.Descrip3))) AS NombreCorte,
                                          p.Unidad
                                   FROM ST_Despostadora_DespostesDetalle det
                                   INNER JOIN SAPROD p ON p.CodProd = det.CodProdCorte
                                   WHERE det.NroOrdenDesposte = :nro
                                   ORDER BY det.NroItem ASC");
        $stmtCuts->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
        $stmtCuts->execute();
        $cuts = $stmtCuts->fetchAll(PDO::FETCH_ASSOC);

        $computed = YieldCalculator::compute($pesoCanal, $costActCanal, $cuts);

        return [
            'orden' => array_merge($ord, [
                'PesoCanalEntrada' => $pesoCanal,
                'CostActCanal' => $costActCanal,
                'CostoTotalCanal' => round($pesoCanal * $costActCanal, 4),
                'PesoTotalCortes' => $computed['PesoTotalCortes'],
                'PesoMerma' => $computed['PesoMerma'],
                'PerdidaMermaDinero' => $computed['PerdidaMermaDinero'],
                'RendimientoPct' => $computed['RendimientoPct'],
                'MermaPct' => $computed['MermaPct'],
                'DescuadreFisico' => $computed['DescuadreFisico']
            ]),
            'cortes' => $computed['Cortes'],
            'totales' => [
                'PesoCanal' => $pesoCanal,
                'PesoCortes' => $computed['PesoTotalCortes'],
                'PesoMerma' => $computed['PesoMerma'],
                'CostoTotalCanal' => round($pesoCanal * $costActCanal, 4),
                'TotalCostoRealCortes' => $computed['TotalCostoRealCortes'],
                'RendimientoGlobalPct' => $computed['RendimientoPct'],
                'MermaGlobalPct' => $computed['MermaPct']
            ]
        ];
    }

    /**
     * Cierra de forma atómica y definitiva una orden de desposte.
     */
    public static function closeOrder(string $nroOrden, string $codusua): void
    {
        $pdo = Database::getConnection();

        $calc = self::getYieldAndCosting($nroOrden);
        $ord = $calc['orden'];
        $cortes = $calc['cortes'];

        if ($ord['Estado'] !== 'EN_PROCESO') {
            throw new RuntimeException("La orden '{$nroOrden}' no está activa.");
        }
        if (empty($cortes)) {
            throw new RuntimeException("No puede cerrar una orden sin cortes pesados.");
        }
        if ($ord['DescuadreFisico']) {
            throw new RuntimeException("Descuadre físico: peso de cortes excede peso de canal.");
        }

        $stmtMerma = $pdo->prepare("SELECT CodProdMerma FROM ST_Despostadora_EspeciesConfig WHERE CodProd = :canal");
        $stmtMerma->bindValue(':canal', $ord['CodProdCanal'], PDO::PARAM_STR);
        $stmtMerma->execute();
        $codProdMerma = (string)($stmtMerma->fetch(PDO::FETCH_ASSOC)['CodProdMerma'] ?? '');

        $pdo->beginTransaction();
        try {
            $stmtUpdDet = $pdo->prepare("UPDATE ST_Despostadora_DespostesDetalle
                                         SET CostoMermaAbsorbidoKg = :mermakg,
                                             CostoRealUnitario = :costounit,
                                             CostoRealTotal = :costotot,
                                             PrecioVentaSugerido = :preciosug
                                         WHERE ID = :id");

            foreach ($cortes as $c) {
                $stmtUpdDet->bindValue(':mermakg', $c['CostoMermaKg'], PDO::PARAM_STR);
                $stmtUpdDet->bindValue(':costounit', $c['CostoRealUnitario'], PDO::PARAM_STR);
                $stmtUpdDet->bindValue(':costotot', $c['CostoRealTotal'], PDO::PARAM_STR);
                $stmtUpdDet->bindValue(':preciosug', $c['PrecioVentaSugerido'], PDO::PARAM_STR);
                $stmtUpdDet->bindValue(':id', $c['ID'], PDO::PARAM_INT);
                $stmtUpdDet->execute();

                $stmtStockCorte = $pdo->prepare("MERGE ST_Despostadora_Existencias AS target
                    USING (SELECT :ubic AS CodUbic, :prod AS CodProd, :lote AS NroLote, CAST(:peso AS DECIMAL(18,4)) AS Peso, CAST(:costo AS DECIMAL(18,4)) AS Costo, CAST(:elab AS DATE) AS Elab, CAST(:venc AS DATE) AS Venc) AS source
                    ON (target.CodUbic = source.CodUbic AND target.CodProd = source.CodProd AND target.NroLote = source.NroLote)
                    WHEN MATCHED THEN UPDATE SET StockActual = target.StockActual + source.Peso, CostoUnitarioReal = source.Costo, UltimaActualizacion = SYSUTCDATETIME()
                    WHEN NOT MATCHED THEN INSERT (CodUbic, CodProd, NroLote, TipoExistencia, StockActual, StockComprometido, CostoUnitarioReal, FechaElaboracion, FechaVencimiento, UltimaActualizacion)
                    VALUES (source.CodUbic, source.CodProd, source.NroLote, 'CORTE', source.Peso, 0, source.Costo, source.Elab, source.Venc, SYSUTCDATETIME());");
                $stmtStockCorte->bindValue(':ubic', $ord['CodUbic'], PDO::PARAM_STR);
                $stmtStockCorte->bindValue(':prod', $c['CodProdCorte'], PDO::PARAM_STR);
                $stmtStockCorte->bindValue(':lote', $c['NroLote'], PDO::PARAM_STR);
                $stmtStockCorte->bindValue(':peso', $c['PesoNeto'], PDO::PARAM_STR);
                $stmtStockCorte->bindValue(':costo', $c['CostoRealUnitario'], PDO::PARAM_STR);
                $stmtStockCorte->bindValue(':elab', $c['FechaElaboracion'], PDO::PARAM_STR);
                $stmtStockCorte->bindValue(':venc', $c['FechaVencimiento'], PDO::PARAM_STR);
                $stmtStockCorte->execute();
            }

            if ($codProdMerma !== '' && (float)$ord['PesoMerma'] > 0) {
                $nroLoteMerma = CorrelativeService::generateBatchNumber($pdo, $nroOrden, 99);
                $stmtStockMerma = $pdo->prepare("INSERT INTO ST_Despostadora_Existencias
                    (CodUbic, CodProd, NroLote, TipoExistencia, StockActual, StockComprometido, CostoUnitarioReal, FechaElaboracion, FechaVencimiento, UltimaActualizacion)
                    VALUES (:ubic, :prod, :lote, 'MERMA', :peso, 0, 0, CAST(GETDATE() AS DATE), NULL, SYSUTCDATETIME())");
                $stmtStockMerma->bindValue(':ubic', $ord['CodUbic'], PDO::PARAM_STR);
                $stmtStockMerma->bindValue(':prod', $codProdMerma, PDO::PARAM_STR);
                $stmtStockMerma->bindValue(':lote', $nroLoteMerma, PDO::PARAM_STR);
                $stmtStockMerma->bindValue(':peso', $ord['PesoMerma'], PDO::PARAM_STR);
                $stmtStockMerma->execute();
            }

            $stmtDescargaCanal = $pdo->prepare("UPDATE e
                SET e.StockActual = CASE WHEN e.StockActual >= p.Peso THEN e.StockActual - p.Peso ELSE 0 END,
                    e.StockComprometido = CASE WHEN e.StockComprometido >= p.Peso THEN e.StockComprometido - p.Peso ELSE 0 END,
                    e.UltimaActualizacion = SYSUTCDATETIME()
                FROM ST_Despostadora_Existencias e
                CROSS JOIN (SELECT CAST(:peso AS DECIMAL(18,4)) AS Peso) p
                WHERE e.CodUbic = :ubic AND e.CodProd = :prod AND e.NroLote = :lote");
            $stmtDescargaCanal->bindValue(':peso', $ord['PesoCanalEntrada'], PDO::PARAM_STR);
            $stmtDescargaCanal->bindValue(':ubic', $ord['CodUbic'], PDO::PARAM_STR);
            $stmtDescargaCanal->bindValue(':prod', $ord['CodProdCanal'], PDO::PARAM_STR);
            $stmtDescargaCanal->bindValue(':lote', $ord['NroRecepcion'], PDO::PARAM_STR);
            $stmtDescargaCanal->execute();

            $stmtCierreOrd = $pdo->prepare("UPDATE ST_Despostadora_Despostes
                SET FechaHoraCierre = SYSUTCDATETIME(), PesoTotalCortes = :pesocortes, PesoMerma = :pesomerma,
                    CostoPerdidaMerma = :perdidamerma, RendimientoPct = :rend, MermaPct = :merma, Estado = 'FINALIZADO'
                WHERE NroOrdenDesposte = :nro");
            $stmtCierreOrd->bindValue(':pesocortes', $ord['PesoTotalCortes'], PDO::PARAM_STR);
            $stmtCierreOrd->bindValue(':pesomerma', $ord['PesoMerma'], PDO::PARAM_STR);
            $stmtCierreOrd->bindValue(':perdidamerma', $ord['PerdidaMermaDinero'], PDO::PARAM_STR);
            $stmtCierreOrd->bindValue(':rend', $ord['RendimientoPct'], PDO::PARAM_STR);
            $stmtCierreOrd->bindValue(':merma', $ord['MermaPct'], PDO::PARAM_STR);
            $stmtCierreOrd->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
            $stmtCierreOrd->execute();

            $stmtUpdEsp = $pdo->prepare("UPDATE ST_Despostadora_RecepcionesDetalle SET EstadoDesposte = 'DESPOSTADO' WHERE NroOrdenDesposte = :nro");
            $stmtUpdEsp->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
            $stmtUpdEsp->execute();

            AuditService::log('DESPOSTE', $nroOrden, 'CERRAR_ORDEN', $codusua, [
                'PesoCanal' => $ord['PesoCanalEntrada'],
                'PesoCortes' => $ord['PesoTotalCortes'],
                'PesoMerma' => $ord['PesoMerma']
            ], $pdo);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException("Error cerrando orden: " . $e->getMessage(), 0, $e);
        }
    }

    private static function recalculateOrderHeader(PDO $pdo, string $nroOrden): void
    {
        $stmtTot = $pdo->prepare("SELECT CAST(ISNULL(SUM(PesoNeto), 0) AS FLOAT) AS PesoTotal FROM ST_Despostadora_DespostesDetalle WHERE NroOrdenDesposte = :nro");
        $stmtTot->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
        $stmtTot->execute();
        $pesoCortes = (float)($stmtTot->fetch(PDO::FETCH_ASSOC)['PesoTotal'] ?? 0.0);

        $stmtOrd = $pdo->prepare("SELECT CAST(PesoCanalEntrada AS FLOAT) AS PesoCanal, CAST(CostActCanal AS FLOAT) AS CostActCanal FROM ST_Despostadora_Despostes WHERE NroOrdenDesposte = :nro");
        $stmtOrd->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
        $stmtOrd->execute();
        $ord = $stmtOrd->fetch(PDO::FETCH_ASSOC);

        $pesoCanal = (float)($ord['PesoCanal'] ?? 0.0);
        $costActCanal = (float)($ord['CostActCanal'] ?? 0.0);

        $pesoMerma = round($pesoCanal - $pesoCortes, 4);
        $rendPct = $pesoCanal > 0 ? round(($pesoCortes / $pesoCanal) * 100, 2) : 0.0;
        $mermaPct = $pesoCanal > 0 ? round(($pesoMerma / $pesoCanal) * 100, 2) : 0.0;
        $perdidaMerma = ($pesoMerma > 0) ? round($pesoMerma * $costActCanal, 4) : 0.0;

        $stmtUpd = $pdo->prepare("UPDATE ST_Despostadora_Despostes
                                  SET PesoTotalCortes = :pesocortes, PesoMerma = :pesomerma,
                                      CostoPerdidaMerma = :perdidamerma, RendimientoPct = :rend, MermaPct = :merma
                                  WHERE NroOrdenDesposte = :nro");
        $stmtUpd->bindValue(':pesocortes', $pesoCortes, PDO::PARAM_STR);
        $stmtUpd->bindValue(':pesomerma', $pesoMerma, PDO::PARAM_STR);
        $stmtUpd->bindValue(':perdidamerma', $perdidaMerma, PDO::PARAM_STR);
        $stmtUpd->bindValue(':rend', $rendPct, PDO::PARAM_STR);
        $stmtUpd->bindValue(':merma', $mermaPct, PDO::PARAM_STR);
        $stmtUpd->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
        $stmtUpd->execute();
    }

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * @return list<array<string, mixed>>
     */
    public static function getActiveOrders(): array
    {
        return DeboningQueryService::getActiveOrders();
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int, fecha_desde: ?string, fecha_hasta: ?string}
     */
    public static function getOrdersPaginated(
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        int $page = 1,
        int $perPage = 10,
        ?string $estado = null
    ): array {
        return DeboningQueryService::getOrdersPaginated($fechaDesde, $fechaHasta, $page, $perPage, $estado);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getOrdersHistory(int $limit = 50): array
    {
        return DeboningQueryService::getOrdersHistory($limit);
    }
}
