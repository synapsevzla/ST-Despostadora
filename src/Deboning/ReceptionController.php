<?php
declare(strict_types=1);

namespace Src\Deboning;

use Config\Security;
use Src\Auth\AuthController;
use Src\Utils\View;
use Throwable;

final class ReceptionController
{
    /**
     * Muestra la interfaz operativa de recepción de canales / especies.
     */
    public static function index(): void
    {
        $user = AuthController::checkAuth();

        try {
            $fechaDesde = !empty($_GET['fecha_desde']) ? trim((string)$_GET['fecha_desde']) : null;
            $fechaHasta = !empty($_GET['fecha_hasta']) ? trim((string)$_GET['fecha_hasta']) : null;
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

            $species = ConfigService::getSpeciesList();
            $paginated = ReceptionService::getReceptionsPaginated($fechaDesde, $fechaHasta, $page, 10);
            $deposits = ConfigService::getDeposits();
            $defaultCodUbic = ConfigService::getConfig('DEFAULT_CODUBIC', '');

            $csrfToken = Security::generateCsrfToken();

            View::render('recepcion', [
                'user' => $user,
                'species' => $species,
                'receptions' => $paginated['items'],
                'pagination' => $paginated,
                'fechaDesde' => $paginated['fecha_desde'],
                'fechaHasta' => $paginated['fecha_hasta'],
                'deposits' => $deposits,
                'defaultCodUbic' => $defaultCodUbic,
                'csrf_token' => $csrfToken,
                'activeNav' => 'recepcion',
                'title' => 'Recepción de Canales - ST Despostadora'
            ]);
        } catch (Throwable $e) {
            error_log('[RECEPTION INDEX ERROR] ' . $e->getMessage());
            http_response_code(500);
            echo "Error cargando recepciones: " . htmlspecialchars($e->getMessage());
        }
    }

    /**
     * Muestra la página completa e independiente para registrar una nueva recepción.
     */
    public static function nueva(): void
    {
        $user = AuthController::checkAuth();

        try {
            $species = ConfigService::getSpeciesList();
            $deposits = ConfigService::getDeposits();
            $defaultCodUbic = ConfigService::getConfig('DEFAULT_CODUBIC', '');
            $nextCorrelativo = CorrelativeService::previewNext('RECEPCION');
            $selectedEspecie = !empty($_GET['especie']) ? trim((string)$_GET['especie']) : '';
            $csrfToken = Security::generateCsrfToken();

            View::render('recepcion_nueva', [
                'user' => $user,
                'species' => $species,
                'deposits' => $deposits,
                'defaultCodUbic' => $defaultCodUbic,
                'nextCorrelativo' => $nextCorrelativo,
                'selectedEspecie' => $selectedEspecie,
                'csrf_token' => $csrfToken,
                'activeNav' => 'recepcion',
                'title' => 'Nueva Recepción de Canales - ST Despostadora'
            ]);
        } catch (Throwable $e) {
            error_log('[RECEPTION NUEVA ERROR] ' . $e->getMessage());
            http_response_code(500);
            echo "Error cargando formulario de recepción: " . htmlspecialchars($e->getMessage());
        }
    }

    /**
     * Endpoint API para registrar una nueva recepción.
     */
    public static function create(): void
    {
        $user = AuthController::checkAuth();
        header('Content-Type: application/json; charset=UTF-8');

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido o expirado.']);
            return;
        }

        try {
            $codProd = trim((string)($_POST['codprod'] ?? ''));
            $metodo = trim((string)($_POST['metodo_entrada'] ?? 'SOLO_PESO'));
            $codUbic = trim((string)($_POST['codubic'] ?? ''));
            $tara = round((float)($_POST['tara'] ?? 0.0), 4);
            $pesoBruto = round((float)($_POST['peso_bruto'] ?? 0.0), 4);
            $pesoNeto = round((float)($_POST['peso_neto'] ?? 0.0), 4);
            $cantidad = (int)($_POST['cantidad'] ?? 0);
            $observaciones = trim((string)($_POST['observaciones'] ?? ''));

            $pesosIndividuales = [];
            if ($metodo === 'PESO_INDIVIDUAL') {
                $rawList = $_POST['pesos_individuales'] ?? [];
                if (is_string($rawList)) {
                    $decoded = json_decode($rawList, true);
                    if (is_array($decoded)) {
                        $rawList = $decoded;
                    }
                }
                if (is_array($rawList)) {
                    foreach ($rawList as $p) {
                        $val = round((float)$p, 4);
                        if ($val > 0) {
                            $pesosIndividuales[] = $val;
                        }
                    }
                }
            }

            $nroRecepcion = ReceptionService::createReception([
                'codprod' => $codProd,
                'metodo_entrada' => $metodo,
                'codubic' => $codUbic,
                'tara' => $tara,
                'peso_bruto' => $pesoBruto,
                'peso_neto' => $pesoNeto,
                'cantidad' => $cantidad,
                'pesos_individuales' => $pesosIndividuales,
                'observaciones' => $observaciones
            ], $user['codusua']);

            echo json_encode([
                'success' => true,
                'message' => "Recepción {$nroRecepcion} registrada exitosamente.",
                'nro_recepcion' => $nroRecepcion
            ]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para consultar el detalle de pesajes individuales de una recepción.
     */
    public static function detail(): void
    {
        AuthController::checkAuth();
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $nrorec = trim((string)($_GET['nrorec'] ?? ''));
            $details = ReceptionService::getReceptionDetail($nrorec);
            echo json_encode(['success' => true, 'details' => $details]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
