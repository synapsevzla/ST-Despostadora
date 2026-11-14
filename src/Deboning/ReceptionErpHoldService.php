<?php
declare(strict_types=1);

namespace Src\Deboning;

use PDO;
use RuntimeException;
use Src\Utils\AuditService;

/**
 * Servicio encargado de generar y auditar Documentos en Espera en el ERP Saint (SAOPEI / SAITEMOPI)
 * para los cargos de inventario derivados de la Recepción de Especies en Canal.
 */
final class ReceptionErpHoldService
{
    /**
     * Inserta atómicamente el documento en espera en SAOPEI y SAITEMOPI
     * dentro de la transacción activa de recepción.
     *
     * @param PDO $pdo Conexión PDO con transacción abierta
     * @param string $nroRecepcion Correlativo de recepción (ej. REC-0000000000000001)
     * @param string $codusua Usuario que realiza la operación
     * @param array<string, mixed> $recData Datos consolidados de la recepción
     * @param list<float> $pesosIndividuales Listado de pesos para PESO_INDIVIDUAL
     * @return string NumeroD del documento en espera generado
     */
    public static function insertReceptionHoldDocument(
        PDO $pdo,
        string $nroRecepcion,
        string $codusua,
        array $recData,
        array $pesosIndividuales = []
    ): string {
        $nroRecepcion = trim($nroRecepcion);
        if ($nroRecepcion === '') {
            throw new RuntimeException("El correlativo de recepción es inválido.");
        }

        // 1. Validar que no exista ya un documento en espera para esta recepción (Unicidad estricta)
        $stmtCheck = $pdo->prepare("SELECT 1 FROM SAOPEI WHERE Notas10 = :nro1 OR NumeroD = :nro2");
        $stmtCheck->bindValue(':nro1', $nroRecepcion, PDO::PARAM_STR);
        $stmtCheck->bindValue(':nro2', $nroRecepcion, PDO::PARAM_STR);
        $stmtCheck->execute();
        if ($stmtCheck->fetch()) {
            throw new RuntimeException("Ya existe un documento en espera registrado en el ERP para la recepción '{$nroRecepcion}'.");
        }

        // 2. Extraer y validar datos requeridos
        $codProdCanal = trim((string)($recData['CodProdCanal'] ?? ''));
        $codUbic = trim((string)($recData['CodUbic'] ?? ''));
        $metodoEntrada = strtoupper(trim((string)($recData['MetodoEntrada'] ?? 'SOLO_PESO')));
        $pesoTotalNeto = (float)($recData['PesoTotalNeto'] ?? 0.0);
        $costoTotalNeto = (float)($recData['CostoTotalNeto'] ?? 0.0);
        $cantidadEspecies = (int)($recData['CantidadEspecies'] ?? 1);

        if ($codProdCanal === '') {
            throw new RuntimeException("Código de producto/canal vacío para la recepción.");
        }
        if ($codUbic === '') {
            throw new RuntimeException("Depósito operativo no especificado.");
        }
        if ($pesoTotalNeto <= 0) {
            throw new RuntimeException("El peso total neto debe ser superior a cero.");
        }

        // 3. Consultar datos y banderas del producto en SAPROD
        $stmtProd = $pdo->prepare("SELECT CodProd, Descrip, Descrip2, Descrip3, Refere, 
                                          CAST(ISNULL(CostAct, 0) AS FLOAT) AS CostAct,
                                          ISNULL(EsPesa, 0) AS EsPesa,
                                          ISNULL(EsExento, 1) AS EsExento,
                                          ISNULL(DEsLote, 0) AS DEsLote,
                                          ISNULL(DEsSeri, 0) AS DEsSeri
                                   FROM SAPROD 
                                   WHERE CodProd = :codprod");
        $stmtProd->bindValue(':codprod', $codProdCanal, PDO::PARAM_STR);
        $stmtProd->execute();
        $prod = $stmtProd->fetch(PDO::FETCH_ASSOC);

        if (!$prod) {
            throw new RuntimeException("El producto '{$codProdCanal}' no existe en el catálogo SAPROD de Saint.");
        }

        $costAct = (float)$prod['CostAct'];
        if ($costoTotalNeto <= 0) {
            $costoTotalNeto = round($pesoTotalNeto * $costAct, 4);
        }

        // 4. Obtener nombre del usuario desde SSUSRS
        $stmtUser = $pdo->prepare("SELECT Descrip FROM SSUSRS WHERE CodUsua = :codusua");
        $stmtUser->bindValue(':codusua', $codusua, PDO::PARAM_STR);
        $stmtUser->execute();
        $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $nombreUsuario = $userRow ? trim((string)$userRow['Descrip']) : $codusua;
        if (mb_strlen($nombreUsuario) > 40) {
            $nombreUsuario = mb_substr($nombreUsuario, 0, 40);
        }

        // 5. Configuraciones operativas de Saint ERP
        $codOper = ConfigService::getConfig('ERP_CODOPER_RECEPCION', 'CMP');
        $codEsta = ConfigService::getConfig('ERP_CODESTA_DEFAULT', 'DESPOSTE');
        $autoriPrefijo = ConfigService::getConfig('ERP_AUTORI_PREFIJO', 'DOC.ESP-');

        $codOperClean = mb_substr(trim($codOper), 0, 10);
        $codEstaClean = mb_substr(trim($codEsta), 0, 10);
        $codUsuaClean = mb_substr(trim($codusua), 0, 10);
        $codUbicClean = mb_substr(trim($codUbic), 0, 10);
        $autoriDoc = mb_substr(trim($autoriPrefijo) . $nroRecepcion, 0, 40);

        // 6. INSERTAR CABECERA SAOPEI (Cargo de inventario en espera, Signo = 1)
        $sqlSaopei = "INSERT INTO SAOPEI (
            CodSucu, TipoOpI, NumeroD, CodEsta, CodUsua, CodUbic, CodUbic2, Signo,
            FechaT, OTipo, ONumero, Autori, Respon, UsoMat, OrdenC, Monto,
            FechaE, FechaV, CodOper, UsoInterno, CodClie, NroTurno,
            Notas1, Notas2, Notas3, Notas4, Notas5, Notas6, Notas7, Notas8, Notas9, Notas10, FACTOR
        ) VALUES (
            '00000', 'R', :numerod, :codesta, :codusua, :codubic, NULL, 1,
            GETDATE(), NULL, NULL, :autori, :respon, NULL, :ordenc, :monto,
            CAST(GETDATE() AS DATE), NULL, :codoper, 0, NULL, 0,
            NULL, NULL, NULL, NULL, :notas5, NULL, NULL, NULL, NULL, :notas10, 1
        )";

        $stmtSaopei = $pdo->prepare($sqlSaopei);
        $stmtSaopei->bindValue(':numerod', $nroRecepcion, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':codesta', $codEstaClean, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':codusua', $codUsuaClean, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':codubic', $codUbicClean, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':autori', $autoriDoc, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':respon', $nombreUsuario, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':ordenc', $nroRecepcion, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':monto', $costoTotalNeto, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':codoper', $codOperClean, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':notas5', $nroRecepcion, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':notas10', $nroRecepcion, PDO::PARAM_STR); // CLAVE DE TRAZABILIDAD
        $stmtSaopei->execute();

        // 7. PREPARAR SENTENCIA DE DETALLE SAITEMOPI
        $sqlSaitemopi = "INSERT INTO SAITEMOPI (
            CodSucu, TipoOpI, NumeroD, NroLinea, NroLineaC, CodItem, CodUbic, CodUbic2,
            Descrip1, Descrip2, Descrip3, Descrip4, Descrip5, Descrip6, Descrip7, Descrip8,
            Descrip9, Descrip10, Refere, Signo, Tara, Factor,
            CantidadD, CantidadT, Cantidad, CantidadO,
            ExistAntU, ExistAnt, ExistAntU2, ExistAnt2,
            CantidadC, CantidadU, CantidadA, CantidadUA,
            Costo, Descto, CodMeca, TotalItem,
            NroUnicoL, NroLote, Precio, PrecioU,
            FechaE, FechaL, FechaV,
            EsServ, EsUnid, EsFreeP, EsPesa, EsExento, UsaServ, DEsLote, DEsSeri
        ) VALUES (
            '00000', 'R', :numerod, :nrolinea, 0, :coditem, :codubic, NULL,
            :desc1, :desc2, :desc3, NULL, :desc5, NULL, :desc7, :desc8,
            :desc9, :desc10, :refere, 1, 0, 0,
            0, 0, :cantidad, 0,
            0, 0, 0, 0,
            0, 0, 0, 0,
            :costo, 0, NULL, :totalitem,
            0, :nrolote, 0, 0,
            CAST(GETDATE() AS DATE), CAST(GETDATE() AS DATE), NULL,
            0, 0, 0, :espesa, :esexento, 0, :deslote, :desseri
        )";

        $stmtItem = $pdo->prepare($sqlSaitemopi);

        $desc1 = mb_substr((string)($prod['Descrip'] ?? ''), 0, 40);
        $desc2 = mb_substr((string)($prod['Descrip2'] ?? ''), 0, 40);
        $desc3 = mb_substr((string)($prod['Descrip3'] ?? ''), 0, 40);
        $refere = mb_substr(trim((string)($prod['Refere'] ?: $codProdCanal)), 0, 20);

        // 8. INSERTAR ITEMS SEGÚN EL MÉTODO DE RECEPCIÓN
        if ($metodoEntrada === 'PESO_INDIVIDUAL' && !empty($pesosIndividuales)) {
            // Método 3: Pieza por Pieza (Pesaje Separado) -> 1 fila por res
            $nroLinea = 1;
            $totalReses = count($pesosIndividuales);
            foreach ($pesosIndividuales as $pesoRes) {
                $pesoResFloat = (float)$pesoRes;
                if ($pesoResFloat <= 0) {
                    continue;
                }
                $totalItemRes = round($pesoResFloat * $costAct, 4);
                $descrip9Text = "RES {$nroLinea} DE {$totalReses}";

                self::bindItemParams(
                    $stmtItem,
                    $nroRecepcion,
                    $nroLinea,
                    $codProdCanal,
                    $codUbicClean,
                    $desc1,
                    $desc2,
                    $desc3,
                    $descrip9Text,
                    $nroRecepcion, // Descrip10 = NroRecepcion
                    $refere,
                    $pesoResFloat,
                    $costAct,
                    $totalItemRes,
                    $prod
                );
                $stmtItem->execute();
                $nroLinea++;
            }
        } elseif ($metodoEntrada === 'PESO_CANTIDAD') {
            // Método 2: Peso y Cantidad de Especies -> 1 fila, Descrip9 con conteo de animales
            $totalItem = round($pesoTotalNeto * $costAct, 4);
            $descrip9Text = (string)$cantidadEspecies;

            self::bindItemParams(
                $stmtItem,
                $nroRecepcion,
                1,
                $codProdCanal,
                $codUbicClean,
                $desc1,
                $desc2,
                $desc3,
                $descrip9Text,
                $nroRecepcion, // Descrip10 = NroRecepcion
                $refere,
                $pesoTotalNeto,
                $costAct,
                $totalItem,
                $prod
            );
            $stmtItem->execute();
        } else {
            // Método 1: Solo Peso -> 1 fila, Descrip9 informativo o vacío
            $totalItem = round($pesoTotalNeto * $costAct, 4);
            $descrip9Text = 'SOLO PESO';

            self::bindItemParams(
                $stmtItem,
                $nroRecepcion,
                1,
                $codProdCanal,
                $codUbicClean,
                $desc1,
                $desc2,
                $desc3,
                $descrip9Text,
                $nroRecepcion, // Descrip10 = NroRecepcion
                $refere,
                $pesoTotalNeto,
                $costAct,
                $totalItem,
                $prod
            );
            $stmtItem->execute();
        }

        // 9. Actualizar bandera en ST_Despostadora_Recepciones
        $stmtUpdRec = $pdo->prepare("UPDATE ST_Despostadora_Recepciones 
                                     SET DocEsperaEnviado = 1, 
                                         DocEsperaNumeroD = :numerod, 
                                         FechaDocEspera = SYSUTCDATETIME() 
                                     WHERE NroRecepcion = :nro");
        $stmtUpdRec->bindValue(':numerod', $nroRecepcion, PDO::PARAM_STR);
        $stmtUpdRec->bindValue(':nro', $nroRecepcion, PDO::PARAM_STR);
        $stmtUpdRec->execute();

        return $nroRecepcion;
    }

    /**
     * Enlaza parámetros de forma estricta para una fila de SAITEMOPI.
     *
     * @param \PDOStatement $stmt
     * @param array<string, mixed> $prod
     */
    private static function bindItemParams(
        \PDOStatement $stmt,
        string $nroRecepcion,
        int $nroLinea,
        string $codItem,
        string $codUbic,
        string $desc1,
        string $desc2,
        string $desc3,
        string $descrip9,
        string $descrip10,
        string $refere,
        float $cantidad,
        float $costo,
        float $totalItem,
        array $prod
    ): void {
        $stmt->bindValue(':numerod', $nroRecepcion, PDO::PARAM_STR);
        $stmt->bindValue(':nrolinea', $nroLinea, PDO::PARAM_INT);
        $stmt->bindValue(':coditem', $codItem, PDO::PARAM_STR);
        $stmt->bindValue(':codubic', $codUbic, PDO::PARAM_STR);
        $stmt->bindValue(':desc1', $desc1, PDO::PARAM_STR);
        $stmt->bindValue(':desc2', $desc2, PDO::PARAM_STR);
        $stmt->bindValue(':desc3', $desc3, PDO::PARAM_STR);
        $stmt->bindValue(':desc5', $nroRecepcion, PDO::PARAM_STR);
        $stmt->bindValue(':desc7', $codItem, PDO::PARAM_STR);
        $stmt->bindValue(':desc8', (string)$nroLinea, PDO::PARAM_STR);
        $stmt->bindValue(':desc9', mb_substr($descrip9, 0, 40), PDO::PARAM_STR);
        $stmt->bindValue(':desc10', mb_substr($descrip10, 0, 40), PDO::PARAM_STR);
        $stmt->bindValue(':refere', $refere, PDO::PARAM_STR);
        $stmt->bindValue(':cantidad', $cantidad, PDO::PARAM_STR);
        $stmt->bindValue(':costo', $costo, PDO::PARAM_STR);
        $stmt->bindValue(':totalitem', $totalItem, PDO::PARAM_STR);
        $stmt->bindValue(':nrolote', $nroRecepcion, PDO::PARAM_STR);
        $stmt->bindValue(':espesa', (int)$prod['EsPesa'], PDO::PARAM_INT);
        $stmt->bindValue(':esexento', (int)$prod['EsExento'], PDO::PARAM_INT);
        $stmt->bindValue(':deslote', (int)$prod['DEsLote'], PDO::PARAM_INT);
        $stmt->bindValue(':desseri', (int)$prod['DEsSeri'], PDO::PARAM_INT);
    }

    /**
     * Sincroniza y marca como procesadas en ST_Despostadora_Recepciones aquellas recepciones
     * cuyo documento en espera en SAITEMOPI ya cambió de letra (TipoOpI <> 'R').
     *
     * @param PDO $pdo
     * @param string|null $nroRecepcion Si se pasa, sincroniza solo esa recepción; si es null, todas las pendientes.
     * @return int Cantidad de registros marcados como procesados
     */
    public static function syncProcessedStatus(PDO $pdo, ?string $nroRecepcion = null): int
    {
        $sql = "UPDATE r
                SET r.DocEsperaProcesado = 1,
                    r.DocEsperaNumeroDOficial = erp.NumeroD,
                    r.DocEsperaTipoOpIFinal = erp.TipoOpI,
                    r.FechaDocEsperaProcesado = erp.FechaE
                FROM ST_Despostadora_Recepciones r
                INNER JOIN (
                    SELECT Descrip10, 
                           MAX(NumeroD) AS NumeroD, 
                           MAX(TipoOpI) AS TipoOpI, 
                           MAX(FechaE) AS FechaE
                    FROM SAITEMOPI
                    WHERE Descrip10 LIKE 'REC-%'
                      AND TipoOpI <> 'R'
                    GROUP BY Descrip10
                ) erp ON erp.Descrip10 = r.NroRecepcion
                WHERE r.DocEsperaEnviado = 1 
                  AND r.DocEsperaProcesado = 0";

        if ($nroRecepcion !== null && trim($nroRecepcion) !== '') {
            $sql .= " AND r.NroRecepcion = :nrorec";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':nrorec', trim($nroRecepcion), PDO::PARAM_STR);
        } else {
            $stmt = $pdo->prepare($sql);
        }

        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Consulta el estado del documento en espera en Saint ERP para una recepción dada
     * basándose en la trazabilidad inmutable de SAITEMOPI (Descrip10).
     *
     * @return array<string, mixed>|null
     */
    public static function getHoldDocumentStatus(PDO $pdo, string $nroRecepcion): ?array
    {
        $stmt = $pdo->prepare("SELECT TOP 1 
                                    i.NumeroD, 
                                    i.TipoOpI, 
                                    i.CodUbic, 
                                    i.Signo, 
                                    i.FechaE, 
                                    i.Descrip10,
                                    ISNULL(o.Monto, 0) AS Monto,
                                    ISNULL(o.CodUsua, '') AS CodUsua,
                                    ISNULL(o.Autori, '') AS Autori
                               FROM SAITEMOPI i
                               LEFT JOIN SAOPEI o ON o.CodSucu = i.CodSucu AND o.TipoOpI = i.TipoOpI AND o.NumeroD = i.NumeroD
                               WHERE i.Descrip10 = :nro
                               ORDER BY i.FechaE DESC, i.NumeroD DESC");
        $stmt->bindValue(':nro', trim($nroRecepcion), PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $numeroDActual = trim((string)$row['NumeroD']);
        $tipoOpIActual = strtoupper(trim((string)$row['TipoOpI']));
        $procesadoEnSaint = ($tipoOpIActual !== 'R');

        // Si ya fue procesado en Saint pero aún no estaba marcado en local, sincronizarlo
        if ($procesadoEnSaint) {
            self::syncProcessedStatus($pdo, $nroRecepcion);
        }

        return [
            'NumeroD' => $numeroDActual,
            'TipoOpI' => $tipoOpIActual,
            'Signo' => (int)$row['Signo'],
            'CodUbic' => (string)$row['CodUbic'],
            'Monto' => (float)$row['Monto'],
            'Autori' => (string)$row['Autori'],
            'FechaE' => (string)$row['FechaE'],
            'Descrip10' => (string)$row['Descrip10'],
            'ProcesadoEnSaint' => $procesadoEnSaint,
            'EstadoTexto' => $procesadoEnSaint ? 'PROCESADO_OFICIAL_ERP' : 'EN_ESPERA_ERP'
        ];
    }
}
