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
            $species = ConfigService::getSpeciesList();
            $receptions = ReceptionService::getReceptions();
            $deposits = ConfigService::getDeposits();
            $defaultCodUbic = ConfigService::getConfig('DEFAULT_CODUBIC', '');

            $csrfToken = Security::generateCsrfToken();

            View::render('recepcion', [
                'user' => $user,
                'species' => $species,
                'receptions' => $receptions,
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
            $csrfToken = Security::generateCsrfToken();

            View::render('recepcion_nueva', [
                'user' => $user,
                'species' => $species,
                'deposits' => $deposits,
                'defaultCodUbic' => $defaultCodUbic,
                'nextCorrelativo' => $nextCorrelativo,
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
            $tara = (float)($_POST['tara'] ?? 0.0);
            $pesoBruto = (float)($_POST['peso_bruto'] ?? 0.0);
            $pesoNeto = (float)($_POST['peso_neto'] ?? 0.0);
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
                        $val = (float)$p;
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
