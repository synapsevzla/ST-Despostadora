<?php
declare(strict_types=1);

namespace Src\Deboning;

use Config\Security;
use Src\Auth\AuthController;
use Src\Utils\View;
use Throwable;

final class ConfigController
{
    /**
     * Muestra la interfaz de configuración general, correlativos y especies.
     */
    public static function index(): void
    {
        $user = AuthController::requireAdmin();

        try {
            $deposits = ConfigService::getDeposits();
            $instances = ConfigService::getProductInstances();
            $correlatives = ConfigService::getCorrelatives();
            $species = ConfigService::getSpeciesList();
            $mesas = ConfigService::getMesas(false);
            $defaultCodUbic = ConfigService::getConfig('DEFAULT_CODUBIC', '');
            $lotePrefijo = ConfigService::getConfig('LOTE_PREFIJO', 'LOT-');
            $margenDefault = ConfigService::getConfig('MARGEN_UTILIDAD_DEFAULT', '30.00');

            $csrfToken = Security::generateCsrfToken();

            View::render('configuracion', [
                'user' => $user,
                'deposits' => $deposits,
                'instances' => $instances,
                'correlatives' => $correlatives,
                'species' => $species,
                'mesas' => $mesas,
                'defaultCodUbic' => $defaultCodUbic,
                'lotePrefijo' => $lotePrefijo,
                'margenDefault' => $margenDefault,
                'csrf_token' => $csrfToken,
                'activeNav' => 'configuracion',
                'title' => 'Configuración - ST Despostadora'
            ]);
        } catch (Throwable $e) {
            error_log('[CONFIG INDEX ERROR] ' . $e->getMessage());
            http_response_code(500);
            echo "Error cargando la configuración: " . htmlspecialchars($e->getMessage());
        }
    }

    /**
     * Endpoint API para guardar configuración general (depósito, lotes, margen).
     */
    public static function saveGeneral(): void
    {
        $user = AuthController::requireAdmin();
        header('Content-Type: application/json; charset=UTF-8');

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido o expirado.']);
            return;
        }

        try {
            $codUbic = trim((string)($_POST['default_codubic'] ?? ''));
            $lotePrefijo = trim((string)($_POST['lote_prefijo'] ?? 'LOT-'));
            $margenDefault = round((float)($_POST['margen_default'] ?? 30.0), 4);

            ConfigService::saveConfig('DEFAULT_CODUBIC', $codUbic, $user['codusua']);
            ConfigService::saveConfig('LOTE_PREFIJO', $lotePrefijo, $user['codusua']);
            ConfigService::saveConfig('MARGEN_UTILIDAD_DEFAULT', (string)$margenDefault, $user['codusua']);

            echo json_encode(['success' => true, 'message' => 'Configuración guardada exitosamente.']);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para actualizar un correlativo.
     */
    public static function updateCorrelative(): void
    {
        $user = AuthController::requireAdmin();
        header('Content-Type: application/json; charset=UTF-8');

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido o expirado.']);
            return;
        }

        try {
            $tipo = trim((string)($_POST['tipo'] ?? ''));
            $prefijo = trim((string)($_POST['prefijo'] ?? ''));
            $longitud = (int)($_POST['longitud'] ?? 20);
            $ultimoNumero = (int)($_POST['ultimo_numero'] ?? 0);
            $activo = (int)($_POST['activo'] ?? 1);

            ConfigService::updateCorrelative($tipo, $prefijo, $longitud, $ultimoNumero, $activo, $user['codusua']);
            $preview = CorrelativeService::preview($prefijo, $longitud, $ultimoNumero + 1);

            echo json_encode([
                'success' => true,
                'message' => 'Correlativo actualizado correctamente.',
                'preview' => $preview
            ]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para buscar productos elegibles de SAPROD.
     */
    public static function searchProducts(): void
    {
        AuthController::checkAuth();
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $q = (string)($_GET['q'] ?? '');
            $codInst = (string)($_GET['codinst'] ?? '');
            $products = ConfigService::getEligibleProducts($q !== '' ? $q : null, $codInst !== '' ? $codInst : null);
            echo json_encode(['success' => true, 'products' => $products]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para guardar una especie/canal.
     */
    public static function saveSpecies(): void
    {
        $user = AuthController::requireAdmin();
        header('Content-Type: application/json; charset=UTF-8');

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido.']);
            return;
        }

        try {
            $codProd = trim((string)($_POST['codprod'] ?? ''));
            $codMerma = trim((string)($_POST['codprod_merma'] ?? ''));
            $margen = round((float)($_POST['margen'] ?? 30.0), 4);
            $diasRefrig = (int)($_POST['dias_refrig'] ?? 15);
            $diasCongel = (int)($_POST['dias_congel'] ?? 180);
            $activo = (int)($_POST['activo'] ?? 1);

            ConfigService::saveSpecies($codProd, $codMerma, $margen, $diasRefrig, $diasCongel, $activo, $user['codusua']);
            echo json_encode(['success' => true, 'message' => 'Especie registrada / actualizada correctamente.']);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para obtener los cortes asociados a una especie.
     */
    public static function getCuts(): void
    {
        AuthController::checkAuth();
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $canal = trim((string)($_GET['canal'] ?? ''));
            $cuts = ConfigService::getSpeciesCuts($canal);
            echo json_encode(['success' => true, 'cuts' => $cuts]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para agregar un corte a una especie.
     */
    public static function addCut(): void
    {
        $user = AuthController::requireAdmin();
        header('Content-Type: application/json; charset=UTF-8');

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido.']);
            return;
        }

        try {
            $canal = trim((string)($_POST['canal'] ?? ''));
            $corte = trim((string)($_POST['corte'] ?? ''));
            $rend = round((float)($_POST['rendimiento'] ?? 0.0), 4);
            $margen = round((float)($_POST['margen'] ?? 30.0), 4);
            $dias = (int)($_POST['dias_vencimiento'] ?? 15);

            ConfigService::addSpeciesCut($canal, $corte, $rend, $margen, $dias, $user['codusua']);
            echo json_encode(['success' => true, 'message' => 'Corte asociado exitosamente.']);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para desvincular un corte.
     */
    public static function removeCut(): void
    {
        $user = AuthController::requireAdmin();
        header('Content-Type: application/json; charset=UTF-8');

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido.']);
            return;
        }

        try {
            $canal = trim((string)($_POST['canal'] ?? ''));
            $corte = trim((string)($_POST['corte'] ?? ''));

            ConfigService::removeSpeciesCut($canal, $corte, $user['codusua']);
            echo json_encode(['success' => true, 'message' => 'Corte desvinculado exitosamente.']);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para consultar el listado de mesas registradas.
     */
    public static function getMesas(): void
    {
        AuthController::checkAuth();
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $onlyActive = isset($_GET['activas']) && (int)$_GET['activas'] === 1;
            $mesas = ConfigService::getMesas($onlyActive);
            echo json_encode(['success' => true, 'mesas' => $mesas]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para crear o actualizar una mesa operativa.
     */
    public static function saveMesa(): void
    {
        $user = AuthController::requireAdmin();
        header('Content-Type: application/json; charset=UTF-8');

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido.']);
            return;
        }

        try {
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
            $nombre = trim((string)($_POST['nombre'] ?? ''));
            $descripcion = trim((string)($_POST['descripcion'] ?? ''));
            $activo = (int)($_POST['activo'] ?? 1);

            ConfigService::saveMesa($id, $nombre, $descripcion !== '' ? $descripcion : null, $activo, $user['codusua']);
            $mesas = ConfigService::getMesas(false);

            echo json_encode([
                'success' => true,
                'message' => $id !== null ? 'Mesa actualizada correctamente.' : 'Mesa registrada correctamente.',
                'mesas' => $mesas
            ]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para alternar el estado activo/inactivo de una mesa.
     */
    public static function toggleMesaStatus(): void
    {
        $user = AuthController::requireAdmin();
        header('Content-Type: application/json; charset=UTF-8');

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido.']);
            return;
        }

        try {
            $id = (int)($_POST['id'] ?? 0);
            $activo = (int)($_POST['activo'] ?? 0);

            ConfigService::toggleMesaStatus($id, $activo, $user['codusua']);
            $mesas = ConfigService::getMesas(false);

            echo json_encode([
                'success' => true,
                'message' => 'Estado de la mesa modificado exitosamente.',
                'mesas' => $mesas
            ]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para eliminar una mesa (si no tiene órdenes vinculadas).
     */
    public static function deleteMesa(): void
    {
        $user = AuthController::requireAdmin();
        header('Content-Type: application/json; charset=UTF-8');

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido.']);
            return;
        }

        try {
            $id = (int)($_POST['id'] ?? 0);

            ConfigService::deleteMesa($id, $user['codusua']);
            $mesas = ConfigService::getMesas(false);

            echo json_encode([
                'success' => true,
                'message' => 'Mesa eliminada correctamente.',
                'mesas' => $mesas
            ]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
