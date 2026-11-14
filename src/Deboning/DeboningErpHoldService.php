<?php
declare(strict_types=1);

namespace Src\Deboning;

use PDO;
use RuntimeException;
use Src\Utils\AuditService;

require_once __DIR__ . '/ConfigService.php';

/**
 * Servicio autónomo encargado de generar y conciliar los Documentos en Espera dobles
 * en el ERP Saint (SAOPEI / SAITEMOPI) para las órdenes de desposte finalizadas:
 * 1. Documento de Descargo de Especie / Canal consumida (Signo = -1, esperado TipoOpI 'P')
 * 2. Documento de Cargo de Cortes producidos (Signo = 1, esperado TipoOpI 'O', aplicando regla DEsComp)
 */
final class DeboningErpHoldService
{
    /**
     * Inserta atómicamente los dos documentos en espera (Descargo y Cargo) en SAOPEI y SAITEMOPI
     * dentro de la transacción activa de cierre de orden de desposte.
     *
     * @param PDO $pdo Conexión PDO con transacción abierta
     * @param string $nroOrdenDesposte Correlativo de la orden (ej. DESP-0000000000000001)
     * @param string $codusua Usuario que cierra la orden
     * @param array<string, mixed> $calcData Datos calculados por YieldCalculator
     * @return array{descargo: string, cargo: string} Correlativos NumeroD generados
     */
    public static function insertDeboningHoldDocuments(
        PDO $pdo,
        string $nroOrdenDesposte,
        string $codusua,
        array $calcData
    ): array {
        $nroOrden = trim($nroOrdenDesposte);
        if ($nroOrden === '') {
            throw new RuntimeException("El correlativo de orden de desposte es inválido.");
        }

        $ord = $calcData['orden'] ?? [];
        $cortes = $calcData['cortes'] ?? [];

        if (empty($cortes)) {
            throw new RuntimeException("No se pueden generar documentos en espera para una orden sin cortes pesados.");
        }

        // Trazabilidades inmutables para SAITEMOPI.Descrip10
        $descrip10Descargo = mb_substr($nroOrden . '-DES', 0, 40);
        $descrip10Cargo    = mb_substr($nroOrden . '-CAR', 0, 40);

        // 1. Validar que no existan documentos en espera previos para esta orden
        $stmtCheck = $pdo->prepare("SELECT 1 FROM SAITEMOPI WHERE Descrip10 IN (:d1, :d2)");
        $stmtCheck->bindValue(':d1', $descrip10Descargo, PDO::PARAM_STR);
        $stmtCheck->bindValue(':d2', $descrip10Cargo, PDO::PARAM_STR);
        $stmtCheck->execute();
        if ($stmtCheck->fetch()) {
            throw new RuntimeException("Ya existen documentos en espera registrados en el ERP para la orden '{$nroOrden}'.");
        }

        // 2. Formatear NumeroD para SAOPEI respetando el límite estricto de VARCHAR(20)
        // Dado que SAOPEI tiene índice único en (CodSucu, TipoOpI, NumeroD), deben ser distintos.
        $numSolo = (string)preg_replace('/^DESP-?/i', '', $nroOrden);
        $numPadded = str_pad($numSolo, 16, '0', STR_PAD_LEFT);
        $numeroDDescargo = mb_substr('D-' . $numPadded, 0, 20); // 18 caracteres
        $numeroDCargo    = mb_substr('C-' . $numPadded, 0, 20); // 18 caracteres

        $codProdCanal = trim((string)($ord['CodProdCanal'] ?? ''));
        $codUbic = trim((string)($ord['CodUbic'] ?? ''));
        $pesoCanalEntrada = (float)($ord['PesoCanalEntrada'] ?? 0.0);
        $costActCanal = (float)($ord['CostActCanal'] ?? 0.0);
        $costoTotalCanal = round($pesoCanalEntrada * $costActCanal, 4);

        if ($codProdCanal === '' || $codUbic === '' || $pesoCanalEntrada <= 0) {
            throw new RuntimeException("Datos de canal de entrada incompletos para el descargo.");
        }

        // 3. Consultar datos y banderas de la especie canal en SAPROD
        $stmtCanal = $pdo->prepare("SELECT CodProd, Descrip, Descrip2, Descrip3, Refere, CostAct,
                                           ISNULL(EsPesa, 0) AS EsPesa, ISNULL(EsExento, 1) AS EsExento,
                                           ISNULL(DEsLote, 0) AS DEsLote, ISNULL(DEsSeri, 0) AS DEsSeri
                                    FROM SAPROD 
                                    WHERE CodProd = :codprod");
        $stmtCanal->bindValue(':codprod', $codProdCanal, PDO::PARAM_STR);
        $stmtCanal->execute();
        $prodCanal = $stmtCanal->fetch(PDO::FETCH_ASSOC);

        if (!$prodCanal) {
            throw new RuntimeException("La especie canal '{$codProdCanal}' no existe en SAPROD.");
        }

        // 4. Nombre de usuario y configuraciones operativas
        $stmtUser = $pdo->prepare("SELECT Descrip FROM SSUSRS WHERE CodUsua = :codusua");
        $stmtUser->bindValue(':codusua', $codusua, PDO::PARAM_STR);
        $stmtUser->execute();
        $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $nombreUsuario = $userRow ? mb_substr(trim((string)$userRow['Descrip']), 0, 40) : mb_substr($codusua, 0, 40);

        $codEsta = mb_substr(trim(ConfigService::getConfig('ERP_CODESTA_DEFAULT', 'DESPOSTE')), 0, 10);
        $codOperDescargo = mb_substr(trim(ConfigService::getConfig('ERP_CODOPER_DESCARGO_DESPOSTE', 'PRO001')), 0, 10);
        $codOperCargo = mb_substr(trim(ConfigService::getConfig('ERP_CODOPER_CARGO_DESPOSTE', 'PRO002')), 0, 10);
        $codUsuaClean = mb_substr(trim($codusua), 0, 10);
        $codUbicClean = mb_substr(trim($codUbic), 0, 10);

        // Preparamos sentencias reutilizables
        $sqlSaopei = "INSERT INTO SAOPEI (
            CodSucu, TipoOpI, NumeroD, CodEsta, CodUsua, CodUbic, CodUbic2, Signo,
            FechaT, OTipo, ONumero, Autori, Respon, UsoMat, OrdenC, Monto,
            FechaE, FechaV, CodOper, UsoInterno, CodClie, NroTurno,
            Notas1, Notas2, Notas3, Notas4, Notas5, Notas6, Notas7, Notas8, Notas9, Notas10, FACTOR
        ) VALUES (
            '00000', 'R', :numerod, :codesta, :codusua, :codubic, NULL, :signo,
            GETDATE(), NULL, NULL, :autori, :respon, NULL, :ordenc, :monto,
            CAST(GETDATE() AS DATE), NULL, :codoper, 0, NULL, 0,
            NULL, NULL, NULL, NULL, :notas5, NULL, NULL, NULL, NULL, :notas10, 1
        )";
        $stmtSaopei = $pdo->prepare($sqlSaopei);

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
            :desc1, :desc2, :desc3, :desc4, :desc5, :desc6, :desc7, :desc8,
            :desc9, :desc10, :refere, :signo, 0, 0,
            0, 0, :cantidad, 0,
            0, 0, 0, 0,
            0, 0, 0, 0,
            :costo, 0, NULL, :totalitem,
            0, :nrolote, 0, 0,
            CAST(GETDATE() AS DATE), CAST(GETDATE() AS DATE), NULL,
            0, 0, 0, :espesa, :esexento, 0, :deslote, :desseri
        )";
        $stmtItem = $pdo->prepare($sqlSaitemopi);

        // =====================================================================
        // 5. INSERTAR DOCUMENTO 1: DESCARGO DE MATERIA PRIMA (Signo = -1)
        // =====================================================================
        $autoriDesc = mb_substr('DOC.DESP.SAL-' . $nroOrden, 0, 40);
        $stmtSaopei->bindValue(':numerod', $numeroDDescargo, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':codesta', $codEsta, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':codusua', $codUsuaClean, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':codubic', $codUbicClean, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':signo', -1, PDO::PARAM_INT);
        $stmtSaopei->bindValue(':autori', $autoriDesc, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':respon', $nombreUsuario, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':ordenc', $nroOrden, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':monto', $costoTotalCanal, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':codoper', $codOperDescargo, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':notas5', $nroOrden, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':notas10', $descrip10Descargo, PDO::PARAM_STR);
        $stmtSaopei->execute();

        // Línea única de descargo de la especie canal en SAITEMOPI
        $desc1Canal = mb_substr((string)($prodCanal['Descrip'] ?? ''), 0, 40);
        $desc2Canal = mb_substr((string)($prodCanal['Descrip2'] ?? ''), 0, 40);
        $desc3Canal = mb_substr((string)($prodCanal['Descrip3'] ?? ''), 0, 40);
        $refCanal   = mb_substr(trim((string)($prodCanal['Refere'] ?: $codProdCanal)), 0, 20);

        $stmtItem->bindValue(':numerod', $numeroDDescargo, PDO::PARAM_STR);
        $stmtItem->bindValue(':nrolinea', 1, PDO::PARAM_INT);
        $stmtItem->bindValue(':coditem', $codProdCanal, PDO::PARAM_STR);
        $stmtItem->bindValue(':codubic', $codUbicClean, PDO::PARAM_STR);
        $stmtItem->bindValue(':desc1', $desc1Canal, PDO::PARAM_STR);
        $stmtItem->bindValue(':desc2', $desc2Canal, PDO::PARAM_STR);
        $stmtItem->bindValue(':desc3', $desc3Canal, PDO::PARAM_STR);
        $stmtItem->bindValue(':desc4', null, PDO::PARAM_NULL);
        $stmtItem->bindValue(':desc5', $nroOrden, PDO::PARAM_STR);
        $stmtItem->bindValue(':desc6', null, PDO::PARAM_NULL);
        $stmtItem->bindValue(':desc7', $codProdCanal, PDO::PARAM_STR);
        $stmtItem->bindValue(':desc8', '1', PDO::PARAM_STR);
        $stmtItem->bindValue(':desc9', 'DESCARGO CANAL', PDO::PARAM_STR);
        $stmtItem->bindValue(':desc10', $descrip10Descargo, PDO::PARAM_STR);
        $stmtItem->bindValue(':refere', $refCanal, PDO::PARAM_STR);
        $stmtItem->bindValue(':signo', -1, PDO::PARAM_INT);
        $stmtItem->bindValue(':cantidad', $pesoCanalEntrada, PDO::PARAM_STR);
        $stmtItem->bindValue(':costo', $costActCanal, PDO::PARAM_STR);
        $stmtItem->bindValue(':totalitem', $costoTotalCanal, PDO::PARAM_STR);
        $stmtItem->bindValue(':nrolote', mb_substr((string)($ord['NroRecepcion'] ?? $nroOrden), 0, 30), PDO::PARAM_STR);
        $stmtItem->bindValue(':espesa', (int)$prodCanal['EsPesa'], PDO::PARAM_INT);
        $stmtItem->bindValue(':esexento', (int)$prodCanal['EsExento'], PDO::PARAM_INT);
        $stmtItem->bindValue(':deslote', (int)$prodCanal['DEsLote'], PDO::PARAM_INT);
        $stmtItem->bindValue(':desseri', (int)$prodCanal['DEsSeri'], PDO::PARAM_INT);
        $stmtItem->execute();

        // =====================================================================
        // 6. INSERTAR DOCUMENTO 2: CARGO DE CORTES TERMINADOS (Signo = 1)
        // =====================================================================
        $totalCostoCortes = (float)($calcData['totales']['TotalCostoRealCortes'] ?? 0.0);
        $autoriCarg = mb_substr('DOC.DESP.ENT-' . $nroOrden, 0, 40);

        $stmtSaopei->bindValue(':numerod', $numeroDCargo, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':codesta', $codEsta, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':codusua', $codUsuaClean, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':codubic', $codUbicClean, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':signo', 1, PDO::PARAM_INT);
        $stmtSaopei->bindValue(':autori', $autoriCarg, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':respon', $nombreUsuario, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':ordenc', $nroOrden, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':monto', $totalCostoCortes, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':codoper', $codOperCargo, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':notas5', $nroOrden, PDO::PARAM_STR);
        $stmtSaopei->bindValue(':notas10', $descrip10Cargo, PDO::PARAM_STR);
        $stmtSaopei->execute();

        // Cache local de información de cortes para evitar consultas repetidas
        $cacheProdCortes = [];
        $stmtCorteProd = $pdo->prepare("SELECT CodProd, Descrip, Descrip2, Descrip3, Refere, CostAct,
                                               ISNULL(DEsComp, 0) AS DEsComp, ISNULL(EsPesa, 0) AS EsPesa,
                                               ISNULL(EsExento, 1) AS EsExento, ISNULL(DEsLote, 0) AS DEsLote,
                                               ISNULL(DEsSeri, 0) AS DEsSeri
                                        FROM SAPROD 
                                        WHERE CodProd = :corte");

        $nroLineaCorte = 1;
        foreach ($cortes as $c) {
            $codProdCorte = trim((string)$c['CodProdCorte']);
            $pesoNetoCorte = (float)$c['PesoNeto'];
            $costoRealUnitario = (float)$c['CostoRealUnitario'];
            $costoRealTotal = (float)$c['CostoRealTotal'];
            $nroLote = mb_substr((string)$c['NroLote'], 0, 30);

            if (!isset($cacheProdCortes[$codProdCorte])) {
                $stmtCorteProd->bindValue(':corte', $codProdCorte, PDO::PARAM_STR);
                $stmtCorteProd->execute();
                $corteInfo = $stmtCorteProd->fetch(PDO::FETCH_ASSOC);
                $cacheProdCortes[$codProdCorte] = $corteInfo ?: [
                    'CodProd' => $codProdCorte, 'Descrip' => $c['NombreCorte'] ?? '', 'Descrip2' => '', 'Descrip3' => '',
                    'Refere' => $codProdCorte, 'DEsComp' => 0, 'EsPesa' => 1, 'EsExento' => 1, 'DEsLote' => 1, 'DEsSeri' => 0
                ];
            }
            $pInfo = $cacheProdCortes[$codProdCorte];
            $esCompuesto = ((int)$pInfo['DEsComp'] === 1);

            if ($esCompuesto) {
                // REGLA DEsComp = 1: Se carga a la especie padre en fila separada
                // y se preserva el código del corte en Descrip4 y nombre (máx 40) en Descrip6
                $itemCodItem  = $codProdCanal;
                $itemDesc1    = $desc1Canal;
                $itemDesc2    = $desc2Canal;
                $itemDesc3    = $desc3Canal;
                $itemDesc4    = mb_substr($codProdCorte, 0, 40);
                $itemDesc6    = mb_substr(trim((string)$pInfo['Descrip']), 0, 40);
                $itemDesc9    = 'CORTE COMPUESTO';
                $itemRefere   = $refCanal;
                $itemEsPesa   = (int)$prodCanal['EsPesa'];
                $itemEsExento = (int)$prodCanal['EsExento'];
                $itemDesLote  = (int)$prodCanal['DEsLote'];
                $itemDesSeri  = (int)$prodCanal['DEsSeri'];
            } else {
                // REGLA DEsComp = 0: Se carga directamente al código del corte
                $itemCodItem  = $codProdCorte;
                $itemDesc1    = mb_substr((string)$pInfo['Descrip'], 0, 40);
                $itemDesc2    = mb_substr((string)$pInfo['Descrip2'], 0, 40);
                $itemDesc3    = mb_substr((string)$pInfo['Descrip3'], 0, 40);
                $itemDesc4    = null;
                $itemDesc6    = null;
                $itemDesc9    = 'CORTE INDIVIDUAL';
                $itemRefere   = mb_substr(trim((string)($pInfo['Refere'] ?: $codProdCorte)), 0, 20);
                $itemEsPesa   = (int)$pInfo['EsPesa'];
                $itemEsExento = (int)$pInfo['EsExento'];
                $itemDesLote  = (int)$pInfo['DEsLote'];
                $itemDesSeri  = (int)$pInfo['DEsSeri'];
            }

            $stmtItem->bindValue(':numerod', $numeroDCargo, PDO::PARAM_STR);
            $stmtItem->bindValue(':nrolinea', $nroLineaCorte, PDO::PARAM_INT);
            $stmtItem->bindValue(':coditem', $itemCodItem, PDO::PARAM_STR);
            $stmtItem->bindValue(':codubic', $codUbicClean, PDO::PARAM_STR);
            $stmtItem->bindValue(':desc1', $itemDesc1, PDO::PARAM_STR);
            $stmtItem->bindValue(':desc2', $itemDesc2, PDO::PARAM_STR);
            $stmtItem->bindValue(':desc3', $itemDesc3, PDO::PARAM_STR);
            $stmtItem->bindValue(':desc4', $itemDesc4, $itemDesc4 !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmtItem->bindValue(':desc5', $nroOrden, PDO::PARAM_STR);
            $stmtItem->bindValue(':desc6', $itemDesc6, $itemDesc6 !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmtItem->bindValue(':desc7', $itemCodItem, PDO::PARAM_STR);
            $stmtItem->bindValue(':desc8', (string)$nroLineaCorte, PDO::PARAM_STR);
            $stmtItem->bindValue(':desc9', $itemDesc9, PDO::PARAM_STR);
            $stmtItem->bindValue(':desc10', $descrip10Cargo, PDO::PARAM_STR);
            $stmtItem->bindValue(':refere', $itemRefere, PDO::PARAM_STR);
            $stmtItem->bindValue(':signo', 1, PDO::PARAM_INT);
            $stmtItem->bindValue(':cantidad', $pesoNetoCorte, PDO::PARAM_STR);
            $stmtItem->bindValue(':costo', $costoRealUnitario, PDO::PARAM_STR);
            $stmtItem->bindValue(':totalitem', $costoRealTotal, PDO::PARAM_STR);
            $stmtItem->bindValue(':nrolote', $nroLote, PDO::PARAM_STR);
            $stmtItem->bindValue(':espesa', $itemEsPesa, PDO::PARAM_INT);
            $stmtItem->bindValue(':esexento', $itemEsExento, PDO::PARAM_INT);
            $stmtItem->bindValue(':deslote', $itemDesLote, PDO::PARAM_INT);
            $stmtItem->bindValue(':desseri', $itemDesSeri, PDO::PARAM_INT);
            $stmtItem->execute();

            $nroLineaCorte++;
        }

        // 7. Actualizar banderas en ST_Despostadora_Despostes
        $stmtUpdDesp = $pdo->prepare("UPDATE ST_Despostadora_Despostes
            SET DocDescargoEnviado = 1,
                DocDescargoNumeroD = :num_desc,
                DocCargoEnviado = 1,
                DocCargoNumeroD = :num_carg,
                EstadoCruceERP = 'AMBOS_EN_ESPERA'
            WHERE NroOrdenDesposte = :nro");
        $stmtUpdDesp->bindValue(':num_desc', $numeroDDescargo, PDO::PARAM_STR);
        $stmtUpdDesp->bindValue(':num_carg', $numeroDCargo, PDO::PARAM_STR);
        $stmtUpdDesp->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
        $stmtUpdDesp->execute();

        return [
            'descargo' => $numeroDDescargo,
            'cargo' => $numeroDCargo
        ];
    }

    /**
     * Sincroniza y diagnostica el estado del cruce de ambos documentos en SAITEMOPI
     * para una o todas las órdenes de desposte finalizadas.
     *
     * @param PDO $pdo
     * @param string|null $nroOrden Si se pasa, sincroniza solo esa orden; si es null, todas las finalizadas.
     * @return int Cantidad de registros sincronizados
     */
    public static function syncDeboningCruceStatus(PDO $pdo, ?string $nroOrden = null): int
    {
        $sql = "SELECT 
                    d.NroOrdenDesposte,
                    d.DocDescargoNumeroD,
                    d.DocCargoNumeroD,
                    d.DocDescargoProcesado,
                    d.DocCargoProcesado,
                    d.EstadoCruceERP,
                    descargo.NumeroD AS NumeroDDescargoActual,
                    descargo.TipoOpI AS TipoOpIDescargoActual,
                    descargo.FechaE AS FechaEDescargoActual,
                    cargo.NumeroD AS NumeroDCargoActual,
                    cargo.TipoOpI AS TipoOpICargoActual,
                    cargo.FechaE AS FechaECargoActual
                FROM ST_Despostadora_Despostes d
                LEFT JOIN (
                    SELECT Descrip10, MAX(NumeroD) AS NumeroD, MAX(TipoOpI) AS TipoOpI, MAX(FechaE) AS FechaE
                    FROM SAITEMOPI
                    WHERE Descrip10 LIKE 'DESP-%-DES'
                    GROUP BY Descrip10
                ) descargo ON descargo.Descrip10 = (d.NroOrdenDesposte + '-DES')
                LEFT JOIN (
                    SELECT Descrip10, MAX(NumeroD) AS NumeroD, MAX(TipoOpI) AS TipoOpI, MAX(FechaE) AS FechaE
                    FROM SAITEMOPI
                    WHERE Descrip10 LIKE 'DESP-%-CAR'
                    GROUP BY Descrip10
                ) cargo ON cargo.Descrip10 = (d.NroOrdenDesposte + '-CAR')
                WHERE d.Estado = 'FINALIZADO'
                  AND (d.DocDescargoEnviado = 1 OR d.DocCargoEnviado = 1)";

        if ($nroOrden !== null && trim($nroOrden) !== '') {
            $sql .= " AND d.NroOrdenDesposte = :nro";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':nro', trim($nroOrden), PDO::PARAM_STR);
        } else {
            $stmt = $pdo->prepare($sql);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return 0;
        }

        $stmtUpd = $pdo->prepare("UPDATE ST_Despostadora_Despostes
            SET DocDescargoProcesado = :proc_desc,
                DocDescargoNumeroDOficial = :num_desc_ofic,
                DocDescargoTipoOpIFinal = :tipo_desc_ofic,
                FechaDocDescargoProcesado = :f_desc_ofic,
                DocCargoProcesado = :proc_carg,
                DocCargoNumeroDOficial = :num_carg_ofic,
                DocCargoTipoOpIFinal = :tipo_carg_ofic,
                FechaDocCargoProcesado = :f_carg_ofic,
                EstadoCruceERP = :est_cruce
            WHERE NroOrdenDesposte = :nro");

        $updatedCount = 0;

        foreach ($rows as $r) {
            $tipoDesc = strtoupper(trim((string)($r['TipoOpIDescargoActual'] ?? '')));
            $tipoCarg = strtoupper(trim((string)($r['TipoOpICargoActual'] ?? '')));

            $numDescOfic = trim((string)($r['NumeroDDescargoActual'] ?? ''));
            $numCargOfic = trim((string)($r['NumeroDCargoActual'] ?? ''));

            $fDescOfic = $r['FechaEDescargoActual'] ?? null;
            $fCargOfic = $r['FechaECargoActual'] ?? null;

            // 1. Diagnóstico de Descargo
            $descargoOk = false;
            $descargoErr = false;
            if ($tipoDesc === 'P') {
                $descargoOk = true;
            } elseif ($tipoDesc !== 'R' && $tipoDesc !== '') {
                $descargoErr = true;
            }

            // 2. Diagnóstico de Cargo
            $cargoOk = false;
            $cargoErr = false;
            if ($tipoCarg === 'O') {
                $cargoOk = true;
            } elseif ($tipoCarg !== 'R' && $tipoCarg !== '') {
                $cargoErr = true;
            }

            // 3. Determinación del Estado Consolidado del Cruce
            if ($descargoErr && $cargoErr) {
                $estadoCruce = 'ERROR_AMBOS_ERP';
            } elseif ($descargoErr) {
                $estadoCruce = 'ERROR_DESCARGO_ERP';
            } elseif ($cargoErr) {
                $estadoCruce = 'ERROR_CARGO_ERP';
            } elseif ($descargoOk && $cargoOk) {
                $estadoCruce = 'TOTALMENTE_PROCESADO';
            } elseif ($descargoOk && !$cargoOk) {
                $estadoCruce = 'DESCARGO_OK_CARGO_PEND';
            } elseif (!$descargoOk && $cargoOk) {
                $estadoCruce = 'CARGO_OK_DESCARGO_PEND';
            } else {
                $estadoCruce = 'AMBOS_EN_ESPERA';
            }

            // Solo se fijan número oficial, tipo final y fecha si la operación fue procesada en Saint o si tiene error
            $guardarDescOfic = ($descargoOk || $descargoErr);
            $guardarCargOfic = ($cargoOk || $cargoErr);

            $stmtUpd->bindValue(':proc_desc', $descargoOk ? 1 : 0, PDO::PARAM_INT);
            $stmtUpd->bindValue(':num_desc_ofic', ($guardarDescOfic && $numDescOfic !== '') ? $numDescOfic : null, ($guardarDescOfic && $numDescOfic !== '') ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmtUpd->bindValue(':tipo_desc_ofic', $guardarDescOfic ? ($tipoDesc !== '' ? $tipoDesc : null) : null, ($guardarDescOfic && $tipoDesc !== '') ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmtUpd->bindValue(':f_desc_ofic', $guardarDescOfic ? $fDescOfic : null, ($guardarDescOfic && $fDescOfic !== null) ? PDO::PARAM_STR : PDO::PARAM_NULL);

            $stmtUpd->bindValue(':proc_carg', $cargoOk ? 1 : 0, PDO::PARAM_INT);
            $stmtUpd->bindValue(':num_carg_ofic', ($guardarCargOfic && $numCargOfic !== '') ? $numCargOfic : null, ($guardarCargOfic && $numCargOfic !== '') ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmtUpd->bindValue(':tipo_carg_ofic', $guardarCargOfic ? ($tipoCarg !== '' ? $tipoCarg : null) : null, ($guardarCargOfic && $tipoCarg !== '') ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmtUpd->bindValue(':f_carg_ofic', $guardarCargOfic ? $fCargOfic : null, ($guardarCargOfic && $fCargOfic !== null) ? PDO::PARAM_STR : PDO::PARAM_NULL);

            $stmtUpd->bindValue(':est_cruce', $estadoCruce, PDO::PARAM_STR);
            $stmtUpd->bindValue(':nro', $r['NroOrdenDesposte'], PDO::PARAM_STR);
            $stmtUpd->execute();

            $updatedCount++;
        }

        return $updatedCount;
    }

    /**
     * Consulta el estado del cruce de ambos documentos para una orden de desposte.
     *
     * @return array<string, mixed>|null
     */
    public static function getDeboningCruceStatus(PDO $pdo, string $nroOrden): ?array
    {
        $stmt = $pdo->prepare("SELECT 
                d.NroOrdenDesposte, d.Estado, d.EstadoCruceERP,
                d.DocDescargoEnviado, d.DocDescargoProcesado, d.DocDescargoNumeroD, d.DocDescargoNumeroDOficial, d.DocDescargoTipoOpIFinal, d.FechaDocDescargoProcesado,
                d.DocCargoEnviado, d.DocCargoProcesado, d.DocCargoNumeroD, d.DocCargoNumeroDOficial, d.DocCargoTipoOpIFinal, d.FechaDocCargoProcesado
            FROM ST_Despostadora_Despostes d
            WHERE d.NroOrdenDesposte = :nro");
        $stmt->bindValue(':nro', trim($nroOrden), PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
