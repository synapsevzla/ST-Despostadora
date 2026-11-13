<?php
declare(strict_types=1);

namespace Src\Deboning;

use Config\Database;
use PDO;
use RuntimeException;
use Throwable;

final class CorrelativeService
{
    /**
     * Obtiene el siguiente correlativo alfanumérico para el tipo indicado,
     * garantizando atomicidad y cero colisiones mediante bloqueo pesimista en SQL Server.
     *
     * @param PDO $pdo Conexión activa dentro de una transacción
     * @param string $tipo Tipo de correlativo (RECEPCION, DESPOSTE, TRANSFERENCIA)
     * @return string Correlativo generado (máximo 20 caracteres)
     */
    public static function getNext(PDO $pdo, string $tipo): string
    {
        $tipo = strtoupper(trim($tipo));

        // 1. Bloqueo exclusivo de fila
        $sqlSelect = "SELECT UltimoNumero, Prefijo, Longitud, Activo 
                      FROM ST_Despostadora_Correlativos WITH (UPDLOCK, ROWLOCK) 
                      WHERE Tipo = :tipo";
        $stmt = $pdo->prepare($sqlSelect);
        $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException("El correlativo para el tipo '{$tipo}' no está configurado.");
        }

        if ((int)$row['Activo'] !== 1) {
            throw new RuntimeException("El correlativo para el tipo '{$tipo}' se encuentra desactivado.");
        }

        $ultimoNumero = (int)$row['UltimoNumero'];
        $nextVal = $ultimoNumero + 1;
        $prefijo = trim((string)$row['Prefijo']);
        $longitudTotal = min((int)$row['Longitud'], 20);
        if ($longitudTotal < 8) {
            $longitudTotal = 20;
        }

        // 2. Incrementar número secuencial
        $sqlUpdate = "UPDATE ST_Despostadora_Correlativos 
                      SET UltimoNumero = :nextval, 
                          FechaModif = SYSUTCDATETIME() 
                      WHERE Tipo = :tipo";
        $stmtUpd = $pdo->prepare($sqlUpdate);
        $stmtUpd->bindValue(':nextval', $nextVal, PDO::PARAM_INT);
        $stmtUpd->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmtUpd->execute();

        // 3. Formatear con padding de ceros a la izquierda respetando longitud máx 20
        $espacioDigitos = max(1, $longitudTotal - mb_strlen($prefijo));
        $digitosPadded = str_pad((string)$nextVal, $espacioDigitos, '0', STR_PAD_LEFT);

        $resultado = $prefijo . $digitosPadded;
        return mb_substr($resultado, 0, 20);
    }

    /**
     * Previsualiza el formato de un correlativo sin incrementar la secuencia.
     */
    public static function preview(string $prefijo, int $longitud, int $proximoNumero): string
    {
        $prefijo = trim($prefijo);
        $longitud = max(8, min($longitud, 20));
        $proximoNumero = max(1, $proximoNumero);

        $espacioDigitos = max(1, $longitud - mb_strlen($prefijo));
        $digitosPadded = str_pad((string)$proximoNumero, $espacioDigitos, '0', STR_PAD_LEFT);

        return mb_substr($prefijo . $digitosPadded, 0, 20);
    }

    /**
     * Previsualiza el siguiente correlativo configurado para un tipo de documento.
     */
    public static function previewNext(string $tipo): string
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT UltimoNumero, Prefijo, Longitud FROM ST_Despostadora_Correlativos WHERE Tipo = :tipo");
            $stmt->bindValue(':tipo', strtoupper(trim($tipo)), PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return 'REC-0000000000000001';
            }

            $nextVal = ((int)$row['UltimoNumero']) + 1;
            return self::preview((string)$row['Prefijo'], (int)$row['Longitud'], $nextVal);
        } catch (Throwable $e) {
            return 'REC-0000000000000001';
        }
    }

    /**
     * Genera automáticamente un número de lote estructurado para un corte dentro de una orden.
     * Estructura: PREFIJO + AAAAMMDD + - + SECUENCIA_ORDEN + - + ITEM
     * Ejemplo: LOT-20261113-0001-01
     *
     * @param PDO $pdo Conexión a base de datos
     * @param string $nroOrdenDesposte Correlativo de la orden (ej. DESP-00000000000001)
     * @param int $nroItem Consecutivo del corte pesado (1, 2, 3...)
     * @return string Número de lote único (máx 30 caracteres)
     */
    public static function generateBatchNumber(PDO $pdo, string $nroOrdenDesposte, int $nroItem): string
    {
        // 1. Obtener prefijo de lote configurado
        $stmt = $pdo->prepare("SELECT Valor FROM ST_Despostadora_Configuracion WHERE Clave = 'LOTE_PREFIJO'");
        $stmt->execute();
        $prefijoLote = (string)($stmt->fetchColumn() ?: 'LOT-');

        // 2. Fecha de producción AAAAMMDD
        $fechaCompacta = date('Ymd');

        // 3. Extraer dígitos relevantes de la orden (últimos 4-5 dígitos o limpieza de prefijos)
        preg_match_all('/\d+/', $nroOrdenDesposte, $matches);
        $digitosOrden = !empty($matches[0]) ? end($matches[0]) : '0001';
        $ordenSeq = str_pad(mb_substr((string)$digitosOrden, -4), 4, '0', STR_PAD_LEFT);

        // 4. Consecutivo del ítem dentro de la orden
        $itemSeq = str_pad((string)$nroItem, 2, '0', STR_PAD_LEFT);

        $nroLote = "{$prefijoLote}{$fechaCompacta}-{$ordenSeq}-{$itemSeq}";
        return mb_substr($nroLote, 0, 30);
    }
}
