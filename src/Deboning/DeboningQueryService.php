<?php
declare(strict_types=1);

namespace Src\Deboning;

use Config\Database;
use DateTime;
use PDO;
use RuntimeException;

final class DeboningQueryService
{
    /**
     * Obtiene el balance y tabla de costeo detallada para la orden.
     * @param string $nroOrden
     * @return array<string, mixed>
     */
    public static function getYieldAndCosting(string $nroOrden): array
    {
        $pdo = Database::getConnection();

        // Sincronizar cruce ERP si la orden está finalizada
        DeboningErpHoldService::syncDeboningCruceStatus($pdo, $nroOrden);

        $stmtOrd = $pdo->prepare("SELECT d.NroOrdenDesposte, d.NroRecepcion, d.LineaMesa, d.FechaHoraInicio,
                                         d.CodProdCanal, d.CostActCanal, d.PesoCanalEntrada, d.CostoTotalCanal,
                                         d.Estado, r.CodUbic,
                                         d.DocDescargoEnviado, d.DocDescargoNumeroD, d.DocDescargoProcesado,
                                         d.DocDescargoNumeroDOficial, d.DocDescargoTipoOpIFinal, d.FechaDocDescargoProcesado,
                                         d.DocCargoEnviado, d.DocCargoNumeroD, d.DocCargoProcesado,
                                         d.DocCargoNumeroDOficial, d.DocCargoTipoOpIFinal, d.FechaDocCargoProcesado,
                                         d.EstadoCruceERP,
                                         CAST(ISNULL(d.Version, 1) AS INT) AS Version,
                                         d.UltimoUsuarioModif, d.UltimaModif, d.CodUsuaCierre, d.FechaHoraCierre,
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
                                          det.CodUsuaCreacion, det.CodUsuaModif, det.FechaHoraCreacion, det.FechaHoraModif,
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
            'especie_cortes' => ConfigService::getSpeciesCuts((string)$ord['CodProdCanal']),
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
     * Obtiene el listado de órdenes activas (EN_PROCESO) para el tablero en vivo.
     * @return list<array<string, mixed>>
     */
    public static function getActiveOrders(): array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT d.NroOrdenDesposte, d.NroRecepcion, d.LineaMesa, d.FechaHoraInicio, d.CodUsua, d.CodProdCanal,
                       LTRIM(RTRIM(CONCAT(p.Descrip, ' ', p.Descrip2, ' ', p.Descrip3))) AS NombreCanal,
                       CAST(d.PesoCanalEntrada AS FLOAT) AS PesoCanalEntrada,
                       CAST(d.PesoTotalCortes AS FLOAT) AS PesoTotalCortes,
                       CAST(d.PesoMerma AS FLOAT) AS PesoMerma,
                       CAST(d.RendimientoPct AS FLOAT) AS RendimientoPct,
                       CAST(d.MermaPct AS FLOAT) AS MermaPct, d.Estado,
                       CAST(ISNULL(d.DocDescargoEnviado, 0) AS INT) AS DocDescargoEnviado,
                       ISNULL(d.DocDescargoNumeroD, '') AS DocDescargoNumeroD,
                       CAST(ISNULL(d.DocDescargoProcesado, 0) AS INT) AS DocDescargoProcesado,
                       ISNULL(d.DocDescargoNumeroDOficial, '') AS DocDescargoNumeroDOficial,
                       ISNULL(d.DocDescargoTipoOpIFinal, '') AS DocDescargoTipoOpIFinal,
                       d.FechaDocDescargoProcesado,
                       CAST(ISNULL(d.DocCargoEnviado, 0) AS INT) AS DocCargoEnviado,
                       ISNULL(d.DocCargoNumeroD, '') AS DocCargoNumeroD,
                       CAST(ISNULL(d.DocCargoProcesado, 0) AS INT) AS DocCargoProcesado,
                       ISNULL(d.DocCargoNumeroDOficial, '') AS DocCargoNumeroDOficial,
                       ISNULL(d.DocCargoTipoOpIFinal, '') AS DocCargoTipoOpIFinal,
                       d.FechaDocCargoProcesado,
                       ISNULL(d.EstadoCruceERP, 'PENDIENTE_CIERRE') AS EstadoCruceERP,
                       (SELECT COUNT(1) FROM ST_Despostadora_DespostesDetalle det WHERE det.NroOrdenDesposte = d.NroOrdenDesposte) AS TotalCortesPesados
                FROM ST_Despostadora_Despostes d
                INNER JOIN SAPROD p ON p.CodProd = d.CodProdCanal
                WHERE d.Estado = 'EN_PROCESO'
                ORDER BY d.FechaHoraInicio DESC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el listado de órdenes de desposte de forma paginada y filtrada por fecha.
     *
     * @param string|null $fechaDesde Fecha inicial YYYY-MM-DD
     * @param string|null $fechaHasta Fecha final YYYY-MM-DD
     * @param int $page Número de página actual
     * @param int $perPage Cantidad de registros por página
     * @param string|null $estado Filtro opcional por estado
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int, fecha_desde: ?string, fecha_hasta: ?string}
     */
    public static function getOrdersPaginated(
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        int $page = 1,
        int $perPage = 10,
        ?string $estado = null
    ): array {
        $pdo = Database::getConnection();

        // Sincronizar automáticamente en tiempo real el cruce de documentos de desposte con Saint ERP
        try {
            DeboningErpHoldService::syncDeboningCruceStatus($pdo);
        } catch (\Throwable $e) {
            error_log('[SYNC DEBONING ERP ERROR] ' . $e->getMessage());
        }

        $where = [];
        $params = [];

        $cleanDesde = null;
        if ($fechaDesde !== null && trim($fechaDesde) !== '') {
            $d = DateTime::createFromFormat('Y-m-d', trim($fechaDesde));
            if ($d && $d->format('Y-m-d') === trim($fechaDesde)) {
                $cleanDesde = trim($fechaDesde);
                $where[] = "CAST(d.FechaHoraInicio AS DATE) >= :fdesde";
                $params[':fdesde'] = $cleanDesde;
            }
        }

        $cleanHasta = null;
        if ($fechaHasta !== null && trim($fechaHasta) !== '') {
            $d = DateTime::createFromFormat('Y-m-d', trim($fechaHasta));
            if ($d && $d->format('Y-m-d') === trim($fechaHasta)) {
                $cleanHasta = trim($fechaHasta);
                $where[] = "CAST(d.FechaHoraInicio AS DATE) <= :fhasta";
                $params[':fhasta'] = $cleanHasta;
            }
        }

        if ($estado !== null && trim($estado) !== '') {
            $where[] = "d.Estado = :estado";
            $params[':estado'] = trim($estado);
        }

        $whereSql = !empty($where) ? (' WHERE ' . implode(' AND ', $where)) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(1) AS Total FROM ST_Despostadora_Despostes d" . $whereSql);
        foreach ($params as $param => $val) {
            $countStmt->bindValue($param, $val, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['Total'] ?? 0);

        $perPage = max(1, $perPage);
        $totalPages = (int)ceil($total / $perPage);
        if ($totalPages < 1) {
            $totalPages = 1;
        }
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT d.NroOrdenDesposte, d.NroRecepcion, d.LineaMesa, d.FechaHoraInicio, d.FechaHoraCierre,
                       d.CodUsua, d.CodProdCanal,
                       LTRIM(RTRIM(CONCAT(p.Descrip, ' ', p.Descrip2, ' ', p.Descrip3))) AS NombreCanal,
                       CAST(d.PesoCanalEntrada AS FLOAT) AS PesoCanalEntrada,
                       CAST(d.PesoTotalCortes AS FLOAT) AS PesoTotalCortes,
                       CAST(d.PesoMerma AS FLOAT) AS PesoMerma,
                       CAST(d.RendimientoPct AS FLOAT) AS RendimientoPct,
                       CAST(d.MermaPct AS FLOAT) AS MermaPct, d.Estado,
                       CAST(ISNULL(d.DocDescargoEnviado, 0) AS INT) AS DocDescargoEnviado,
                       ISNULL(d.DocDescargoNumeroD, '') AS DocDescargoNumeroD,
                       CAST(ISNULL(d.DocDescargoProcesado, 0) AS INT) AS DocDescargoProcesado,
                       ISNULL(d.DocDescargoNumeroDOficial, '') AS DocDescargoNumeroDOficial,
                       ISNULL(d.DocDescargoTipoOpIFinal, '') AS DocDescargoTipoOpIFinal,
                       d.FechaDocDescargoProcesado,
                       CAST(ISNULL(d.DocCargoEnviado, 0) AS INT) AS DocCargoEnviado,
                       ISNULL(d.DocCargoNumeroD, '') AS DocCargoNumeroD,
                       CAST(ISNULL(d.DocCargoProcesado, 0) AS INT) AS DocCargoProcesado,
                       ISNULL(d.DocCargoNumeroDOficial, '') AS DocCargoNumeroDOficial,
                       ISNULL(d.DocCargoTipoOpIFinal, '') AS DocCargoTipoOpIFinal,
                       d.FechaDocCargoProcesado,
                       ISNULL(d.EstadoCruceERP, 'PENDIENTE_CIERRE') AS EstadoCruceERP,
                       (SELECT COUNT(1) FROM ST_Despostadora_DespostesDetalle det WHERE det.NroOrdenDesposte = d.NroOrdenDesposte) AS TotalCortesPesados
                FROM ST_Despostadora_Despostes d
                INNER JOIN SAPROD p ON p.CodProd = d.CodProdCanal
                {$whereSql}
                ORDER BY d.FechaHoraInicio DESC
                OFFSET :offset ROWS
                FETCH NEXT :limit ROWS ONLY";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $param => $val) {
            $stmt->bindValue($param, $val, PDO::PARAM_STR);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'fecha_desde' => $cleanDesde,
            'fecha_hasta' => $cleanHasta,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getOrdersHistory(int $limit = 50): array
    {
        $res = self::getOrdersPaginated(null, null, 1, $limit);
        return $res['items'];
    }

    /**
     * Obtiene el conjunto completo y conciliado de datos para la liquidación impresa de desposte,
     * incluyendo balance de masas, doble cruce ERP (Descargo y Cargo), códigos de barras y cuadre contable.
     *
     * @param string $nroOrden
     * @return array<string, mixed>
     */
    public static function getDeboningPrintData(string $nroOrden): array
    {
        $pdo = Database::getConnection();
        $nro = trim($nroOrden);

        // 1. Obtener balance completo y sincronizar con SAOPEI/SAITEMOPI
        $calcData = self::getYieldAndCosting($nro);
        $ord = $calcData['orden'];

        // 2. Datos de empresa desde SACONF
        $stmtEmp = $pdo->query("SELECT TOP 1 NroSerial, Descrip FROM SACONF ORDER BY CASE WHEN CodSucu = '00000' THEN 0 ELSE 1 END");
        $empresa = $stmtEmp ? ($stmtEmp->fetch(PDO::FETCH_ASSOC) ?: []) : [];

        // 3. Descripción de depósito operativo
        $codUbic = (string)($ord['CodUbic'] ?? '');
        $stmtDep = $pdo->prepare("SELECT Descrip FROM SADEPO WHERE CodUbic = :ubic");
        $stmtDep->bindValue(':ubic', $codUbic, PDO::PARAM_STR);
        $stmtDep->execute();
        $depRow = $stmtDep->fetch(PDO::FETCH_ASSOC);
        $nombreUbicacion = $depRow ? trim((string)$depRow['Descrip']) : $codUbic;

        // 4. Nombres de usuarios (creación y cierre)
        $codUsua = (string)($ord['CodUsua'] ?? '');
        $codUsuaCierre = (string)($ord['CodUsuaCierre'] ?? $codUsua);

        $stmtU1 = $pdo->prepare("SELECT Descrip FROM SSUSRS WHERE CodUsua = :u");
        $stmtU1->bindValue(':u', $codUsua, PDO::PARAM_STR);
        $stmtU1->execute();
        $u1 = $stmtU1->fetch(PDO::FETCH_ASSOC);
        $nombreUsuaInicio = $u1 ? trim((string)$u1['Descrip']) : $codUsua;

        $stmtU2 = $pdo->prepare("SELECT Descrip FROM SSUSRS WHERE CodUsua = :u");
        $stmtU2->bindValue(':u', $codUsuaCierre, PDO::PARAM_STR);
        $stmtU2->execute();
        $u2 = $stmtU2->fetch(PDO::FETCH_ASSOC);
        $nombreUsuaCierre = $u2 ? trim((string)$u2['Descrip']) : $codUsuaCierre;

        // 5. Configuración de producto de merma
        $codProdCanal = (string)($ord['CodProdCanal'] ?? '');
        $stmtMerma = $pdo->prepare("SELECT ec.CodProdMerma, 
                                           LTRIM(RTRIM(CONCAT(p.Descrip, ' ', p.Descrip2, ' ', p.Descrip3))) AS NombreMerma,
                                           p.Unidad
                                    FROM ST_Despostadora_EspeciesConfig ec
                                    LEFT JOIN SAPROD p ON p.CodProd = ec.CodProdMerma
                                    WHERE ec.CodProd = :canal");
        $stmtMerma->bindValue(':canal', $codProdCanal, PDO::PARAM_STR);
        $stmtMerma->execute();
        $mermaRow = $stmtMerma->fetch(PDO::FETCH_ASSOC) ?: [];

        // 6. Configuración de códigos de operación ERP
        $codOperDescargo = ConfigService::getConfig('ERP_CODOPER_DESCARGO_DESPOSTE', 'PRO001');
        $codOperCargo = ConfigService::getConfig('ERP_CODOPER_CARGO_DESPOSTE', 'PRO002');
        $codEsta = ConfigService::getConfig('ERP_CODESTA_DEFAULT', 'DESPOSTE');

        // Formato estándar NumeroD
        $numSolo = (string)preg_replace('/^DESP-?/i', '', $nro);
        $numPadded = str_pad($numSolo, 16, '0', STR_PAD_LEFT);
        $defaultNumDDescargo = 'D-' . $numPadded;
        $defaultNumDCargo    = 'C-' . $numPadded;

        $numDDescargo = !empty($ord['DocDescargoNumeroD']) ? (string)$ord['DocDescargoNumeroD'] : $defaultNumDDescargo;
        $numDCargo    = !empty($ord['DocCargoNumeroD']) ? (string)$ord['DocCargoNumeroD'] : $defaultNumDCargo;

        // Balances de paridad
        $pesoEntrada = (float)$ord['PesoCanalEntrada'];
        $pesoCortesTotal = (float)$calcData['totales']['PesoCortes'];
        $pesoMermaTotal = (float)$calcData['totales']['PesoMerma'];
        $pesoCargoTotal = round($pesoCortesTotal + $pesoMermaTotal, 4);

        $montoEntrada = (float)$ord['CostoTotalCanal'];
        $montoCargoTotal = (float)$calcData['totales']['TotalCostoRealCortes'];

        return [
            'orden' => array_merge($ord, [
                'NombreUbicacion' => $nombreUbicacion,
                'NombreUsuarioInicio' => $nombreUsuaInicio,
                'NombreUsuarioCierre' => $nombreUsuaCierre,
                'TotalKilosCargo' => $pesoCargoTotal,
                'ParidadKilos' => abs($pesoEntrada - $pesoCargoTotal) < 0.0001,
                'DiferenciaKilos' => round($pesoEntrada - $pesoCargoTotal, 4),
                'CuadreMonetario' => abs($montoEntrada - $montoCargoTotal) < 0.01,
                'DiferenciaMonto' => round($montoEntrada - $montoCargoTotal, 4)
            ]),
            'cortes' => $calcData['cortes'],
            'totales' => $calcData['totales'],
            'merma_info' => [
                'CodProdMerma' => (string)($mermaRow['CodProdMerma'] ?? 'MERMA'),
                'NombreMerma' => (string)($mermaRow['NombreMerma'] ?? 'MERMA DE DESPOSTE'),
                'Unidad' => (string)($mermaRow['Unidad'] ?? 'KG'),
                'PesoMerma' => $pesoMermaTotal,
                'MermaPct' => (float)$ord['MermaPct'],
                'CostoUnitario' => 0.0000,
                'TotalItem' => 0.0000,
                'NroLote' => CorrelativeService::generateBatchNumber($pdo, $nro, 99)
            ],
            'empresa' => [
                'Nombre' => trim((string)($empresa['Descrip'] ?? 'SOLUCIONES TOTALES DESPOSTADORA')),
                'RIF' => trim((string)($empresa['NroSerial'] ?? 'J-00000000-0')),
            ],
            'erp' => [
                'CodEsta' => $codEsta,
                'descargo' => [
                    'Modulo' => 'Operaciones de Inventario',
                    'TipoOpI' => 'R',
                    'Signo' => -1,
                    'CodOper' => $codOperDescargo,
                    'NumeroD' => $numDDescargo,
                    'Autori' => 'DOC.DESP.SAL-' . $nro,
                    'Trazabilidad' => $nro . '-DES',
                    'Enviado' => (bool)($ord['DocDescargoEnviado'] ?? false),
                    'Procesado' => (bool)($ord['DocDescargoProcesado'] ?? false),
                    'NumeroDOficial' => (string)($ord['DocDescargoNumeroDOficial'] ?? ''),
                    'TipoOpIFinal' => (string)($ord['DocDescargoTipoOpIFinal'] ?? ''),
                    'FechaProcesado' => (string)($ord['FechaDocDescargoProcesado'] ?? ''),
                    'Kilos' => $pesoEntrada,
                    'Monto' => $montoEntrada
                ],
                'cargo' => [
                    'Modulo' => 'Operaciones de Inventario',
                    'TipoOpI' => 'R',
                    'Signo' => 1,
                    'CodOper' => $codOperCargo,
                    'NumeroD' => $numDCargo,
                    'Autori' => 'DOC.DESP.ENT-' . $nro,
                    'Trazabilidad' => $nro . '-CAR',
                    'Enviado' => (bool)($ord['DocCargoEnviado'] ?? false),
                    'Procesado' => (bool)($ord['DocCargoProcesado'] ?? false),
                    'NumeroDOficial' => (string)($ord['DocCargoNumeroDOficial'] ?? ''),
                    'TipoOpIFinal' => (string)($ord['DocCargoTipoOpIFinal'] ?? ''),
                    'FechaProcesado' => (string)($ord['FechaDocCargoProcesado'] ?? ''),
                    'Kilos' => $pesoCargoTotal,
                    'Monto' => $montoCargoTotal
                ]
            ]
        ];
    }
}
