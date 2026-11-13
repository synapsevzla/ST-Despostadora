<?php
declare(strict_types=1);

namespace Src\Deboning;

use Src\Auth\AuthController;
use Src\Utils\View;
use Throwable;

final class DashboardController
{
    /**
     * Muestra la vista principal del Dashboard con todos los KPIs.
     */
    public static function index(): void
    {
        $user = AuthController::checkAuth();

        try {
            $summary = DashboardService::getDashboardSummary();
        } catch (Throwable $e) {
            error_log('[DASHBOARD ERROR] ' . $e->getMessage());
            $summary = [
                'ordenes' => ['abiertas' => 0, 'en_proceso' => 0, 'terminadas' => 0, 'total' => 0],
                'kilos_despostados' => ['dia' => 0.0, 'semana' => 0.0, 'mes' => 0.0, 'cortes_dia' => 0, 'cortes_semana' => 0, 'cortes_mes' => 0],
                'especies' => ['especies' => [], 'resumen' => ['total_especies' => 0, 'total_kilos' => 0.0, 'total_piezas' => 0]],
                'recientes' => [],
                'fecha_consulta' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage(),
            ];
        }

        View::render('home', [
            'user'             => $user,
            'ordenes'          => $summary['ordenes'],
            'kilos'            => $summary['kilos_despostados'],
            'especiesData'     => $summary['especies'],
            'recientes'        => $summary['recientes'],
            'fechaConsulta'    => $summary['fecha_consulta'],
            'dashboardError'   => $summary['error'] ?? null,
        ]);
    }

    /**
     * Endpoint API JSON para auto-refresco en vivo de KPIs.
     */
    public static function apiSummary(): void
    {
        AuthController::checkAuth();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $data = DashboardService::getDashboardSummary();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }
}
