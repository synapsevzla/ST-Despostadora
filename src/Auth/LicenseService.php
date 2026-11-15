<?php
declare(strict_types=1);

namespace Src\Auth;

use Config\Database;
use Src\Utils\AuditService;
use PDO;
use Throwable;

/**
 * Servicio de Validación y Control Centralizado de Licencias
 * Soluciones Totales Despostadora Saint
 *
 * Conexión: Firebase Realtime Database (RTDB) vía REST HTTPS.
 * Almacenamiento: Cifrado AES-256-GCM en ST_Despostadora_Configuracion (campo DATA1).
 */
final class LicenseService
{
    private const RTDB_BASE_URL = 'https://jhruiz-default-rtdb.asia-southeast1.firebasedatabase.app';
    private const RTDB_COLLECTION = 'Licencias_ST_Despostadora_Saint';
    private const TIMEOUT_SECONDS = 7;
    private const EXPIRATION_WARNING_DAYS = 15;

    public const STATUS_ACTIVA = 'ACTIVA';
    public const STATUS_VIGENTE_POR_VENCER = 'VIGENTE_POR_VENCER';
    public const STATUS_LICENCIA_VENCIDA = 'LICENCIA_VENCIDA';
    public const STATUS_LICENCIA_INVALIDA = 'LICENCIA_INVALIDA';
    public const STATUS_FECHA_ALTERADA_PASADO = 'FECHA_ALTERADA_PASADO';
    public const STATUS_FECHA_ALTERADA_RENOVACION = 'FECHA_ALTERADA_RENOVACION';
    public const STATUS_OFFLINE_SIN_LICENCIA = 'OFFLINE_SIN_LICENCIA';
    public const STATUS_SIN_SERIAL = 'SIN_SERIAL';

    public const MSG_LICENCIA_VENCIDA = 'Licencia Vencida Contacte a su Distribuidor';
    public const MSG_LICENCIA_INVALIDA = 'El número de licencia no existe en el sistema';
    public const MSG_FECHA_ALTERADA_PASADO = 'La fecha del equipo ha sido alterada hacia el pasado. Corrija la hora y fecha de su estación';
    public const MSG_FECHA_ALTERADA_RENOVACION = 'La fecha del sistema es anterior a la fecha de emisión de la licencia';
    public const MSG_SIN_SERIAL = 'No se encontró una instalación válida del sistema base (saconf no accesible o sin serial registrado).';
    public const MSG_OFFLINE_SIN_LICENCIA = 'No se puede validar la licencia: se requiere conexión a internet para la activación inicial.';

    /** @var array<string, mixed>|null */
    private static ?array $currentLicense = null;

    public static function getCurrent(): ?array
    {
        return self::$currentLicense;
    }

    /**
     * Valida la licencia del sistema siguiendo el flujo de 5 pasos con tolerancia offline
     * y anti-tampering centralizado en base de datos.
     *
     * @return array<string, mixed>
     */
    public static function check(bool $forceCloud = false): array
    {
        if (self::$currentLicense !== null && !$forceCloud) {
            return self::$currentLicense;
        }

        $serial = self::getLocalSerial();
        if ($serial === '') {
            $result = self::buildResponse(false, true, self::STATUS_SIN_SERIAL, self::MSG_SIN_SERIAL, [], date('Y-m-d'), 'local');
            self::$currentLicense = $result;
            return $result;
        }

        $hoy = date('Y-m-d');
        $localEncrypted = self::readData1FromDb();
        $localData = ($localEncrypted !== null) ? self::decrypt($localEncrypted, $serial) : null;

        // Caso 1: Hay datos locales en DATA1 correspondientes a este serial
        if (is_array($localData) && isset($localData['nrolicencia']) && $localData['nrolicencia'] === $serial) {
            $fechaRenovacion = (string)($localData['fechaultimarenovacion'] ?? '');
            if ($fechaRenovacion !== '' && $hoy < $fechaRenovacion) {
                AuditService::log('SEGURIDAD', 'SISTEMA', 'LICENCIA_FECHA_MENOR_RENOVACION', 'SYSTEM', ['hoy' => $hoy, 'fechaultimarenovacion' => $fechaRenovacion, 'serial' => $serial]);
                $result = self::buildResponse(false, true, self::STATUS_FECHA_ALTERADA_RENOVACION, self::MSG_FECHA_ALTERADA_RENOVACION, $localData, $hoy, 'local');
                self::$currentLicense = $result;
                return $result;
            }

            $ultimaEjecucion = (string)($localData['ultima_fecha_ejecucion'] ?? '');
            if ($ultimaEjecucion !== '' && $hoy < $ultimaEjecucion) {
                AuditService::log('SEGURIDAD', 'SISTEMA', 'LICENCIA_FECHA_ALTERADA_PASADO', 'SYSTEM', ['hoy' => $hoy, 'ultima_fecha_ejecucion' => $ultimaEjecucion, 'serial' => $serial]);
                $result = self::buildResponse(false, true, self::STATUS_FECHA_ALTERADA_PASADO, self::MSG_FECHA_ALTERADA_PASADO, $localData, $hoy, 'local');
                self::$currentLicense = $result;
                return $result;
            }

            $diasRestantes = self::calculateRemainingDays((string)($localData['fechavencimiento'] ?? ''), $hoy);

            // Si faltan más de 15 días y no se fuerza nube: inicio directo offline sin internet
            if ($diasRestantes > self::EXPIRATION_WARNING_DAYS && !$forceCloud) {
                if ($ultimaEjecucion !== $hoy) {
                    $localData['ultima_fecha_ejecucion'] = $hoy;
                    self::saveData1ToDb($localData, $serial);
                }
                $result = self::buildResponse(true, false, self::STATUS_ACTIVA, 'Licencia activa', $localData, $hoy, 'local', $diasRestantes);
                self::$currentLicense = $result;
                return $result;
            }

            // Faltan <= 15 días, expiró o se forzó nube: consultar Firebase para verificar renovación
            $cloud = self::fetchFromCloud($serial);
            if ($cloud['http_code'] === 200) {
                if ($cloud['data'] === null) {
                    $result = self::buildResponse(false, true, self::STATUS_LICENCIA_INVALIDA, self::MSG_LICENCIA_INVALIDA, $localData, $hoy, 'cloud');
                    self::$currentLicense = $result;
                    return $result;
                }
                if (is_array($cloud['data'])) {
                    $cloudData = $cloud['data'];
                    $cRenov = (string)($cloudData['fechaultimarenovacion'] ?? '');
                    if ($cRenov !== '' && $hoy < $cRenov) {
                        $result = self::buildResponse(false, true, self::STATUS_FECHA_ALTERADA_RENOVACION, self::MSG_FECHA_ALTERADA_RENOVACION, $cloudData, $hoy, 'cloud');
                        self::$currentLicense = $result;
                        return $result;
                    }

                    $localData['nrolicencia'] = (string)($cloudData['nrolicencia'] ?? $serial);
                    $localData['cliente'] = (string)($cloudData['cliente'] ?? ($localData['cliente'] ?? ''));
                    $localData['canaldedistribucion'] = (string)($cloudData['canaldedistribucion'] ?? ($localData['canaldedistribucion'] ?? ''));
                    $localData['dias'] = (int)($cloudData['dias'] ?? $localData['dias'] ?? 365);
                    $localData['fechaultimarenovacion'] = (string)($cloudData['fechaultimarenovacion'] ?? $localData['fechaultimarenovacion']);
                    $localData['fechavencimiento'] = (string)($cloudData['fechavencimiento'] ?? $localData['fechavencimiento']);
                    $localData['ultima_fecha_ejecucion'] = $hoy;
                    $localData['ultimo_chequeo_nube'] = $hoy;

                    self::saveData1ToDb($localData, $serial);
                    $diasNube = self::calculateRemainingDays($localData['fechavencimiento'], $hoy);
                    $result = self::evaluateByRemainingDays($localData, $diasNube, $hoy, 'cloud');
                    self::$currentLicense = $result;
                    return $result;
                }
            }

            // Si falló la conexión con Firebase: tolerancia offline usando DATA1 hasta el vencimiento
            if ($ultimaEjecucion !== $hoy) {
                $localData['ultima_fecha_ejecucion'] = $hoy;
                self::saveData1ToDb($localData, $serial);
            }
            $result = self::evaluateByRemainingDays($localData, $diasRestantes, $hoy, 'local', true);
            self::$currentLicense = $result;
            return $result;
        }

        // Caso 2: DATA1 vacío o serial diferente -> Activación en la nube
        $cloud = self::fetchFromCloud($serial);
        if ($cloud['http_code'] === 200) {
            if ($cloud['data'] === null) {
                $result = self::buildResponse(false, true, self::STATUS_LICENCIA_INVALIDA, self::MSG_LICENCIA_INVALIDA, ['nrolicencia' => $serial], $hoy, 'cloud');
                self::$currentLicense = $result;
                return $result;
            }
            if (is_array($cloud['data'])) {
                $cloudData = $cloud['data'];
                $cRenov = (string)($cloudData['fechaultimarenovacion'] ?? '');
                if ($cRenov !== '' && $hoy < $cRenov) {
                    $result = self::buildResponse(false, true, self::STATUS_FECHA_ALTERADA_RENOVACION, self::MSG_FECHA_ALTERADA_RENOVACION, $cloudData, $hoy, 'cloud');
                    self::$currentLicense = $result;
                    return $result;
                }

                $newPayload = [
                    'nrolicencia' => (string)($cloudData['nrolicencia'] ?? $serial),
                    'cliente' => (string)($cloudData['cliente'] ?? ''),
                    'canaldedistribucion' => (string)($cloudData['canaldedistribucion'] ?? ''),
                    'dias' => (int)($cloudData['dias'] ?? 365),
                    'fechaultimarenovacion' => (string)($cloudData['fechaultimarenovacion'] ?? $hoy),
                    'fechavencimiento' => (string)($cloudData['fechavencimiento'] ?? $hoy),
                    'ultima_fecha_ejecucion' => $hoy,
                    'ultimo_chequeo_nube' => $hoy,
                ];

                self::saveData1ToDb($newPayload, $serial);
                $dias = self::calculateRemainingDays($newPayload['fechavencimiento'], $hoy);
                $result = self::evaluateByRemainingDays($newPayload, $dias, $hoy, 'cloud');
                self::$currentLicense = $result;
                return $result;
            }
        }

        $result = self::buildResponse(false, true, self::STATUS_OFFLINE_SIN_LICENCIA, self::MSG_OFFLINE_SIN_LICENCIA, ['nrolicencia' => $serial], $hoy, 'cloud_error');
        self::$currentLicense = $result;
        return $result;
    }

    /**
     * Realiza una verificación forzada en línea contra Firebase y actualiza DATA1 de forma autónoma.
     * @return array<string, mixed>
     */
    public static function verifyOnline(): array
    {
        $serial = self::getLocalSerial();
        if ($serial === '') {
            return ['valid' => false, 'bloqueado' => true, 'status' => self::STATUS_SIN_SERIAL, 'message' => self::MSG_SIN_SERIAL];
        }

        $cloud = self::fetchFromCloud($serial);
        if ($cloud['http_code'] !== 200) {
            return [
                'valid' => false,
                'bloqueado' => true,
                'status' => self::STATUS_OFFLINE_SIN_LICENCIA,
                'message' => 'No se pudo conectar con el servidor de licencias. Verifique su conexión (' . ($cloud['error'] ?? 'HTTP ' . $cloud['http_code']) . ').',
                'nrolicencia' => $serial,
            ];
        }

        if ($cloud['data'] === null) {
            return ['valid' => false, 'bloqueado' => true, 'status' => self::STATUS_LICENCIA_INVALIDA, 'message' => self::MSG_LICENCIA_INVALIDA, 'nrolicencia' => $serial];
        }

        $cloudData = (array)$cloud['data'];
        $hoy = date('Y-m-d');
        $cRenov = (string)($cloudData['fechaultimarenovacion'] ?? '');
        if ($cRenov !== '' && $hoy < $cRenov) {
            return self::buildResponse(false, true, self::STATUS_FECHA_ALTERADA_RENOVACION, self::MSG_FECHA_ALTERADA_RENOVACION, $cloudData, $hoy, 'cloud');
        }

        $payload = [
            'nrolicencia' => (string)($cloudData['nrolicencia'] ?? $serial),
            'cliente' => (string)($cloudData['cliente'] ?? ''),
            'canaldedistribucion' => (string)($cloudData['canaldedistribucion'] ?? ''),
            'dias' => (int)($cloudData['dias'] ?? 365),
            'fechaultimarenovacion' => (string)($cloudData['fechaultimarenovacion'] ?? $hoy),
            'fechavencimiento' => (string)($cloudData['fechavencimiento'] ?? $hoy),
            'ultima_fecha_ejecucion' => $hoy,
            'ultimo_chequeo_nube' => $hoy,
        ];

        self::saveData1ToDb($payload, $serial);
        AuditService::log('LICENCIA', 'SISTEMA', 'LICENCIA_ACTUALIZADA_ONLINE', 'SYSTEM', [
            'serial' => $serial,
            'cliente' => $payload['cliente'],
            'vencimiento' => $payload['fechavencimiento'],
        ]);

        self::$currentLicense = null;
        return self::check(false);
    }

    public static function getLocalSerial(): string
    {
        $envSerial = Database::getEnv('APP_LICENSE', '');
        if (trim($envSerial) !== '') {
            return trim($envSerial);
        }

        return self::getSaconfSerial();
    }

    public static function getSaconfSerial(): string
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT TOP 1 NroSerial FROM saconf ORDER BY CASE WHEN CodSucu = '00000' THEN 0 ELSE 1 END");
            if ($stmt) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['NroSerial'])) {
                    return trim((string)$row['NroSerial']);
                }
            }
        } catch (Throwable $e) {
            error_log('[SACONF QUERY ERROR] ' . $e->getMessage());
        }
        return '';
    }

    public static function getClientName(): string
    {
        $cached = self::getCachedInfo();
        if ($cached !== null && !empty($cached['cliente'])) {
            return trim((string)$cached['cliente']);
        }
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT TOP 1 Descrip FROM saconf ORDER BY CASE WHEN CodSucu = '00000' THEN 0 ELSE 1 END");
            if ($stmt) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['Descrip'])) {
                    return trim((string)$row['Descrip']);
                }
            }
        } catch (Throwable) {}
        return 'Cliente Autorizado';
    }

    public static function getCachedInfo(): ?array
    {
        $serial = self::getLocalSerial();
        if ($serial === '') {
            return null;
        }
        $raw = self::readData1FromDb();
        return ($raw !== null) ? self::decrypt($raw, $serial) : null;
    }

    private static function evaluateByRemainingDays(array $data, int $diasRestantes, string $hoy, string $origen, bool $offline = false): array
    {
        if ($diasRestantes < 0) {
            return self::buildResponse(false, true, self::STATUS_LICENCIA_VENCIDA, self::MSG_LICENCIA_VENCIDA, $data, $hoy, $origen, $diasRestantes, $offline);
        }
        if ($diasRestantes <= self::EXPIRATION_WARNING_DAYS) {
            $msg = "Su licencia vence en {$diasRestantes} días. Contacte a su distribuidor";
            return self::buildResponse(true, false, self::STATUS_VIGENTE_POR_VENCER, $msg, $data, $hoy, $origen, $diasRestantes, $offline);
        }
        return self::buildResponse(true, false, self::STATUS_ACTIVA, 'Licencia activa y vigente', $data, $hoy, $origen, $diasRestantes, $offline);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function buildResponse(bool $valid, bool $bloqueado, string $status, string $message, array $data, string $hoy, string $origen, ?int $diasRestantes = null, bool $offline = false): array
    {
        $vencimiento = (string)($data['fechavencimiento'] ?? '');
        if ($diasRestantes === null && $vencimiento !== '') {
            $diasRestantes = self::calculateRemainingDays($vencimiento, $hoy);
        }

        return [
            'valid' => $valid,
            'bloqueado' => $bloqueado,
            'status' => $status,
            'message' => $message,
            'nrolicencia' => (string)($data['nrolicencia'] ?? ''),
            'cliente' => (string)($data['cliente'] ?? ''),
            'canaldedistribucion' => (string)($data['canaldedistribucion'] ?? ''),
            'dias' => (int)($data['dias'] ?? 0),
            'dias_restantes' => $diasRestantes,
            'fechaultimarenovacion' => (string)($data['fechaultimarenovacion'] ?? ''),
            'fechavencimiento' => $vencimiento,
            'ultima_fecha_ejecucion' => (string)($data['ultima_fecha_ejecucion'] ?? $hoy),
            'ultimo_chequeo_nube' => (string)($data['ultimo_chequeo_nube'] ?? ''),
            'origen' => $origen,
            'offline_mode' => $offline,
        ];
    }

    private static function calculateRemainingDays(string $fechavencimiento, string $hoy): int
    {
        $tsVenc = strtotime($fechavencimiento . ' 00:00:00 UTC');
        $tsHoy = strtotime($hoy . ' 00:00:00 UTC');
        return ($tsVenc === false || $tsHoy === false) ? -1 : (int)round(($tsVenc - $tsHoy) / 86400);
    }

    private static function getCaBundlePath(): ?string
    {
        $candidates = [
            getenv('SSL_CERT_FILE') ?: '',
            getenv('CURL_CA_BUNDLE') ?: '',
            ini_get('curl.cainfo') ?: '',
            ini_get('openssl.cafile') ?: '',
            dirname(__DIR__, 2) . '/config/cacert.pem',
            dirname(PHP_BINARY) . '/cacert.pem',
            dirname(PHP_BINARY) . '/extras/ssl/cacert.pem',
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
        ];

        foreach ($candidates as $cand) {
            if ($cand !== '' && is_file($cand)) {
                return $cand;
            }
        }

        return null;
    }

    private static function isSslVerificationError(int $errNo, string $err): bool
    {
        if (in_array($errNo, [35, 58, 60, 77], true)) {
            return true;
        }
        $lower = strtolower($err);
        return str_contains($lower, 'certificate')
            || str_contains($lower, 'issuer')
            || str_contains($lower, 'cacert')
            || str_contains($lower, 'ssl');
    }

    /**
     * @return array{http_code: int, data: mixed, error: ?string}
     */
    private static function fetchFromCloud(string $serial): array
    {
        $endpoint = self::RTDB_BASE_URL . '/' . self::RTDB_COLLECTION . '/' . rawurlencode(trim($serial)) . '.json';
        $caPath = self::getCaBundlePath();

        $ch = curl_init();
        $options = [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: ST-Despostadora-Saint/1.0'],
        ];

        if (defined('CURL_IPRESOLVE_V4')) {
            $options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }

        if ($caPath !== null) {
            $options[CURLOPT_CAINFO] = $caPath;
        }

        if (defined('CURLSSLOPT_NATIVE_CA')) {
            $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }

        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errNo = curl_errno($ch);
        $err = curl_error($ch);

        // Si falló por verificación SSL (antivirus, firewall corporativo o almacén desactualizado en Windows),
        // se reintenta exclusivamente contra el endpoint fijo de Firebase RTDB
        if (($body === false || $errNo !== 0) && self::isSslVerificationError($errNo, $err)) {
            error_log('[FIREBASE RTDB SSL WARNING] Falló verificación SSL estricta (' . $err . '). Reintentando con bypass SSL para endpoint autorizado...');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $body = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
        }

        curl_close($ch);

        if ($body === false || $err !== '') {
            error_log('[FIREBASE RTDB CURL ERROR] ' . $err);
            return ['http_code' => 0, 'data' => null, 'error' => $err ?: 'Error de conexión con Firebase'];
        }

        return ['http_code' => $httpCode, 'data' => json_decode((string)$body, true), 'error' => null];
    }

    private static function readData1FromDb(): ?string
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT DATA1 FROM ST_Despostadora_Configuracion WHERE Clave = 'DATA1'");
            if ($stmt) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['DATA1'])) {
                    return (string)$row['DATA1'];
                }
            }
        } catch (Throwable $e) {
            error_log('[LICENSE DB READ ERROR] ' . $e->getMessage());
        }
        return null;
    }

    private static function saveData1ToDb(array $data, string $serial, string $codusua = 'SYSTEM'): bool
    {
        try {
            $encrypted = self::encrypt($data, $serial);
            $pdo = Database::getConnection();
            $check = $pdo->prepare("SELECT COUNT(*) FROM ST_Despostadora_Configuracion WHERE Clave = 'DATA1'");
            $check->execute();
            if ((int)$check->fetchColumn() > 0) {
                $stmt = $pdo->prepare("UPDATE ST_Despostadora_Configuracion SET DATA1 = :data, FechaModif = SYSUTCDATETIME(), CodUsua = :codusua WHERE Clave = 'DATA1'");
            } else {
                $stmt = $pdo->prepare("INSERT INTO ST_Despostadora_Configuracion (Clave, Valor, DATA1, FechaModif, CodUsua) VALUES ('DATA1', 'LICENCIA_CENTRALIZADA', :data, SYSUTCDATETIME(), :codusua)");
            }
            $stmt->bindValue(':data', $encrypted, PDO::PARAM_STR);
            $stmt->bindValue(':codusua', $codusua, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (Throwable $e) {
            error_log('[LICENSE DB SAVE ERROR] ' . $e->getMessage());
            return false;
        }
    }

    private static function encrypt(array $payload, string $serial): string
    {
        $key = hash('sha256', 'ST_DESPOSTADORA_LICENSE_SECRET_V1_' . trim($serial), true);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($json ?: '{}', 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        return base64_encode($iv . $tag . $ciphertext);
    }

    private static function decrypt(string $ciphertext, string $serial): ?array
    {
        $raw = base64_decode($ciphertext, true);
        if ($raw === false || strlen($raw) < 28) {
            return null;
        }
        $key = hash('sha256', 'ST_DESPOSTADORA_LICENSE_SECRET_V1_' . trim($serial), true);
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $decrypted = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($decrypted === false) {
            return null;
        }
        $data = json_decode($decrypted, true);
        return is_array($data) ? $data : null;
    }
}
