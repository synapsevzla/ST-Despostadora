<?php
declare(strict_types=1);

namespace Src\Audit;

use Config\Database;
use PDO;

final class AuditQueryService
{
    public const CRITICAL_ACTIONS = [
        'MODIFICAR_CORRELATIVO', 'ANULAR_ORDEN', 'MODIFICACION_PESO',
        'ELIMINAR_CORTE', 'ACCESO_403_DENEGADO', 'ACCESO_403_API_DENEGADO',
        'BLOQUEO_INTENTOS', 'ALERTA_RENDIMIENTO_ANOMALO'
    ];

    public const WARN_ACTIONS = [
        'LOGIN_FALLIDO', 'LOGIN_NIVEL_DENEGADO', 'MODIFICAR_PARAMETRO',
        'CAMBIAR_ESTADO_MESA', 'ELIMINAR_MESA', 'LIBERAR_STOCK'
    ];

    public static function classifySeverity(string $accion): string
    {
        $acc = strtoupper(trim($accion));
        if (in_array($acc, self::CRITICAL_ACTIONS, true)) {
            return 'CRITICAL';
        }
        if (in_array($acc, self::WARN_ACTIONS, true)) {
            return 'WARN';
        }
        return 'INFO';
    }

    /**
     * @return array<string, int|float>
     */
    public static function getSummaryKpis(?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $pdo = Database::getConnection();
        $params = [];
        $whereAud = "1=1";
        $whereCuts = "1=1";

        if ($fechaDesde !== null && $fechaDesde !== '') {
            $whereAud .= " AND a.FechaHora >= :fdesde_aud";
            $whereCuts .= " AND c.FechaHora >= :fdesde_cuts";
            $params[':fdesde_aud'] = $fechaDesde . ' 00:00:00';
            $params[':fdesde_cuts'] = $fechaDesde . ' 00:00:00';
        }
        if ($fechaHasta !== null && $fechaHasta !== '') {
            $whereAud .= " AND a.FechaHora <= :fhasta_aud";
            $whereCuts .= " AND c.FechaHora <= :fhasta_cuts";
            $params[':fhasta_aud'] = $fechaHasta . ' 23:59:59.999';
            $params[':fhasta_cuts'] = $fechaHasta . ' 23:59:59.999';
        }

        $sqlAud = "SELECT COUNT(*) AS TotalEventos,
            COUNT(CASE WHEN a.Accion = 'ANULAR_ORDEN' THEN 1 END) AS OrdenesAnuladas,
            COUNT(CASE WHEN a.Accion IN ('LOGIN_FALLIDO', 'BLOQUEO_INTENTOS', 'ACCESO_403_DENEGADO', 'ACCESO_403_API_DENEGADO') THEN 1 END) AS EventosSeguridad,
            COUNT(CASE WHEN a.Accion IN ('" . implode("','", self::CRITICAL_ACTIONS) . "') THEN 1 END) AS EventosCriticos
        FROM ST_Despostadora_Auditoria a WHERE {$whereAud}";

        $stmtAud = $pdo->prepare($sqlAud);
        foreach ($params as $k => $v) {
            if (str_contains($k, '_aud')) {
                $stmtAud->bindValue($k, $v, PDO::PARAM_STR);
            }
        }
        $stmtAud->execute();
        $rowAud = $stmtAud->fetch(PDO::FETCH_ASSOC) ?: [];

        $sqlCuts = "SELECT COUNT(*) AS PesajesModificados,
            CAST(ISNULL(SUM(ABS(c.DiferenciaPeso)), 0) AS FLOAT) AS DeltaKilosTotal
        FROM ST_Despostadora_DespostesCortesLog c WHERE c.TipoAccion = 'MODIFICACION_PESO' AND {$whereCuts}";

        $stmtCuts = $pdo->prepare($sqlCuts);
        foreach ($params as $k => $v) {
            if (str_contains($k, '_cuts')) {
                $stmtCuts->bindValue($k, $v, PDO::PARAM_STR);
            }
        }
        $stmtCuts->execute();
        $rowCuts = $stmtCuts->fetch(PDO::FETCH_ASSOC) ?: [];

        $sqlDesp = "SELECT COUNT(CASE WHEN d.RendimientoPct < 75.0 OR d.RendimientoPct > 102.0 OR d.MermaPct > 25.0 OR d.MermaPct < 0.0 THEN 1 END) AS DesviacionesRendimiento
        FROM ST_Despostadora_Despostes d WHERE d.Estado IN ('FINALIZADO', 'TERMINADA', 'TERMINADO', 'CERRADA', 'CERRADO')";
        $stmtDesp = $pdo->query($sqlDesp);
        $rowDesp = $stmtDesp ? $stmtDesp->fetch(PDO::FETCH_ASSOC) : [];

        return [
            'total_eventos' => (int)($rowAud['TotalEventos'] ?? 0),
            'pesajes_modificados' => (int)($rowCuts['PesajesModificados'] ?? 0),
            'delta_kilos_modificados' => round((float)($rowCuts['DeltaKilosTotal'] ?? 0.0), 2),
            'ordenes_anuladas' => (int)($rowAud['OrdenesAnuladas'] ?? 0),
            'eventos_seguridad' => (int)($rowAud['EventosSeguridad'] ?? 0),
            'eventos_criticos' => (int)($rowAud['EventosCriticos'] ?? 0),
            'desviaciones_rendimiento' => (int)($rowDesp['DesviacionesRendimiento'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public static function getEvents(array $filters, int $page = 1, int $perPage = 25): array
    {
        $pdo = Database::getConnection();
        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = ["1=1"];
        $params = [];

        if (!empty($filters['modulo'])) {
            $conditions[] = "a.Modulo = :modulo";
            $params[':modulo'] = trim((string)$filters['modulo']);
        }
        if (!empty($filters['accion'])) {
            $conditions[] = "a.Accion = :accion";
            $params[':accion'] = trim((string)$filters['accion']);
        }
        if (!empty($filters['codusua'])) {
            $conditions[] = "a.CodUsua = :codusua";
            $params[':codusua'] = trim((string)$filters['codusua']);
        }
        if (!empty($filters['fecha_desde'])) {
            $conditions[] = "a.FechaHora >= :fecha_desde";
            $params[':fecha_desde'] = trim((string)$filters['fecha_desde']) . ' 00:00:00';
        }
        if (!empty($filters['fecha_hasta'])) {
            $conditions[] = "a.FechaHora <= :fecha_hasta";
            $params[':fecha_hasta'] = trim((string)$filters['fecha_hasta']) . ' 23:59:59.999';
        }
        if (!empty($filters['q'])) {
            $conditions[] = "(a.DocumentoID LIKE :q1 OR a.DetalleJSON LIKE :q2 OR a.IP LIKE :q3 OR a.CodUsua LIKE :q4)";
            $qVal = '%' . trim((string)$filters['q']) . '%';
            $params[':q1'] = $qVal;
            $params[':q2'] = $qVal;
            $params[':q3'] = $qVal;
            $params[':q4'] = $qVal;
        }

        $whereClause = implode(' AND ', $conditions);

        $sql = "SELECT a.ID, a.Modulo, a.DocumentoID, a.Accion, a.CodUsua,
            ISNULL(u.Descrip, a.CodUsua) AS NombreUsuario,
            CONVERT(VARCHAR(19), a.FechaHora, 120) AS FechaHora,
            a.IP, a.DetalleJSON, COUNT(*) OVER() AS TotalRows
        FROM ST_Despostadora_Auditoria a
        LEFT JOIN ssusrs u ON u.CodUsua = a.CodUsua
        WHERE {$whereClause}
        ORDER BY a.FechaHora DESC, a.ID DESC
        OFFSET :offset ROWS FETCH NEXT :per_page ROWS ONLY";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = !empty($rows) ? (int)$rows[0]['TotalRows'] : 0;
        $items = [];
        $filterSeverity = !empty($filters['severidad']) ? strtoupper(trim((string)$filters['severidad'])) : '';

        foreach ($rows as $r) {
            $sev = self::classifySeverity((string)$r['Accion']);
            if ($filterSeverity !== '' && $filterSeverity !== 'TODAS' && $sev !== $filterSeverity) {
                continue;
            }
            $detalle = null;
            if (!empty($r['DetalleJSON'])) {
                $decoded = json_decode((string)$r['DetalleJSON'], true);
                if (is_array($decoded)) {
                    $detalle = $decoded;
                }
            }
            $items[] = [
                'id' => (int)$r['ID'],
                'modulo' => (string)$r['Modulo'],
                'documento_id' => (string)$r['DocumentoID'],
                'accion' => (string)$r['Accion'],
                'codusua' => (string)$r['CodUsua'],
                'nombre_usuario' => (string)$r['NombreUsuario'],
                'fecha_hora' => (string)$r['FechaHora'],
                'ip' => (string)$r['IP'],
                'severidad' => $sev,
                'detalle' => $detalle,
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / max(1, $perPage)),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public static function getCutsLog(array $filters, int $page = 1, int $perPage = 25): array
    {
        $pdo = Database::getConnection();
        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = ["1=1"];
        $params = [];

        if (!empty($filters['nro_orden'])) {
            $conditions[] = "log.NroOrdenDesposte = :nro_orden";
            $params[':nro_orden'] = trim((string)$filters['nro_orden']);
        }
        if (!empty($filters['tipo_accion'])) {
            $conditions[] = "log.TipoAccion = :tipo_accion";
            $params[':tipo_accion'] = trim((string)$filters['tipo_accion']);
        }
        if (!empty($filters['codusua'])) {
            $conditions[] = "log.CodUsua = :codusua";
            $params[':codusua'] = trim((string)$filters['codusua']);
        }
        if (!empty($filters['fecha_desde'])) {
            $conditions[] = "log.FechaHora >= :fecha_desde";
            $params[':fecha_desde'] = trim((string)$filters['fecha_desde']) . ' 00:00:00';
        }
        if (!empty($filters['fecha_hasta'])) {
            $conditions[] = "log.FechaHora <= :fecha_hasta";
            $params[':fecha_hasta'] = trim((string)$filters['fecha_hasta']) . ' 23:59:59.999';
        }
        if (!empty($filters['q'])) {
            $conditions[] = "(log.NroOrdenDesposte LIKE :q1 OR log.NroLote LIKE :q2 OR log.CodProdCorte LIKE :q3 OR log.Motivo LIKE :q4 OR p.Descrip LIKE :q5)";
            $qVal = '%' . trim((string)$filters['q']) . '%';
            $params[':q1'] = $qVal;
            $params[':q2'] = $qVal;
            $params[':q3'] = $qVal;
            $params[':q4'] = $qVal;
            $params[':q5'] = $qVal;
        }

        $whereClause = implode(' AND ', $conditions);

        $sql = "SELECT log.ID, log.NroOrdenDesposte, log.IDDetalle, log.NroLote, log.CodProdCorte,
            log.TipoAccion, CAST(log.PesoAnterior AS FLOAT) AS PesoAnterior,
            CAST(log.PesoNuevo AS FLOAT) AS PesoNuevo, CAST(log.DiferenciaPeso AS FLOAT) AS DiferenciaPeso,
            log.Motivo, log.CodUsua, ISNULL(u.Descrip, log.CodUsua) AS NombreUsuario,
            log.IP, CONVERT(VARCHAR(19), log.FechaHora, 120) AS FechaHora,
            LTRIM(RTRIM(CONCAT(p.Descrip, ' ', p.Descrip2, ' ', p.Descrip3))) AS NombreCorte,
            COUNT(*) OVER() AS TotalRows
        FROM ST_Despostadora_DespostesCortesLog log
        LEFT JOIN SAPROD p ON p.CodProd = log.CodProdCorte
        LEFT JOIN ssusrs u ON u.CodUsua = log.CodUsua
        WHERE {$whereClause}
        ORDER BY log.FechaHora DESC, log.ID DESC
        OFFSET :offset ROWS FETCH NEXT :per_page ROWS ONLY";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = !empty($rows) ? (int)$rows[0]['TotalRows'] : 0;
        $items = [];

        foreach ($rows as $r) {
            $items[] = [
                'id' => (int)$r['ID'],
                'nro_orden' => (string)$r['NroOrdenDesposte'],
                'id_detalle' => (int)$r['IDDetalle'],
                'nro_lote' => (string)$r['NroLote'],
                'codprod_corte' => (string)$r['CodProdCorte'],
                'nombre_corte' => (string)($r['NombreCorte'] ?: $r['CodProdCorte']),
                'tipo_accion' => (string)$r['TipoAccion'],
                'peso_anterior' => $r['PesoAnterior'] !== null ? (float)$r['PesoAnterior'] : null,
                'peso_nuevo' => $r['PesoNuevo'] !== null ? (float)$r['PesoNuevo'] : null,
                'diferencia_peso' => $r['DiferenciaPeso'] !== null ? (float)$r['DiferenciaPeso'] : 0.0,
                'motivo' => (string)($r['Motivo'] ?? ''),
                'codusua' => (string)$r['CodUsua'],
                'nombre_usuario' => (string)$r['NombreUsuario'],
                'ip' => (string)$r['IP'],
                'fecha_hora' => (string)$r['FechaHora'],
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / max(1, $perPage)),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public static function getYieldAnomalies(array $filters, int $page = 1, int $perPage = 25): array
    {
        $pdo = Database::getConnection();
        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = ["d.Estado IN ('FINALIZADO', 'TERMINADA', 'TERMINADO', 'CERRADA', 'CERRADO')"];
        $params = [];

        if (!empty($filters['solo_alertas'])) {
            $conditions[] = "(d.RendimientoPct < 75.0 OR d.RendimientoPct > 102.0 OR d.MermaPct > 25.0 OR d.MermaPct < 0.0)";
        }
        if (!empty($filters['fecha_desde'])) {
            $conditions[] = "d.FechaHoraCierre >= :fecha_desde";
            $params[':fecha_desde'] = trim((string)$filters['fecha_desde']) . ' 00:00:00';
        }
        if (!empty($filters['fecha_hasta'])) {
            $conditions[] = "d.FechaHoraCierre <= :fecha_hasta";
            $params[':fecha_hasta'] = trim((string)$filters['fecha_hasta']) . ' 23:59:59.999';
        }
        if (!empty($filters['q'])) {
            $conditions[] = "(d.NroOrdenDesposte LIKE :q1 OR d.CodProdCanal LIKE :q2 OR d.NroRecepcion LIKE :q3 OR p.Descrip LIKE :q4)";
            $qVal = '%' . trim((string)$filters['q']) . '%';
            $params[':q1'] = $qVal;
            $params[':q2'] = $qVal;
            $params[':q3'] = $qVal;
            $params[':q4'] = $qVal;
        }

        $whereClause = implode(' AND ', $conditions);

        $sql = "SELECT d.NroOrdenDesposte, d.NroRecepcion, d.LineaMesa, d.CodProdCanal,
            LTRIM(RTRIM(CONCAT(p.Descrip, ' ', p.Descrip2, ' ', p.Descrip3))) AS NombreCanal,
            CAST(d.PesoCanalEntrada AS FLOAT) AS PesoCanalEntrada,
            CAST(d.PesoTotalCortes AS FLOAT) AS PesoTotalCortes,
            CAST(d.PesoMerma AS FLOAT) AS PesoMerma,
            CAST(d.RendimientoPct AS FLOAT) AS RendimientoPct,
            CAST(d.MermaPct AS FLOAT) AS MermaPct,
            d.Estado, d.CodUsuaCierre, ISNULL(u.Descrip, d.CodUsuaCierre) AS NombreUsuarioCierre,
            CONVERT(VARCHAR(19), d.FechaHoraCierre, 120) AS FechaHoraCierre,
            d.DocDescargoNumeroD, d.DocCargoNumeroD, COUNT(*) OVER() AS TotalRows
        FROM ST_Despostadora_Despostes d
        LEFT JOIN SAPROD p ON p.CodProd = d.CodProdCanal
        LEFT JOIN ssusrs u ON u.CodUsua = d.CodUsuaCierre
        WHERE {$whereClause}
        ORDER BY d.FechaHoraCierre DESC
        OFFSET :offset ROWS FETCH NEXT :per_page ROWS ONLY";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = !empty($rows) ? (int)$rows[0]['TotalRows'] : 0;
        $items = [];

        foreach ($rows as $r) {
            $rend = (float)$r['RendimientoPct'];
            $merma = (float)$r['MermaPct'];
            $items[] = [
                'nro_orden' => (string)$r['NroOrdenDesposte'],
                'nro_recepcion' => (string)$r['NroRecepcion'],
                'linea_mesa' => (string)$r['LineaMesa'],
                'codprod_canal' => (string)$r['CodProdCanal'],
                'nombre_canal' => (string)($r['NombreCanal'] ?: $r['CodProdCanal']),
                'peso_canal' => (float)$r['PesoCanalEntrada'],
                'peso_cortes' => (float)$r['PesoTotalCortes'],
                'peso_merma' => (float)$r['PesoMerma'],
                'rendimiento_pct' => $rend,
                'merma_pct' => $merma,
                'fecha_cierre' => (string)$r['FechaHoraCierre'],
                'usuario_cierre' => (string)($r['NombreUsuarioCierre'] ?: $r['CodUsuaCierre']),
                'doc_descargo' => (string)($r['DocDescargoNumeroD'] ?? ''),
                'doc_cargo' => (string)($r['DocCargoNumeroD'] ?? ''),
                'alerta_rendimiento' => ($rend < 75.0 || $rend > 102.0),
                'alerta_merma' => ($merma > 25.0 || $merma < 0.0),
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / max(1, $perPage)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getTraceability(string $searchCode): ?array
    {
        $code = trim($searchCode);
        if ($code === '') {
            return null;
        }

        $pdo = Database::getConnection();
        $stmtLote = $pdo->prepare("SELECT TOP 1 NroOrdenDesposte, NroLote FROM ST_Despostadora_DespostesDetalle WHERE NroLote = :code");
        $stmtLote->bindValue(':code', $code, PDO::PARAM_STR);
        $stmtLote->execute();
        $loteInfo = $stmtLote->fetch(PDO::FETCH_ASSOC);

        $nroOrden = '';
        $targetLote = '';

        if ($loteInfo) {
            $nroOrden = (string)$loteInfo['NroOrdenDesposte'];
            $targetLote = (string)$loteInfo['NroLote'];
        } elseif (str_starts_with(strtoupper($code), 'DESP-')) {
            $nroOrden = $code;
        } elseif (str_starts_with(strtoupper($code), 'REC-')) {
            $stmtRecOrd = $pdo->prepare("SELECT TOP 1 NroOrdenDesposte FROM ST_Despostadora_Despostes WHERE NroRecepcion = :code");
            $stmtRecOrd->bindValue(':code', $code, PDO::PARAM_STR);
            $stmtRecOrd->execute();
            $nroOrden = (string)($stmtRecOrd->fetchColumn() ?: '');
        } else {
            $stmtGen = $pdo->prepare("SELECT TOP 1 NroOrdenDesposte FROM ST_Despostadora_Despostes WHERE NroOrdenDesposte = :code1 OR NroRecepcion = :code2");
            $stmtGen->bindValue(':code1', $code, PDO::PARAM_STR);
            $stmtGen->bindValue(':code2', $code, PDO::PARAM_STR);
            $stmtGen->execute();
            $nroOrden = (string)($stmtGen->fetchColumn() ?: '');
        }

        if ($nroOrden === '') {
            return null;
        }

        $stmtOrd = $pdo->prepare("SELECT d.NroOrdenDesposte, d.NroRecepcion, d.LineaMesa, d.CodProdCanal,
            LTRIM(RTRIM(CONCAT(p.Descrip, ' ', p.Descrip2, ' ', p.Descrip3))) AS NombreCanal,
            CAST(d.PesoCanalEntrada AS FLOAT) AS PesoCanalEntrada,
            CAST(d.PesoTotalCortes AS FLOAT) AS PesoTotalCortes,
            CAST(d.PesoMerma AS FLOAT) AS PesoMerma,
            CAST(d.RendimientoPct AS FLOAT) AS RendimientoPct,
            CAST(d.MermaPct AS FLOAT) AS MermaPct,
            d.Estado, d.CodUsua,
            CONVERT(VARCHAR(19), d.FechaHoraInicio, 120) AS FechaHoraInicio,
            CONVERT(VARCHAR(19), d.FechaHoraCierre, 120) AS FechaHoraCierre,
            d.DocDescargoNumeroD, d.DocCargoNumeroD
        FROM ST_Despostadora_Despostes d
        LEFT JOIN SAPROD p ON p.CodProd = d.CodProdCanal
        WHERE d.NroOrdenDesposte = :nro");
        $stmtOrd->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
        $stmtOrd->execute();
        $ord = $stmtOrd->fetch(PDO::FETCH_ASSOC);
        if (!$ord) {
            return null;
        }

        $stmtRec = $pdo->prepare("SELECT r.NroRecepcion, r.CodProdCanal, r.CodUbic, r.MetodoEntrada,
            CAST(r.PesoTotalNeto AS FLOAT) AS PesoTotalNeto, CAST(r.PesoDisponible AS FLOAT) AS PesoDisponible,
            r.Estado, r.CodUsua, CONVERT(VARCHAR(19), r.FechaHora, 120) AS FechaHora,
            r.DocEsperaNumeroD, r.DocEsperaProcesado, r.DocEsperaNumeroDOficial
        FROM ST_Despostadora_Recepciones r WHERE r.NroRecepcion = :nrorec");
        $stmtRec->bindValue(':nrorec', (string)$ord['NroRecepcion'], PDO::PARAM_STR);
        $stmtRec->execute();
        $rec = $stmtRec->fetch(PDO::FETCH_ASSOC);

        $stmtCortes = $pdo->prepare("SELECT det.ID, det.NroItem, det.NroLote, det.CodProdCorte,
            LTRIM(RTRIM(CONCAT(p.Descrip, ' ', p.Descrip2, ' ', p.Descrip3))) AS NombreCorte,
            CAST(det.PesoNeto AS FLOAT) AS PesoNeto, CAST(det.RendimientoCortePct AS FLOAT) AS RendimientoCortePct,
            CONVERT(VARCHAR(10), det.FechaElaboracion, 120) AS FechaElaboracion,
            CONVERT(VARCHAR(10), det.FechaVencimiento, 120) AS FechaVencimiento,
            CONVERT(VARCHAR(19), det.FechaHoraPesaje, 120) AS FechaHoraPesaje
        FROM ST_Despostadora_DespostesDetalle det
        LEFT JOIN SAPROD p ON p.CodProd = det.CodProdCorte
        WHERE det.NroOrdenDesposte = :nro ORDER BY det.NroItem ASC");
        $stmtCortes->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
        $stmtCortes->execute();
        $cortes = $stmtCortes->fetchAll(PDO::FETCH_ASSOC);

        $stmtLog = $pdo->prepare("SELECT log.ID, log.TipoAccion, log.NroLote, log.CodProdCorte,
            CAST(log.PesoAnterior AS FLOAT) AS PesoAnterior, CAST(log.PesoNuevo AS FLOAT) AS PesoNuevo,
            CAST(log.DiferenciaPeso AS FLOAT) AS DiferenciaPeso, log.Motivo, log.CodUsua,
            CONVERT(VARCHAR(19), log.FechaHora, 120) AS FechaHora
        FROM ST_Despostadora_DespostesCortesLog log
        WHERE log.NroOrdenDesposte = :nro ORDER BY log.FechaHora DESC");
        $stmtLog->bindValue(':nro', $nroOrden, PDO::PARAM_STR);
        $stmtLog->execute();
        $logs = $stmtLog->fetchAll(PDO::FETCH_ASSOC);

        return [
            'codigo_buscado' => $code,
            'lote_especifico' => $targetLote,
            'recepcion' => $rec ?: null,
            'desposte' => $ord,
            'cortes' => $cortes,
            'historial_mesa' => $logs,
        ];
    }

    /**
     * @return list<array{codusua: string, descrip: string}>
     */
    public static function getDistinctUsers(): array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT DISTINCT RTRIM(LTRIM(u.CodUsua)) AS CodUsua, RTRIM(LTRIM(u.Descrip)) AS Descrip
                FROM ssusrs u
                WHERE u.CodUsua IN (
                    SELECT DISTINCT CodUsua FROM ST_Despostadora_Auditoria
                    UNION
                    SELECT DISTINCT CodUsua FROM ST_Despostadora_DespostesCortesLog
                ) ORDER BY CodUsua ASC";
        $stmt = $pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * @return list<string>
     */
    public static function getDistinctModules(): array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT DISTINCT Modulo FROM ST_Despostadora_Auditoria ORDER BY Modulo ASC";
        $stmt = $pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : ['CONFIGURACION', 'DESPOSTE', 'RECEPCION', 'SEGURIDAD'];
    }
}
