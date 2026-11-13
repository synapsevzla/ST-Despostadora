<?php
declare(strict_types=1);

namespace Src\Deboning;

use Config\Database;
use Src\Utils\AuditService;
use PDO;
use RuntimeException;
use Throwable;

final class ReceptionService
{
    /**
     * Registra una nueva recepción de carne en canal bajo cualquiera de los 3 métodos de entrada:
     * - SOLO_PESO
     * - PESO_CANTIDAD
     * - PESO_INDIVIDUAL
     *
     * @param array<string, mixed> $data Datos de la recepción
     * @param string $codusua Usuario autenticado que registra
     * @return string Correlativo generado (NroRecepcion)
     */
    public static function createReception(array $data, string $codusua): string
    {
        $pdo = Database::getConnection();
        $codProdCanal = trim((string)($data['codprod'] ?? ''));
        $metodoEntrada = strtoupper(trim((string)($data['metodo_entrada'] ?? 'SOLO_PESO')));
        $observaciones = trim((string)($data['observaciones'] ?? ''));

        if ($codProdCanal === '') {
            throw new RuntimeException("Debe seleccionar una especie o producto de canal válido.");
        }

        // 1. Obtener y validar depósito operativo
        $codUbic = trim((string)($data['codubic'] ?? ''));
        if ($codUbic === '') {
            $codUbic = ConfigService::getConfig('DEFAULT_CODUBIC', '');
        }
        if ($codUbic === '') {
            throw new RuntimeException("No hay un depósito operativo configurado en el sistema.");
        }

        // 2. Obtener CostAct actual y descripción de SAPROD
        $stmtProd = $pdo->prepare("SELECT CodProd, LTRIM(RTRIM(CONCAT(Descrip, ' ', Descrip2, ' ', Descrip3))) AS Descrip, 
                                          CAST(ISNULL(CostAct, 0) AS FLOAT) AS CostAct 
                                   FROM SAPROD 
                                   WHERE CodProd = :codprod");
        $stmtProd->bindValue(':codprod', $codProdCanal, PDO::PARAM_STR);
        $stmtProd->execute();
        $prodInfo = $stmtProd->fetch(PDO::FETCH_ASSOC);

        if (!$prodInfo) {
            throw new RuntimeException("El producto '{$codProdCanal}' no existe en el catálogo de productos.");
        }

        $costActCanal = (float)$prodInfo['CostAct'];

        // Variables calculadas según el método
        $cantidadEspecies = 0;
        $pesoBruto = 0.0;
        $tara = (float)($data['tara'] ?? 0.0);
        $pesoNeto = 0.0;
        $pesosIndividuales = [];

        if ($metodoEntrada === 'SOLO_PESO') {
            $pesoBruto = (float)($data['peso_bruto'] ?? 0.0);
            $pesoNeto = (float)($data['peso_neto'] ?? ($pesoBruto - $tara));
            if ($pesoNeto <= 0) {
                throw new RuntimeException("El peso neto recibido debe ser mayor a cero.");
            }
            $cantidadEspecies = (int)($data['cantidad'] ?? 0);
        } elseif ($metodoEntrada === 'PESO_CANTIDAD') {
            $cantidadEspecies = (int)($data['cantidad'] ?? 0);
            if ($cantidadEspecies <= 0) {
                throw new RuntimeException("La cantidad de especies/cabezas debe ser mayor a cero.");
            }
            $pesoBruto = (float)($data['peso_bruto'] ?? 0.0);
            $pesoNeto = (float)($data['peso_neto'] ?? ($pesoBruto - $tara));
            if ($pesoNeto <= 0) {
                throw new RuntimeException("El peso neto recibido debe ser mayor a cero.");
            }
        } elseif ($metodoEntrada === 'PESO_INDIVIDUAL') {
            $rawPesos = $data['pesos_individuales'] ?? [];
            if (!is_array($rawPesos) || empty($rawPesos)) {
                throw new RuntimeException("Debe ingresar al menos un pesaje individual de animal/canal.");
            }

            foreach ($rawPesos as $p) {
                $val = (float)$p;
                if ($val > 0) {
                    $pesosIndividuales[] = $val;
                }
            }

            if (empty($pesosIndividuales)) {
                throw new RuntimeException("Ningún pesaje individual válido mayor a cero fue recibido.");
            }

            $cantidadEspecies = count($pesosIndividuales);
            $pesoNeto = array_sum($pesosIndividuales);
            $pesoBruto = $pesoNeto + $tara;
        } else {
            throw new RuntimeException("Método de recepción no reconocido: '{$metodoEntrada}'.");
        }

        $costoTotalNeto = round($pesoNeto * $costActCanal, 4);

        $pdo->beginTransaction();
        try {
            // 3. Generar correlativo atómico sin IDENTITY
            $nroRecepcion = CorrelativeService::getNext($pdo, 'RECEPCION');

            // 4. Insertar encabezado de recepción
            $sqlInsRec = "INSERT INTO ST_Despostadora_Recepciones
                (NroRecepcion, FechaHora, CodUsua, CodUbic, CodProdCanal, CostActCanal, MetodoEntrada,
                 CantidadEspecies, PesoTotalBruto, TaraTotal, PesoTotalNeto, CostoTotalNeto, PesoDisponible,
                 Estado, Observaciones)
                VALUES
                (:nrorec, SYSUTCDATETIME(), :codusua, :codubic, :codprod, :costact, :metodo,
                 :cant, :bruto, :tara, :neto, :costotot, :disp, 'DISPONIBLE', :obs)";

            $stmtInsRec = $pdo->prepare($sqlInsRec);
            $stmtInsRec->bindValue(':nrorec', $nroRecepcion, PDO::PARAM_STR);
            $stmtInsRec->bindValue(':codusua', $codusua, PDO::PARAM_STR);
            $stmtInsRec->bindValue(':codubic', $codUbic, PDO::PARAM_STR);
            $stmtInsRec->bindValue(':codprod', $codProdCanal, PDO::PARAM_STR);
            $stmtInsRec->bindValue(':costact', $costActCanal, PDO::PARAM_STR);
            $stmtInsRec->bindValue(':metodo', $metodoEntrada, PDO::PARAM_STR);
            $stmtInsRec->bindValue(':cant', $cantidadEspecies, PDO::PARAM_INT);
            $stmtInsRec->bindValue(':bruto', $pesoBruto, PDO::PARAM_STR);
            $stmtInsRec->bindValue(':tara', $tara, PDO::PARAM_STR);
            $stmtInsRec->bindValue(':neto', $pesoNeto, PDO::PARAM_STR);
            $stmtInsRec->bindValue(':costotot', $costoTotalNeto, PDO::PARAM_STR);
            $stmtInsRec->bindValue(':disp', $pesoNeto, PDO::PARAM_STR);
            $stmtInsRec->bindValue(':obs', $observaciones !== '' ? $observaciones : null, $observaciones !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmtInsRec->execute();

            // 5. Si es peso individual, insertar en ST_Despostadora_RecepcionesDetalle
            if ($metodoEntrada === 'PESO_INDIVIDUAL') {
                $sqlDet = "INSERT INTO ST_Despostadora_RecepcionesDetalle
                    (NroRecepcion, NroEspecie, PesoNeto, CostoEspecie, EstadoDesposte)
                    VALUES (:nrorec, :nroesp, :peso, :costo, 'DISPONIBLE')";
                $stmtDet = $pdo->prepare($sqlDet);

                $nroEsp = 1;
                foreach ($pesosIndividuales as $pIndividual) {
                    $costoIndividual = round($pIndividual * $costActCanal, 4);
                    $stmtDet->bindValue(':nrorec', $nroRecepcion, PDO::PARAM_STR);
                    $stmtDet->bindValue(':nroesp', $nroEsp, PDO::PARAM_INT);
                    $stmtDet->bindValue(':peso', $pIndividual, PDO::PARAM_STR);
                    $stmtDet->bindValue(':costo', $costoIndividual, PDO::PARAM_STR);
                    $stmtDet->execute();
                    $nroEsp++;
                }
            }

            // 6. Cargar en existencias internas ST_Despostadora_Existencias (CERO afectación a SAPROD)
            $sqlStock = "INSERT INTO ST_Despostadora_Existencias
                (CodUbic, CodProd, NroLote, TipoExistencia, StockActual, StockComprometido, CostoUnitarioReal, FechaElaboracion, FechaVencimiento, UltimaActualizacion)
                VALUES
                (:codubic, :codprod, :lote, 'CANAL', :stock, 0, :costo, CAST(GETDATE() AS DATE), NULL, SYSUTCDATETIME())";
            $stmtStock = $pdo->prepare($sqlStock);
            $stmtStock->bindValue(':codubic', $codUbic, PDO::PARAM_STR);
            $stmtStock->bindValue(':codprod', $codProdCanal, PDO::PARAM_STR);
            $stmtStock->bindValue(':lote', $nroRecepcion, PDO::PARAM_STR);
            $stmtStock->bindValue(':stock', $pesoNeto, PDO::PARAM_STR);
            $stmtStock->bindValue(':costo', $costActCanal, PDO::PARAM_STR);
            $stmtStock->execute();

            // 7. Auditoría
            AuditService::log('RECEPCION', $nroRecepcion, 'CREAR_RECEPCION', $codusua, [
                'NroRecepcion' => $nroRecepcion,
                'CodProdCanal' => $codProdCanal,
                'MetodoEntrada' => $metodoEntrada,
                'CantidadEspecies' => $cantidadEspecies,
                'PesoNeto' => $pesoNeto,
                'CostActCanal' => $costActCanal,
                'CostoTotalNeto' => $costoTotalNeto
            ], $pdo);

            $pdo->commit();
            return $nroRecepcion;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException("Error registrando la recepción: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Obtiene el listado de recepciones registradas.
     * @return list<array<string, mixed>>
     */
    public static function getReceptions(?string $estado = null): array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT 
                    r.NroRecepcion,
                    r.FechaHora,
                    r.CodUsua,
                    r.CodUbic,
                    r.CodProdCanal,
                    LTRIM(RTRIM(CONCAT(p.Descrip, ' ', p.Descrip2, ' ', p.Descrip3))) AS NombreCanal,
                    p.Unidad,
                    CAST(r.CostActCanal AS FLOAT) AS CostActCanal,
                    r.MetodoEntrada,
                    r.CantidadEspecies,
                    CAST(r.PesoTotalBruto AS FLOAT) AS PesoTotalBruto,
                    CAST(r.TaraTotal AS FLOAT) AS TaraTotal,
                    CAST(r.PesoTotalNeto AS FLOAT) AS PesoTotalNeto,
                    CAST(r.CostoTotalNeto AS FLOAT) AS CostoTotalNeto,
                    CAST(r.PesoDisponible AS FLOAT) AS PesoDisponible,
                    r.Estado,
                    r.Observaciones
                FROM ST_Despostadora_Recepciones r
                INNER JOIN SAPROD p ON p.CodProd = r.CodProdCanal";

        if ($estado !== null && trim($estado) !== '') {
            $sql .= " WHERE r.Estado = :estado";
        }

        $sql .= " ORDER BY r.FechaHora DESC";

        $stmt = $pdo->prepare($sql);
        if ($estado !== null && trim($estado) !== '') {
            $stmt->bindValue(':estado', trim($estado), PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el listado de recepciones registradas de forma paginada y filtrada por fecha.
     *
     * @param string|null $fechaDesde Fecha inicial YYYY-MM-DD
     * @param string|null $fechaHasta Fecha final YYYY-MM-DD
     * @param int $page Número de página actual
     * @param int $perPage Cantidad de registros por página
     * @param string|null $estado Filtro opcional por estado
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int, fecha_desde: ?string, fecha_hasta: ?string}
     */
    public static function getReceptionsPaginated(
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        int $page = 1,
        int $perPage = 10,
        ?string $estado = null
    ): array {
        $pdo = Database::getConnection();

        $where = [];
        $params = [];

        $cleanDesde = null;
        if ($fechaDesde !== null && trim($fechaDesde) !== '') {
            $d = \DateTime::createFromFormat('Y-m-d', trim($fechaDesde));
            if ($d && $d->format('Y-m-d') === trim($fechaDesde)) {
                $cleanDesde = trim($fechaDesde);
                $where[] = "CAST(r.FechaHora AS DATE) >= :fdesde";
                $params[':fdesde'] = $cleanDesde;
            }
        }

        $cleanHasta = null;
        if ($fechaHasta !== null && trim($fechaHasta) !== '') {
            $d = \DateTime::createFromFormat('Y-m-d', trim($fechaHasta));
            if ($d && $d->format('Y-m-d') === trim($fechaHasta)) {
                $cleanHasta = trim($fechaHasta);
                $where[] = "CAST(r.FechaHora AS DATE) <= :fhasta";
                $params[':fhasta'] = $cleanHasta;
            }
        }

        if ($estado !== null && trim($estado) !== '') {
            $where[] = "r.Estado = :estado";
            $params[':estado'] = trim($estado);
        }

        $whereSql = !empty($where) ? (' WHERE ' . implode(' AND ', $where)) : '';

        // 1. Contar total de registros
        $countSql = "SELECT COUNT(1) AS Total FROM ST_Despostadora_Recepciones r" . $whereSql;
        $countStmt = $pdo->prepare($countSql);
        foreach ($params as $param => $val) {
            $countStmt->bindValue($param, $val, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['Total'] ?? 0);

        // 2. Calcular límites de paginación
        $perPage = max(1, $perPage);
        $totalPages = (int)ceil($total / $perPage);
        if ($totalPages < 1) {
            $totalPages = 1;
        }
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        // 3. Obtener registros paginados
        $dataSql = "SELECT 
                        r.NroRecepcion,
                        r.FechaHora,
                        r.CodUsua,
                        r.CodUbic,
                        r.CodProdCanal,
                        LTRIM(RTRIM(CONCAT(p.Descrip, ' ', p.Descrip2, ' ', p.Descrip3))) AS NombreCanal,
                        p.Unidad,
                        CAST(r.CostActCanal AS FLOAT) AS CostActCanal,
                        r.MetodoEntrada,
                        r.CantidadEspecies,
                        CAST(r.PesoTotalBruto AS FLOAT) AS PesoTotalBruto,
                        CAST(r.TaraTotal AS FLOAT) AS TaraTotal,
                        CAST(r.PesoTotalNeto AS FLOAT) AS PesoTotalNeto,
                        CAST(r.CostoTotalNeto AS FLOAT) AS CostoTotalNeto,
                        CAST(r.PesoDisponible AS FLOAT) AS PesoDisponible,
                        r.Estado,
                        r.Observaciones
                    FROM ST_Despostadora_Recepciones r
                    INNER JOIN SAPROD p ON p.CodProd = r.CodProdCanal
                    {$whereSql}
                    ORDER BY r.FechaHora DESC
                    OFFSET :offset ROWS
                    FETCH NEXT :limit ROWS ONLY";

        $dataStmt = $pdo->prepare($dataSql);
        foreach ($params as $param => $val) {
            $dataStmt->bindValue($param, $val, PDO::PARAM_STR);
        }
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $dataStmt->execute();
        $items = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

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
     * Obtiene el detalle de piezas individuales para una recepción.
     * @return list<array<string, mixed>>
     */
    public static function getReceptionDetail(string $nroRecepcion): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT ID, NroRecepcion, NroEspecie, CAST(PesoNeto AS FLOAT) AS PesoNeto, 
                                      CAST(CostoEspecie AS FLOAT) AS CostoEspecie, EstadoDesposte, NroOrdenDesposte 
                               FROM ST_Despostadora_RecepcionesDetalle 
                               WHERE NroRecepcion = :nrorec 
                               ORDER BY NroEspecie ASC");
        $stmt->bindValue(':nrorec', trim($nroRecepcion), PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene recepciones disponibles para abrir órdenes de desposte de una especie dada.
     * @return list<array<string, mixed>>
     */
    public static function getAvailableForDeboning(string $codProdCanal): array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT 
                    r.NroRecepcion,
                    r.FechaHora,
                    r.MetodoEntrada,
                    r.CantidadEspecies,
                    CAST(r.PesoTotalNeto AS FLOAT) AS PesoTotalNeto,
                    CAST(r.PesoDisponible AS FLOAT) AS PesoDisponible,
                    CAST(r.CostActCanal AS FLOAT) AS CostActCanal
                FROM ST_Despostadora_Recepciones r
                WHERE r.CodProdCanal = :canal 
                  AND r.PesoDisponible > 0 
                  AND r.Estado IN ('DISPONIBLE', 'EN_PROCESO')
                ORDER BY r.FechaHora ASC"; // FIFO por defecto

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':canal', trim($codProdCanal), PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
