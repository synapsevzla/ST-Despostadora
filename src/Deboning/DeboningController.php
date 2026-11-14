<?php
declare(strict_types=1);

namespace Src\Deboning;

use Config\Database;
use Config\Security;
use Src\Auth\AuthController;
use Src\Utils\View;
use Throwable;

final class DeboningController
{
    /**
     * Muestra la interfaz táctil y tablero operativo de desposte multi-línea.
     */
    public static function index(): void
    {
        $user = AuthController::checkAuth();

        try {
            $fechaDesde = !empty($_GET['fecha_desde']) ? trim((string)$_GET['fecha_desde']) : null;
            $fechaHasta = !empty($_GET['fecha_hasta']) ? trim((string)$_GET['fecha_hasta']) : null;
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $initialOrder = !empty($_GET['order']) ? trim((string)$_GET['order']) : '';

            $species = ConfigService::getSpeciesList();
            $activeOrders = DeboningService::getActiveOrders();
            $ordersPagination = DeboningService::getOrdersPaginated($fechaDesde, $fechaHasta, $page, 10);
            $csrfToken = Security::generateCsrfToken();

            View::render('deboning', [
                'user' => $user,
                'species' => $species,
                'activeOrders' => $activeOrders,
                'orders' => $ordersPagination['items'],
                'pagination' => $ordersPagination,
                'fechaDesde' => $ordersPagination['fecha_desde'],
                'fechaHasta' => $ordersPagination['fecha_hasta'],
                'initialOrder' => $initialOrder,
                'csrf_token' => $csrfToken,
                'activeNav' => 'deboning',
                'title' => 'Líneas de Desposte - ST Despostadora'
            ]);
        } catch (Throwable $e) {
            error_log('[DEBONING INDEX ERROR] ' . $e->getMessage());
            if (Database::isConnectionError($e)) {
                Database::renderErrorView(Database::MSG_OFFLINE);
                return;
            }
            http_response_code(500);
            echo "Error cargando módulo de desposte: " . htmlspecialchars($e->getMessage());
        }
    }

    /**
     * Muestra la página completa e independiente para abrir una nueva orden de desposte.
     */
    public static function nueva(): void
    {
        $user = AuthController::checkAuth();

        try {
            $species = ConfigService::getSpeciesList();
            $mesas = ConfigService::getMesas(true);
            $nextCorrelativo = CorrelativeService::previewNext('DESPOSTE');
            $selectedEspecie = !empty($_GET['especie']) ? trim((string)$_GET['especie']) : '';
            $csrfToken = Security::generateCsrfToken();

            View::render('desposte_nueva', [
                'user' => $user,
                'species' => $species,
                'mesas' => $mesas,
                'nextCorrelativo' => $nextCorrelativo,
                'selectedEspecie' => $selectedEspecie,
                'csrf_token' => $csrfToken,
                'activeNav' => 'deboning',
                'title' => 'Nueva Orden de Desposte - ST Despostadora'
            ]);
        } catch (Throwable $e) {
            error_log('[DEBONING NUEVA ERROR] ' . $e->getMessage());
            if (Database::isConnectionError($e)) {
                Database::renderErrorView(Database::MSG_OFFLINE);
                return;
            }
            http_response_code(500);
            echo "Error cargando formulario de desposte: " . htmlspecialchars($e->getMessage());
        }
    }

    /**
     * Endpoint API para abrir una orden de desposte.
     */
    public static function open(): void
    {
        $user = AuthController::checkAuth();
        header('Content-Type: application/json; charset=UTF-8');

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido o expirado.']);
            return;
        }

        try {
            $nroRecepcion = trim((string)($_POST['nro_recepcion'] ?? ''));
            $lineaMesa = trim((string)($_POST['linea_mesa'] ?? ''));
            $nroEspecie = isset($_POST['nro_especie']) && $_POST['nro_especie'] !== '' ? (int)$_POST['nro_especie'] : null;
            $pesoEntrada = isset($_POST['peso_entrada']) ? round((float)$_POST['peso_entrada'], 4) : 0.0;
            $observaciones = trim((string)($_POST['observaciones'] ?? ''));

            // Validar mesa: solo es necesaria cuando existen mesas configuradas
            $activeMesas = ConfigService::getMesas(true);
            if (!empty($activeMesas)) {
                if ($lineaMesa === '') {
                    echo json_encode(['success' => false, 'error' => 'Debe seleccionar o indicar una mesa de trabajo operativa configurada.']);
                    return;
                }
            } else {
                // Si no existen o no hay creadas, su uso no es necesario
                $lineaMesa = '';
            }

            $nroOrden = DeboningService::openOrder([
                'nro_recepcion' => $nroRecepcion,
                'linea_mesa' => $lineaMesa,
                'nro_especie' => $nroEspecie,
                'peso_entrada' => $pesoEntrada,
                'observaciones' => $observaciones
            ], $user['codusua']);

            echo json_encode([
                'success' => true,
                'message' => "Orden de desposte {$nroOrden} abierta exitosamente.",
                'nro_orden' => $nroOrden
            ]);
        } catch (Throwable $e) {
            if (Database::isConnectionError($e)) {
                Database::handleApiError($e);
                return;
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para registrar el pesaje de un corte.
     */
    public static function weigh(): void
    {
        $user = AuthController::checkAuth();
        header('Content-Type: application/json; charset=UTF-8');

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido o expirado.']);
            return;
        }

        try {
            $nroOrden = trim((string)($_POST['nro_orden'] ?? ''));
            $codProdCorte = trim((string)($_POST['codprod_corte'] ?? ''));
            $pesoNeto = round((float)($_POST['peso_neto'] ?? 0.0), 4);

            $result = DeboningService::weighCut([
                'nro_orden' => $nroOrden,
                'codprod_corte' => $codProdCorte,
                'peso_neto' => $pesoNeto
            ], $user['codusua']);

            // Traer balance actualizado
            $balance = DeboningService::getYieldAndCosting($nroOrden);

            echo json_encode([
                'success' => true,
                'message' => "Corte registrado con Lote: {$result['nro_lote']}",
                'cut' => $result,
                'balance' => $balance
            ]);
        } catch (Throwable $e) {
            if (Database::isConnectionError($e)) {
                Database::handleApiError($e);
                return;
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para anular un corte pesado.
     */
    public static function deleteCut(): void
    {
        $user = AuthController::checkAuth();
        header('Content-Type: application/json; charset=UTF-8');

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido o expirado.']);
            return;
        }

        try {
            $nroOrden = trim((string)($_POST['nro_orden'] ?? ''));
            $id = (int)($_POST['id'] ?? 0);

            DeboningService::deleteCut($nroOrden, $id, $user['codusua']);
            $balance = DeboningService::getYieldAndCosting($nroOrden);

            echo json_encode([
                'success' => true,
                'message' => "Pesaje eliminado correctamente.",
                'balance' => $balance
            ]);
        } catch (Throwable $e) {
            if (Database::isConnectionError($e)) {
                Database::handleApiError($e);
                return;
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para consultar el balance y tabla de costeo en tiempo real.
     */
    public static function balance(): void
    {
        AuthController::checkAuth();
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $nroOrden = trim((string)($_GET['nro_orden'] ?? ''));
            if ($nroOrden === '') {
                throw new \InvalidArgumentException("Nro de orden no especificado.");
            }

            $balance = DeboningService::getYieldAndCosting($nroOrden);
            echo json_encode(['success' => true, 'balance' => $balance]);
        } catch (Throwable $e) {
            if (Database::isConnectionError($e)) {
                Database::handleApiError($e);
                return;
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para cerrar definitivamente una orden de desposte.
     */
    public static function close(): void
    {
        $user = AuthController::checkAuth();
        header('Content-Type: application/json; charset=UTF-8');

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido o expirado.']);
            return;
        }

        try {
            $nroOrden = trim((string)($_POST['nro_orden'] ?? ''));
            DeboningService::closeOrder($nroOrden, $user['codusua']);

            echo json_encode([
                'success' => true,
                'message' => "Orden de desposte {$nroOrden} cerrada exitosamente. Documentos en espera (Descargo de Canal y Cargo de Cortes y Merma) colocados en Saint ERP."
            ]);
        } catch (Throwable $e) {
            if (Database::isConnectionError($e)) {
                Database::handleApiError($e);
                return;
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para consultar el estado del cruce de documentos en espera en Saint ERP.
     */
    public static function erpStatus(): void
    {
        AuthController::checkAuth();
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $nroOrden = trim((string)($_GET['nro_orden'] ?? ''));
            if ($nroOrden === '') {
                echo json_encode(['success' => false, 'error' => 'Nro de orden requerido.']);
                return;
            }
            $pdo = Database::getConnection();
            DeboningErpHoldService::syncDeboningCruceStatus($pdo, $nroOrden);
            $status = DeboningErpHoldService::getDeboningCruceStatus($pdo, $nroOrden);
            echo json_encode(['success' => true, 'data' => $status]);
        } catch (Throwable $e) {
            if (Database::isConnectionError($e)) {
                Database::handleApiError($e);
                return;
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para obtener recepciones disponibles para abrir órdenes.
     */
    public static function availableReceptions(): void
    {
        AuthController::checkAuth();
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $canal = trim((string)($_GET['canal'] ?? ''));
            $receptions = ReceptionService::getAvailableForDeboning($canal);
            echo json_encode(['success' => true, 'receptions' => $receptions]);
        } catch (Throwable $e) {
            if (Database::isConnectionError($e)) {
                Database::handleApiError($e);
                return;
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para obtener el listado de órdenes activas (para polling o refresco del tablero).
     */
    public static function active(): void
    {
        AuthController::checkAuth();
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $orders = DeboningService::getActiveOrders();
            echo json_encode(['success' => true, 'orders' => $orders]);
        } catch (Throwable $e) {
            if (Database::isConnectionError($e)) {
                Database::handleApiError($e);
                return;
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para sincronización en tiempo real (Smart Delta Polling).
     */
    public static function sync(): void
    {
        AuthController::checkAuth();
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $nroOrden = trim((string)($_GET['nro_orden'] ?? ''));
            $version = isset($_GET['version']) ? (int)$_GET['version'] : 0;

            if ($nroOrden === '') {
                echo json_encode(['success' => false, 'error' => 'Nro de orden requerido.']);
                return;
            }

            $result = DeboningService::getSyncStatus($nroOrden, $version);
            echo json_encode($result);
        } catch (Throwable $e) {
            if (Database::isConnectionError($e)) {
                Database::handleApiError($e);
                return;
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para modificar el peso de un corte existente con trazabilidad.
     */
    public static function updateCutWeight(): void
    {
        $user = AuthController::checkAuth();
        header('Content-Type: application/json; charset=UTF-8');

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido o expirado.']);
            return;
        }

        try {
            $nroOrden = trim((string)($_POST['nro_orden'] ?? ''));
            $id = (int)($_POST['id'] ?? 0);
            $nuevoPeso = round((float)($_POST['nuevo_peso'] ?? 0.0), 4);
            $motivo = trim((string)($_POST['motivo'] ?? ''));

            $result = DeboningService::updateCutWeight([
                'nro_orden' => $nroOrden,
                'id' => $id,
                'nuevo_peso' => $nuevoPeso,
                'motivo' => $motivo
            ], $user['codusua']);

            $balance = DeboningService::getYieldAndCosting($nroOrden);

            echo json_encode([
                'success' => true,
                'message' => "Peso del lote {$result['nro_lote']} actualizado a {$result['peso_nuevo']} kg.",
                'cut' => $result,
                'balance' => $balance
            ]);
        } catch (Throwable $e) {
            if (Database::isConnectionError($e)) {
                Database::handleApiError($e);
                return;
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint API para consultar la bitácora inmutable de trazabilidad de pesajes.
     */
    public static function auditLog(): void
    {
        AuthController::checkAuth();
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $nroOrden = trim((string)($_GET['nro_orden'] ?? ''));
            if ($nroOrden === '') {
                echo json_encode(['success' => false, 'error' => 'Nro de orden requerido.']);
                return;
            }

            $log = DeboningService::getAuditLog($nroOrden);
            echo json_encode(['success' => true, 'log' => $log]);
        } catch (Throwable $e) {
            if (Database::isConnectionError($e)) {
                Database::handleApiError($e);
                return;
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
