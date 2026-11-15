<?php
declare(strict_types=1);

namespace Src\Deboning;

use Config\Database;
use PDO;
use Throwable;

final class DashboardService
{
    /**
     * Obtiene los KPIs de órdenes de desposte clasificadas en:
     * - Abiertas (órdenes creadas sin pesajes iniciados o con estado ABIERTA)
     * - En Proceso (órdenes con pesajes activos)
     * - Terminadas (órdenes cerradas / finalizadas)
     *
     * @return array<string, int>
     */
    public static function getOrdersKpis(): array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT
            COUNT(CASE WHEN d.Estado = 'ABIERTA' OR (d.Estado = 'EN_PROCESO' AND ISNULL(d.PesoTotalCortes, 0) = 0) THEN 1 END) AS abiertas,
            COUNT(CASE WHEN d.Estado = 'EN_PROCESO' AND ISNULL(d.PesoTotalCortes, 0) > 0 THEN 1 END) AS en_proceso,
            COUNT(CASE WHEN d.Estado IN ('FINALIZADO', 'TERMINADA', 'TERMINADO', 'CERRADA', 'CERRADO') THEN 1 END) AS terminadas,
            COUNT(CASE WHEN d.Estado = 'ANULADO' THEN 1 END) AS anuladas,
            COUNT(*) AS total
        FROM ST_Despostadora_Despostes d";

        $stmt = $pdo->query($sql);
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        return [
            'abiertas'   => (int)($row['abiertas'] ?? 0),
            'en_proceso' => (int)($row['en_proceso'] ?? 0),
            'terminadas' => (int)($row['terminadas'] ?? 0),
            'anuladas'   => (int)($row['anuladas'] ?? 0),
            'total'      => (int)($row['total'] ?? 0),
        ];
    }

    /**
     * Obtiene el acumulado de kilos despostados para el día actual, la semana y el mes.
     * Basado en la fecha/hora real de pesaje de cada corte en ST_Despostadora_DespostesDetalle.
     *
     * @return array{
     *     dia: float,
     *     semana: float,
     *     mes: float,
     *     cortes_dia: int,
     *     cortes_semana: int,
     *     cortes_mes: int
     * }
     */
    public static function getDebonedWeightKpis(): array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT
            CAST(ISNULL(SUM(CASE WHEN CAST(det.FechaHoraPesaje AS DATE) = CAST(SYSUTCDATETIME() AS DATE) OR CAST(det.FechaHoraPesaje AS DATE) = CAST(GETDATE() AS DATE) THEN det.PesoNeto ELSE 0 END), 0) AS FLOAT) AS KilosDia,
            CAST(ISNULL(SUM(CASE WHEN det.FechaHoraPesaje >= DATEADD(week, DATEDIFF(week, 0, SYSUTCDATETIME()), 0) THEN det.PesoNeto ELSE 0 END), 0) AS FLOAT) AS KilosSemana,
            CAST(ISNULL(SUM(CASE WHEN det.FechaHoraPesaje >= DATEADD(month, DATEDIFF(month, 0, SYSUTCDATETIME()), 0) THEN det.PesoNeto ELSE 0 END), 0) AS FLOAT) AS KilosMes,
            COUNT(CASE WHEN CAST(det.FechaHoraPesaje AS DATE) = CAST(SYSUTCDATETIME() AS DATE) OR CAST(det.FechaHoraPesaje AS DATE) = CAST(GETDATE() AS DATE) THEN 1 END) AS CortesDia,
            COUNT(CASE WHEN det.FechaHoraPesaje >= DATEADD(week, DATEDIFF(week, 0, SYSUTCDATETIME()), 0) THEN 1 END) AS CortesSemana,
            COUNT(CASE WHEN det.FechaHoraPesaje >= DATEADD(month, DATEDIFF(month, 0, SYSUTCDATETIME()), 0) THEN 1 END) AS CortesMes
        FROM ST_Despostadora_DespostesDetalle det";

        $stmt = $pdo->query($sql);
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        return [
            'dia'           => round((float)($row['KilosDia'] ?? 0.0), 2),
            'semana'        => round((float)($row['KilosSemana'] ?? 0.0), 2),
            'mes'           => round((float)($row['KilosMes'] ?? 0.0), 2),
            'cortes_dia'    => (int)($row['CortesDia'] ?? 0),
            'cortes_semana' => (int)($row['CortesSemana'] ?? 0),
            'cortes_mes'    => (int)($row['CortesMes'] ?? 0),
        ];
    }

    /**
     * Obtiene las especies disponibles en stock (en Kilos y Cantidad),
     * desglosadas dinámicamente según sus modos de recepción:
     * - PESO_INDIVIDUAL (Pieza a pieza)
     * - PESO_CANTIDAD (Por lote con conteo)
     * - SOLO_PESO (Peso global)
     *
     * Incluye especies configuradas en ST_Despostadora_EspeciesConfig y recepciones activas.
     *
     * @return array{
     *     especies: list<array<string, mixed>>,
     *     resumen: array{total_especies: int, total_kilos: float, total_piezas: int}
     * }
     */
    public static function getSpeciesAvailabilityKpis(): array
    {
        $pdo = Database::getConnection();

        $sql = "
        WITH RecDisponibles AS (
            SELECT 
                r.NroRecepcion,
                r.CodProdCanal,
                r.MetodoEntrada,
                r.PesoDisponible,
                CASE 
                    WHEN r.MetodoEntrada = 'PESO_INDIVIDUAL' THEN (
                        SELECT COUNT(*) 
                        FROM ST_Despostadora_RecepcionesDetalle rd 
                        WHERE rd.NroRecepcion = r.NroRecepcion AND rd.EstadoDesposte = 'DISPONIBLE'
                    )
                    WHEN r.MetodoEntrada = 'PESO_CANTIDAD' THEN (
                        CASE 
                            WHEN r.PesoDisponible <= 0 THEN 0
                            ELSE CASE 
                                WHEN r.CantidadEspecies - ISNULL((SELECT SUM(d.CantidadProcesada) FROM ST_Despostadora_Despostes d WHERE d.NroRecepcion = r.NroRecepcion AND d.Estado <> 'ANULADO'), 0) < 0 THEN 0
                                ELSE r.CantidadEspecies - ISNULL((SELECT SUM(d.CantidadProcesada) FROM ST_Despostadora_Despostes d WHERE d.NroRecepcion = r.NroRecepcion AND d.Estado <> 'ANULADO'), 0)
                            END
                        END
                    )
                    ELSE r.CantidadEspecies
                END AS CantidadDisponible
            FROM ST_Despostadora_Recepciones r
            WHERE r.PesoDisponible > 0 AND r.Estado IN ('DISPONIBLE', 'EN_PROCESO')
        ),
        ModosAgrupados AS (
            SELECT 
                rd.CodProdCanal,
                rd.MetodoEntrada,
                COUNT(*) AS TotalRecepciones,
                CAST(SUM(rd.PesoDisponible) AS FLOAT) AS PesoDisponibleModo,
                SUM(rd.CantidadDisponible) AS CantidadDisponibleModo
            FROM RecDisponibles rd
            GROUP BY rd.CodProdCanal, rd.MetodoEntrada
        )
        SELECT 
            COALESCE(ec.CodProd, ma.CodProdCanal) AS CodProdCanal,
            LTRIM(RTRIM(CONCAT(p.Descrip, ' ', p.Descrip2, ' ', p.Descrip3))) AS NombreCanal,
            p.Unidad,
            CASE WHEN ec.CodProd IS NOT NULL AND ec.Activo = 1 THEN 1 ELSE 0 END AS EstaConfigurado,
            ma.MetodoEntrada,
            ISNULL(ma.TotalRecepciones, 0) AS TotalRecepciones,
            ISNULL(ma.PesoDisponibleModo, 0) AS PesoDisponibleModo,
            ISNULL(ma.CantidadDisponibleModo, 0) AS CantidadDisponibleModo
        FROM (
            SELECT CodProd, Activo FROM ST_Despostadora_EspeciesConfig WHERE Activo = 1
        ) ec
        FULL OUTER JOIN ModosAgrupados ma ON ma.CodProdCanal = ec.CodProd
        INNER JOIN SAPROD p ON p.CodProd = COALESCE(ec.CodProd, ma.CodProdCanal)
        ORDER BY EstaConfigurado DESC, NombreCanal ASC, ma.MetodoEntrada ASC";

        $stmt = $pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $speciesMap = [];
        $totalGlobalKg = 0.0;
        $totalGlobalPiezas = 0;

        foreach ($rows as $row) {
            $cod = (string)$row['CodProdCanal'];
            if (!isset($speciesMap[$cod])) {
                $speciesMap[$cod] = [
                    'codprod'           => $cod,
                    'nombre'            => (string)$row['NombreCanal'],
                    'unidad'            => (string)($row['Unidad'] ?: 'KG'),
                    'esta_configurado'  => (int)$row['EstaConfigurado'] === 1,
                    'peso_total'        => 0.0,
                    'cantidad_total'    => 0,
                    'total_recepciones' => 0,
                    'modos'             => [],
                ];
            }

            $metodo = $row['MetodoEntrada'] ? (string)$row['MetodoEntrada'] : null;
            $pesoModo = (float)$row['PesoDisponibleModo'];
            $cantModo = (int)$row['CantidadDisponibleModo'];
            $recModo = (int)$row['TotalRecepciones'];

            if ($metodo !== null) {
                $labelMetodo = match ($metodo) {
                    'PESO_INDIVIDUAL' => 'Pesaje Individual (Pieza a Pieza)',
                    'PESO_CANTIDAD'   => 'Peso y Cantidad (Por Lote)',
                    'SOLO_PESO'       => 'Solo Peso Global',
                    default           => $metodo,
                };

                $speciesMap[$cod]['modos'][] = [
                    'metodo'      => $metodo,
                    'label'       => $labelMetodo,
                    'peso'        => round($pesoModo, 2),
                    'cantidad'    => $cantModo,
                    'recepciones' => $recModo,
                ];

                $speciesMap[$cod]['peso_total'] += $pesoModo;
                $speciesMap[$cod]['cantidad_total'] += $cantModo;
                $speciesMap[$cod]['total_recepciones'] += $recModo;

                $totalGlobalKg += $pesoModo;
                $totalGlobalPiezas += $cantModo;
            }
        }

        foreach ($speciesMap as &$sp) {
            $sp['peso_total'] = round($sp['peso_total'], 2);
        }
        unset($sp);

        return [
            'especies' => array_values($speciesMap),
            'resumen'  => [
                'total_especies' => count($speciesMap),
                'total_kilos'    => round($totalGlobalKg, 2),
                'total_piezas'   => $totalGlobalPiezas,
            ],
        ];
    }

    /**
     * Obtiene el listado de órdenes activas / recientes para visualización en el dashboard.
     * @return list<array<string, mixed>>
     */
    public static function getRecentOrders(int $limit = 5): array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT TOP ({$limit})
                    d.NroOrdenDesposte,
                    d.NroRecepcion,
                    ISNULL(NULLIF(d.LineaMesa, ''), 'Mesa General') AS LineaMesa,
                    d.FechaHoraInicio,
                    d.CodProdCanal,
                    LTRIM(RTRIM(CONCAT(p.Descrip, ' ', p.Descrip2, ' ', p.Descrip3))) AS NombreCanal,
                    CAST(d.PesoCanalEntrada AS FLOAT) AS PesoCanalEntrada,
                    CAST(d.PesoTotalCortes AS FLOAT) AS PesoTotalCortes,
                    CAST(d.RendimientoPct AS FLOAT) AS RendimientoPct,
                    d.Estado,
                    (SELECT COUNT(1) FROM ST_Despostadora_DespostesDetalle det WHERE det.NroOrdenDesposte = d.NroOrdenDesposte) AS TotalCortesPesados
                FROM ST_Despostadora_Despostes d
                INNER JOIN SAPROD p ON p.CodProd = d.CodProdCanal
                ORDER BY 
                    CASE 
                        WHEN d.Estado = 'EN_PROCESO' THEN 0 
                        WHEN d.Estado = 'ABIERTA' THEN 1 
                        ELSE 2 
                    END,
                    d.FechaHoraInicio DESC";

        $stmt = $pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Resumen completo para el dashboard y la API.
     * @return array<string, mixed>
     */
    public static function getDashboardSummary(): array
    {
        return [
            'ordenes'           => self::getOrdersKpis(),
            'kilos_despostados' => self::getDebonedWeightKpis(),
            'especies'          => self::getSpeciesAvailabilityKpis(),
            'recientes'         => self::getRecentOrders(5),
            'fecha_consulta'    => date('Y-m-d H:i:s'),
        ];
    }
}
