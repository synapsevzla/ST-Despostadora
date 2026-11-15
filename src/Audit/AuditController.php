<?php
declare(strict_types=1);

namespace Src\Audit;

use Config\Database;
use Config\Security;
use Src\Auth\AuthController;
use Src\Utils\View;
use Throwable;

final class AuditController
{
    /**
     * Muestra la interfaz principal del Módulo de Auditoría (Exclusivo Nivel 01 o 1).
     */
    public static function index(): void
    {
        $user = AuthController::requireAdmin();

        try {
            $users = AuditQueryService::getDistinctUsers();
            $modules = AuditQueryService::getDistinctModules();
            $kpis = AuditQueryService::getSummaryKpis();
            $csrfToken = Security::generateCsrfToken();

            View::render('auditoria', [
                'user' => $user,
                'users' => $users,
                'modules' => $modules,
                'kpis' => $kpis,
                'csrf_token' => $csrfToken,
                'activeNav' => 'auditoria',
                'title' => 'Auditoría y Control Forense - ST Despostadora'
            ]);
        } catch (Throwable $e) {
            error_log('[AUDIT INDEX ERROR] ' . $e->getMessage());
            if (Database::isConnectionError($e)) {
                Database::renderErrorView(Database::MSG_OFFLINE);
            } else {
                http_response_code(500);
                echo "Error interno al cargar módulo de auditoría: " . Security::escape($e->getMessage());
            }
        }
    }

    private static function sendJsonHeader(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
    }

    /**
     * Endpoint API: KPIs del módulo de auditoría.
     */
    public static function apiKpis(): void
    {
        AuthController::requireAdminApi();
        self::sendJsonHeader();

        try {
            $fechaDesde = !empty($_GET['fecha_desde']) ? trim((string)$_GET['fecha_desde']) : null;
            $fechaHasta = !empty($_GET['fecha_hasta']) ? trim((string)$_GET['fecha_hasta']) : null;

            $kpis = AuditQueryService::getSummaryKpis($fechaDesde, $fechaHasta);
            echo json_encode(['success' => true, 'data' => $kpis], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('[API AUDIT KPIS ERROR] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Endpoint API: Bitácora general de eventos paginada con filtros.
     */
    public static function apiEvents(): void
    {
        AuthController::requireAdminApi();
        self::sendJsonHeader();

        try {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;

            $filters = [
                'modulo' => $_GET['modulo'] ?? '',
                'accion' => $_GET['accion'] ?? '',
                'codusua' => $_GET['codusua'] ?? '',
                'fecha_desde' => $_GET['fecha_desde'] ?? '',
                'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
                'severidad' => $_GET['severidad'] ?? '',
                'q' => $_GET['q'] ?? '',
            ];

            $res = AuditQueryService::getEvents($filters, $page, $perPage);
            echo json_encode(['success' => true, 'data' => $res], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('[API AUDIT EVENTS ERROR] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Endpoint API: Log forense de pesajes y modificaciones en mesa.
     */
    public static function apiCutsLog(): void
    {
        AuthController::requireAdminApi();
        self::sendJsonHeader();

        try {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;

            $filters = [
                'nro_orden' => $_GET['nro_orden'] ?? '',
                'tipo_accion' => $_GET['tipo_accion'] ?? '',
                'codusua' => $_GET['codusua'] ?? '',
                'fecha_desde' => $_GET['fecha_desde'] ?? '',
                'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
                'q' => $_GET['q'] ?? '',
            ];

            $res = AuditQueryService::getCutsLog($filters, $page, $perPage);
            echo json_encode(['success' => true, 'data' => $res], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('[API AUDIT CUTS ERROR] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Endpoint API: Órdenes con mermas y rendimientos críticos.
     */
    public static function apiYieldAnomalies(): void
    {
        AuthController::requireAdminApi();
        self::sendJsonHeader();

        try {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;

            $filters = [
                'solo_alertas' => isset($_GET['solo_alertas']) && $_GET['solo_alertas'] === '1',
                'fecha_desde' => $_GET['fecha_desde'] ?? '',
                'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
                'q' => $_GET['q'] ?? '',
            ];

            $res = AuditQueryService::getYieldAnomalies($filters, $page, $perPage);
            echo json_encode(['success' => true, 'data' => $res], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('[API AUDIT YIELD ERROR] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Endpoint API: Eventos de seguridad y accesos.
     */
    public static function apiSecurity(): void
    {
        AuthController::requireAdminApi();
        self::sendJsonHeader();

        try {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;

            $filters = [
                'modulo' => 'SEGURIDAD',
                'accion' => $_GET['accion'] ?? '',
                'codusua' => $_GET['codusua'] ?? '',
                'fecha_desde' => $_GET['fecha_desde'] ?? '',
                'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
                'q' => $_GET['q'] ?? '',
            ];

            $res = AuditQueryService::getEvents($filters, $page, $perPage);
            echo json_encode(['success' => true, 'data' => $res], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('[API AUDIT SECURITY ERROR] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Endpoint API: Explorador de genealogía y trazabilidad cárnica.
     */
    public static function apiTraceability(): void
    {
        AuthController::requireAdminApi();
        self::sendJsonHeader();

        try {
            $code = trim((string)($_GET['code'] ?? ''));
            if ($code === '') {
                echo json_encode(['success' => false, 'error' => 'Debe ingresar un código de lote, orden o recepción.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $chain = AuditQueryService::getTraceability($code);
            if (!$chain) {
                echo json_encode(['success' => false, 'error' => "No se encontraron registros para el código '{$code}'."], JSON_UNESCAPED_UNICODE);
                return;
            }

            echo json_encode(['success' => true, 'data' => $chain], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('[API AUDIT TRACEABILITY ERROR] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Exportación de la bitácora a formato CSV estructurado con UTF-8 BOM.
     */
    public static function exportCsv(): void
    {
        AuthController::requireAdmin();

        try {
            $filters = [
                'modulo' => $_GET['modulo'] ?? '',
                'accion' => $_GET['accion'] ?? '',
                'codusua' => $_GET['codusua'] ?? '',
                'fecha_desde' => $_GET['fecha_desde'] ?? '',
                'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
                'severidad' => $_GET['severidad'] ?? '',
                'q' => $_GET['q'] ?? '',
            ];

            // Obtener hasta 5000 registros para exportación
            $res = AuditQueryService::getEvents($filters, 1, 5000);

            $filename = 'auditoria_st_despostadora_' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Escribir BOM UTF-8 para apertura correcta en Microsoft Excel
            echo "\xEF\xBB\xBF";

            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['ID', 'FechaHora UTC', 'Módulo', 'Documento/Clave', 'Acción', 'Severidad', 'Usuario', 'Nombre Usuario', 'IP', 'Detalle JSON'], ';');

            foreach ($res['items'] as $item) {
                fputcsv($out, [
                    $item['id'],
                    $item['fecha_hora'],
                    $item['modulo'],
                    $item['documento_id'],
                    $item['accion'],
                    $item['severidad'],
                    $item['codusua'],
                    $item['nombre_usuario'],
                    $item['ip'],
                    $item['detalle'] ? json_encode($item['detalle'], JSON_UNESCAPED_UNICODE) : ''
                ], ';');
            }

            fclose($out);
            exit;
        } catch (Throwable $e) {
            error_log('[AUDIT CSV EXPORT ERROR] ' . $e->getMessage());
            http_response_code(500);
            echo "Error generando archivo de exportación: " . Security::escape($e->getMessage());
        }
    }
}
