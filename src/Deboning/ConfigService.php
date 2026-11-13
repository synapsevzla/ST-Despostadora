<?php
declare(strict_types=1);

namespace Src\Deboning;

use Config\Database;
use Src\Utils\AuditService;
use PDO;
use RuntimeException;
use Throwable;

final class ConfigService
{
    /**
     * Obtiene el listado de depósitos disponibles en el ERP (SADEPO).
     * @return list<array{CodUbic: string, Descrip: string}>
     */
    public static function getDeposits(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT CodUbic, Descrip FROM SADEPO ORDER BY CodUbic ASC");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Obtiene todas las instancias/categorías de productos aptos para desposte.
     * @return list<array{CodInst: string, Descrip: string}>
     */
    public static function getProductInstances(): array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT DISTINCT T2.CodInst, T2.Descrip 
                FROM SAINSTA T2
                INNER JOIN SAPROD T1 ON T1.CodInst = T2.CodInst
                WHERE T1.ExDecimal = 1 AND T1.EsEmpaque = 0
                ORDER BY T2.Descrip ASC";
        $stmt = $pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Obtiene productos de SAPROD aptos para desposte (admite decimales y no es empaque).
     * @return list<array<string, mixed>>
     */
    public static function getEligibleProducts(?string $search = null, ?string $codInst = null): array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT 
                    T1.CodProd,
                    LTRIM(RTRIM(CONCAT(T1.Descrip, ' ', T1.Descrip2, ' ', T1.Descrip3))) AS DescripcionCompleta,
                    T1.Unidad,
                    CAST(T1.Tara AS FLOAT) AS Tara,
                    CAST(ISNULL(T1.CostAct, 0) AS FLOAT) AS CostAct,
                    CAST(ISNULL(T1.Precio1, 0) AS FLOAT) AS Precio1,
                    T2.CodInst,
                    T2.Descrip AS Instancia,
                    CAST(T1.Existen AS FLOAT) AS ExistenciaERPReferencial
                FROM SAPROD T1
                INNER JOIN SAINSTA T2 ON T1.CodInst = T2.CodInst
                WHERE T1.ExDecimal = 1 AND T1.EsEmpaque = 0";

        $params = [];
        if ($codInst !== null && trim($codInst) !== '') {
            $sql .= " AND T1.CodInst = :codinst";
            $params[':codinst'] = trim($codInst);
        }

        if ($search !== null && trim($search) !== '') {
            $sql .= " AND (T1.CodProd LIKE :search1 OR T1.Descrip LIKE :search2 OR T1.Descrip2 LIKE :search3 OR T1.Descrip3 LIKE :search4)";
            $searchTerm = '%' . trim($search) . '%';
            $params[':search1'] = $searchTerm;
            $params[':search2'] = $searchTerm;
            $params[':search3'] = $searchTerm;
            $params[':search4'] = $searchTerm;
        }

        $sql .= " ORDER BY T2.Descrip ASC, T1.Descrip ASC";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el valor de una clave en ST_Despostadora_Configuracion.
     */
    public static function getConfig(string $clave, string $default = ''): string
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT Valor FROM ST_Despostadora_Configuracion WHERE Clave = :clave");
        $stmt->bindValue(':clave', trim($clave), PDO::PARAM_STR);
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : $default;
    }

    /**
     * Guarda o actualiza un valor de configuración con auditoría.
     */
    public static function saveConfig(string $clave, string $valor, string $codusua): void
    {
        $pdo = Database::getConnection();
        $clave = trim($clave);
        $valor = trim($valor);

        $valorAnterior = self::getConfig($clave, '');

        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM ST_Despostadora_Configuracion WHERE Clave = :c");
        $stmtCheck->bindValue(':c', $clave, PDO::PARAM_STR);
        $stmtCheck->execute();
        $exists = (int)$stmtCheck->fetchColumn() > 0;

        if ($exists) {
            $stmt = $pdo->prepare("UPDATE ST_Despostadora_Configuracion 
                                   SET Valor = :valor, FechaModif = SYSUTCDATETIME(), CodUsua = :codusua 
                                   WHERE Clave = :clave");
        } else {
            $stmt = $pdo->prepare("INSERT INTO ST_Despostadora_Configuracion (Clave, Valor, FechaModif, CodUsua)
                                   VALUES (:clave, :valor, SYSUTCDATETIME(), :codusua)");
        }

        $stmt->bindValue(':clave', $clave, PDO::PARAM_STR);
        $stmt->bindValue(':valor', $valor, PDO::PARAM_STR);
        $stmt->bindValue(':codusua', $codusua, PDO::PARAM_STR);
        $stmt->execute();

        AuditService::log('CONFIGURACION', $clave, 'MODIFICAR_PARAMETRO', $codusua, [
            'clave' => $clave,
            'anterior' => $valorAnterior,
            'nuevo' => $valor
        ]);
    }

    /**
     * Obtiene todos los correlativos configurados.
     * @return list<array<string, mixed>>
     */
    public static function getCorrelatives(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT Tipo, Descripcion, Prefijo, UltimoNumero, Longitud, Activo, FechaModif, CodUsuaModif 
                             FROM ST_Despostadora_Correlativos 
                             ORDER BY Tipo ASC");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Actualiza un correlativo verificando restricciones de seguridad.
     */
    public static function updateCorrelative(
        string $tipo,
        string $prefijo,
        int $longitud,
        int $ultimoNumero,
        int $activo,
        string $codusua
    ): void {
        $tipo = strtoupper(trim($tipo));
        $prefijo = trim($prefijo);
        $longitud = min(20, max(8, $longitud));

        if (mb_strlen($prefijo) >= $longitud) {
            throw new RuntimeException("El prefijo no puede ocupar toda la longitud del correlativo.");
        }

        $pdo = Database::getConnection();

        // 1. Obtener estado actual
        $stmtSel = $pdo->prepare("SELECT Prefijo, Longitud, UltimoNumero, Activo FROM ST_Despostadora_Correlativos WHERE Tipo = :tipo");
        $stmtSel->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmtSel->execute();
        $curr = $stmtSel->fetch(PDO::FETCH_ASSOC);

        if (!$curr) {
            throw new RuntimeException("El correlativo '{$tipo}' no existe.");
        }

        // 2. Seguridad: no permitir reducir UltimoNumero por debajo del mayor documento existente
        if ($tipo === 'RECEPCION') {
            $stmtCheck = $pdo->query("SELECT COUNT(*) FROM ST_Despostadora_Recepciones");
            $totalDoc = (int)($stmtCheck ? $stmtCheck->fetchColumn() : 0);
            if ($ultimoNumero < $totalDoc) {
                throw new RuntimeException("No puede fijar una numeración menor al total de recepciones ya registradas ({$totalDoc}).");
            }
        } elseif ($tipo === 'DESPOSTE') {
            $stmtCheck = $pdo->query("SELECT COUNT(*) FROM ST_Despostadora_Despostes");
            $totalDoc = (int)($stmtCheck ? $stmtCheck->fetchColumn() : 0);
            if ($ultimoNumero < $totalDoc) {
                throw new RuntimeException("No puede fijar una numeración menor al total de órdenes de desposte ya emitidas ({$totalDoc}).");
            }
        }

        $sqlUpd = "UPDATE ST_Despostadora_Correlativos 
                   SET Prefijo = :prefijo,
                       Longitud = :longitud,
                       UltimoNumero = :ultimonumero,
                       Activo = :activo,
                       FechaModif = SYSUTCDATETIME(),
                       CodUsuaModif = :codusua
                   WHERE Tipo = :tipo";

        $stmt = $pdo->prepare($sqlUpd);
        $stmt->bindValue(':prefijo', $prefijo, PDO::PARAM_STR);
        $stmt->bindValue(':longitud', $longitud, PDO::PARAM_INT);
        $stmt->bindValue(':ultimonumero', $ultimoNumero, PDO::PARAM_INT);
        $stmt->bindValue(':activo', $activo === 1 ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':codusua', $codusua, PDO::PARAM_STR);
        $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->execute();

        AuditService::log('CONFIGURACION', $tipo, 'MODIFICAR_CORRELATIVO', $codusua, [
            'tipo' => $tipo,
            'anterior' => $curr,
            'nuevo' => [
                'Prefijo' => $prefijo,
                'Longitud' => $longitud,
                'UltimoNumero' => $ultimoNumero,
                'Activo' => $activo
            ]
        ]);
    }

    /**
     * Obtiene el listado de especies configuradas con su producto de merma y datos de SAPROD.
     * @return list<array<string, mixed>>
     */
    public static function getSpeciesList(): array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT 
                    ec.CodProd,
                    ec.CodProd AS CodProdCanal,
                    LTRIM(RTRIM(CONCAT(p.Descrip, ' ', p.Descrip2, ' ', p.Descrip3))) AS NombreEspecie,
                    LTRIM(RTRIM(CONCAT(p.Descrip, ' ', p.Descrip2, ' ', p.Descrip3))) AS NombreCanal,
                    p.Unidad,
                    CAST(ISNULL(p.CostAct, 0) AS FLOAT) AS CostActCanal,
                    ec.CodProdMerma,
                    LTRIM(RTRIM(CONCAT(pm.Descrip, ' ', pm.Descrip2, ' ', pm.Descrip3))) AS NombreMerma,
                    CAST(ec.MargenUtilidadDefault AS FLOAT) AS MargenUtilidadDefault,
                    ec.DiasVencimientoRefrig,
                    ec.DiasVencimientoCongel,
                    ec.Activo,
                    ec.FechaRegistro,
                    (SELECT COUNT(*) FROM ST_Despostadora_EspeciesCortes c WHERE c.CodProdCanal = ec.CodProd AND c.Activo = 1) AS TotalCortes
                FROM ST_Despostadora_EspeciesConfig ec
                INNER JOIN SAPROD p ON p.CodProd = ec.CodProd
                LEFT JOIN SAPROD pm ON pm.CodProd = ec.CodProdMerma
                ORDER BY ec.FechaRegistro DESC";

        $stmt = $pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Guarda o actualiza una especie/canal de entrada.
     */
    public static function saveSpecies(
        string $codProd,
        string $codProdMerma,
        float $margen,
        int $diasRefrig,
        int $diasCongel,
        int $activo,
        string $codusua
    ): void {
        $codProd = trim($codProd);
        $codProdMerma = trim($codProdMerma);

        if ($codProd === '' || $codProdMerma === '') {
            throw new RuntimeException("Debe seleccionar tanto la especie en canal como el producto de merma.");
        }

        if ($codProd === $codProdMerma) {
            throw new RuntimeException("El producto de merma no puede ser el mismo producto de la canal.");
        }

        $pdo = Database::getConnection();
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM ST_Despostadora_EspeciesConfig WHERE CodProd = :c");
        $stmtCheck->bindValue(':c', $codProd, PDO::PARAM_STR);
        $stmtCheck->execute();
        $exists = (int)$stmtCheck->fetchColumn() > 0;

        if ($exists) {
            $stmt = $pdo->prepare("UPDATE ST_Despostadora_EspeciesConfig 
                                   SET CodProdMerma = :merma, 
                                       MargenUtilidadDefault = :margen,
                                       DiasVencimientoRefrig = :refrig,
                                       DiasVencimientoCongel = :congel,
                                       Activo = :activo
                                   WHERE CodProd = :codprod");
        } else {
            $stmt = $pdo->prepare("INSERT INTO ST_Despostadora_EspeciesConfig 
                                   (CodProd, CodProdMerma, MargenUtilidadDefault, DiasVencimientoRefrig, DiasVencimientoCongel, Activo, FechaRegistro)
                                   VALUES 
                                   (:codprod, :merma, :margen, :refrig, :congel, :activo, SYSUTCDATETIME())");
        }
        $stmt->bindValue(':codprod', $codProd, PDO::PARAM_STR);
        $stmt->bindValue(':merma', $codProdMerma, PDO::PARAM_STR);
        $stmt->bindValue(':margen', $margen, PDO::PARAM_STR);
        $stmt->bindValue(':refrig', max(1, $diasRefrig), PDO::PARAM_INT);
        $stmt->bindValue(':congel', max(1, $diasCongel), PDO::PARAM_INT);
        $stmt->bindValue(':activo', $activo === 1 ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();

        AuditService::log('CONFIGURACION', $codProd, 'GUARDAR_ESPECIE', $codusua, [
            'CodProd' => $codProd,
            'CodProdMerma' => $codProdMerma,
            'Margen' => $margen,
            'DiasRefrig' => $diasRefrig,
            'DiasCongel' => $diasCongel
        ]);
    }

    /**
     * Obtiene los cortes asociados a una especie con sus datos comerciales de SAPROD.
     * @return list<array<string, mixed>>
     */
    public static function getSpeciesCuts(string $codProdCanal): array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT 
                    ec.CodProdCanal,
                    ec.CodProdCorte,
                    LTRIM(RTRIM(CONCAT(p.Descrip, ' ', p.Descrip2, ' ', p.Descrip3))) AS NombreCorte,
                    p.Unidad,
                    CAST(ISNULL(p.CostAct, 0) AS FLOAT) AS CostActBase,
                    CAST(ISNULL(p.Precio1, 0) AS FLOAT) AS Precio1ERP,
                    CAST(ec.RendimientoEsperadoPct AS FLOAT) AS RendimientoEsperadoPct,
                    CAST(ec.MargenUtilidadPct AS FLOAT) AS MargenUtilidadPct,
                    ec.DiasVencimiento,
                    ec.Activo
                FROM ST_Despostadora_EspeciesCortes ec
                INNER JOIN SAPROD p ON p.CodProd = ec.CodProdCorte
                WHERE ec.CodProdCanal = :canal AND ec.Activo = 1
                ORDER BY p.Descrip ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':canal', trim($codProdCanal), PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Asocia un corte a una especie cárnica.
     */
    public static function addSpeciesCut(
        string $codProdCanal,
        string $codProdCorte,
        float $rendimiento,
        float $margen,
        int $diasVenc,
        string $codusua
    ): void {
        $codProdCanal = trim($codProdCanal);
        $codProdCorte = trim($codProdCorte);

        if ($codProdCanal === '' || $codProdCorte === '') {
            throw new RuntimeException("Debe seleccionar la especie y el corte.");
        }

        if ($codProdCanal === $codProdCorte) {
            throw new RuntimeException("El corte no puede ser el mismo producto de la canal.");
        }

        $pdo = Database::getConnection();

        // Validar que no sea el producto asignado a merma
        $stmtMerma = $pdo->prepare("SELECT CodProdMerma FROM ST_Despostadora_EspeciesConfig WHERE CodProd = :canal");
        $stmtMerma->bindValue(':canal', $codProdCanal, PDO::PARAM_STR);
        $stmtMerma->execute();
        $codMerma = (string)$stmtMerma->fetchColumn();

        if ($codProdCorte === $codMerma) {
            throw new RuntimeException("El producto seleccionado ya está asignado como la Merma de esta especie.");
        }

        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM ST_Despostadora_EspeciesCortes WHERE CodProdCanal = :c1 AND CodProdCorte = :c2");
        $stmtCheck->bindValue(':c1', $codProdCanal, PDO::PARAM_STR);
        $stmtCheck->bindValue(':c2', $codProdCorte, PDO::PARAM_STR);
        $stmtCheck->execute();
        $exists = (int)$stmtCheck->fetchColumn() > 0;

        if ($exists) {
            $stmt = $pdo->prepare("UPDATE ST_Despostadora_EspeciesCortes
                                   SET RendimientoEsperadoPct = :rend,
                                       MargenUtilidadPct = :margen,
                                       DiasVencimiento = :dias,
                                       Activo = 1
                                   WHERE CodProdCanal = :canal AND CodProdCorte = :corte");
        } else {
            $stmt = $pdo->prepare("INSERT INTO ST_Despostadora_EspeciesCortes
                                   (CodProdCanal, CodProdCorte, RendimientoEsperadoPct, MargenUtilidadPct, DiasVencimiento, Activo)
                                   VALUES
                                   (:canal, :corte, :rend, :margen, :dias, 1)");
        }
        $stmt->bindValue(':canal', $codProdCanal, PDO::PARAM_STR);
        $stmt->bindValue(':corte', $codProdCorte, PDO::PARAM_STR);
        $stmt->bindValue(':rend', $rendimiento, PDO::PARAM_STR);
        $stmt->bindValue(':margen', $margen, PDO::PARAM_STR);
        $stmt->bindValue(':dias', max(1, $diasVenc), PDO::PARAM_INT);
        $stmt->execute();

        AuditService::log('CONFIGURACION', $codProdCanal, 'AGREGAR_CORTE', $codusua, [
            'CodProdCanal' => $codProdCanal,
            'CodProdCorte' => $codProdCorte,
            'Rendimiento' => $rendimiento,
            'Margen' => $margen
        ]);
    }

    /**
     * Desvincula un corte de una especie.
     */
    public static function removeSpeciesCut(string $codProdCanal, string $codProdCorte, string $codusua): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM ST_Despostadora_EspeciesCortes WHERE CodProdCanal = :canal AND CodProdCorte = :corte");
        $stmt->bindValue(':canal', trim($codProdCanal), PDO::PARAM_STR);
        $stmt->bindValue(':corte', trim($codProdCorte), PDO::PARAM_STR);
        $stmt->execute();

        AuditService::log('CONFIGURACION', $codProdCanal, 'ELIMINAR_CORTE', $codusua, [
            'CodProdCanal' => $codProdCanal,
            'CodProdCorte' => $codProdCorte
        ]);
    }

    /**
     * Obtiene el listado de mesas o líneas operativas configuradas.
     * @return list<array{ID: int, Nombre: string, Descripcion: ?string, Activo: int, FechaRegistro: string}>
     */
    public static function getMesas(bool $onlyActive = false): array
    {
        $pdo = Database::getConnection();
        try {
            $sql = "SELECT ID, Nombre, Descripcion, Activo, FechaRegistro FROM ST_Despostadora_Mesas";
            if ($onlyActive) {
                $sql .= " WHERE Activo = 1";
            }
            $sql .= " ORDER BY Nombre ASC";
            $stmt = $pdo->query($sql);
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Registra o actualiza una mesa o línea de desposte.
     */
    public static function saveMesa(?int $id, string $nombre, ?string $descripcion, int $activo, string $codusua): void
    {
        $nombre = trim($nombre);
        $descripcion = $descripcion !== null ? trim($descripcion) : null;

        if ($nombre === '') {
            throw new RuntimeException("El identificador o nombre de la mesa es requerido.");
        }

        $pdo = Database::getConnection();

        // Validar unicidad del nombre
        if ($id !== null && $id > 0) {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM ST_Despostadora_Mesas WHERE Nombre = :nombre AND ID != :id");
            $stmtCheck->bindValue(':id', $id, PDO::PARAM_INT);
        } else {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM ST_Despostadora_Mesas WHERE Nombre = :nombre");
        }
        $stmtCheck->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmtCheck->execute();
        if ((int)$stmtCheck->fetchColumn() > 0) {
            throw new RuntimeException("Ya existe una mesa o línea configurada con el nombre '{$nombre}'.");
        }

        if ($id !== null && $id > 0) {
            $stmt = $pdo->prepare("UPDATE ST_Despostadora_Mesas 
                                   SET Nombre = :nombre, Descripcion = :desc, Activo = :activo 
                                   WHERE ID = :id");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $action = 'EDITAR_MESA';
        } else {
            $stmt = $pdo->prepare("INSERT INTO ST_Despostadora_Mesas (Nombre, Descripcion, Activo, FechaRegistro) 
                                   VALUES (:nombre, :desc, :activo, SYSUTCDATETIME())");
            $action = 'CREAR_MESA';
        }

        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':desc', $descripcion !== '' ? $descripcion : null, $descripcion !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':activo', $activo === 1 ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();

        AuditService::log('CONFIGURACION', $nombre, $action, $codusua, [
            'id' => $id,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'activo' => $activo
        ]);
    }

    /**
     * Alterna el estado activo/inactivo de una mesa.
     */
    public static function toggleMesaStatus(int $id, int $activo, string $codusua): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE ST_Despostadora_Mesas SET Activo = :activo WHERE ID = :id");
        $stmt->bindValue(':activo', $activo === 1 ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        AuditService::log('CONFIGURACION', (string)$id, 'CAMBIAR_ESTADO_MESA', $codusua, [
            'id' => $id,
            'nuevo_activo' => $activo === 1 ? 1 : 0
        ]);
    }

    /**
     * Elimina una mesa si no posee órdenes de desposte asociadas.
     */
    public static function deleteMesa(int $id, string $codusua): void
    {
        $pdo = Database::getConnection();
        $stmtSel = $pdo->prepare("SELECT Nombre FROM ST_Despostadora_Mesas WHERE ID = :id");
        $stmtSel->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtSel->execute();
        $nombre = $stmtSel->fetchColumn();

        if ($nombre === false) {
            throw new RuntimeException("La mesa no existe.");
        }

        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM ST_Despostadora_Despostes WHERE LineaMesa = :nombre");
        $stmtCheck->bindValue(':nombre', (string)$nombre, PDO::PARAM_STR);
        $stmtCheck->execute();
        $usos = (int)$stmtCheck->fetchColumn();

        if ($usos > 0) {
            throw new RuntimeException("No se puede eliminar la mesa '{$nombre}' porque está vinculada a {$usos} orden(es) de desposte. Puede inactivarla en su lugar.");
        }

        $stmtDel = $pdo->prepare("DELETE FROM ST_Despostadora_Mesas WHERE ID = :id");
        $stmtDel->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtDel->execute();

        AuditService::log('CONFIGURACION', (string)$nombre, 'ELIMINAR_MESA', $codusua, [
            'id' => $id,
            'nombre' => $nombre
        ]);
    }
}
