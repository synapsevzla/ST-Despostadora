-- ==============================================================================
-- Migración: 20261115_100000_CrearIndicesAuditoria.sql
-- Propósito: Optimizar el rendimiento de consultas forenses y paginación en el
--            Módulo de Auditoría de ST Despostadora mediante índices específicos
--            en ST_Despostadora_Auditoria y ST_Despostadora_DespostesCortesLog.
-- Prefijo estricto: ST_Despostadora_
-- ==============================================================================

-- 1. Índice por FechaHora descendente y Módulo en ST_Despostadora_Auditoria
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Auditoria') 
      AND name = 'IX_ST_Despostadora_Auditoria_FechaMod'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_ST_Despostadora_Auditoria_FechaMod
    ON ST_Despostadora_Auditoria (FechaHora DESC, Modulo)
    INCLUDE (DocumentoID, Accion, CodUsua, IP);
END;
GO

-- 2. Índice por CodUsua en ST_Despostadora_Auditoria
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Auditoria') 
      AND name = 'IX_ST_Despostadora_Auditoria_Usua'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_ST_Despostadora_Auditoria_Usua
    ON ST_Despostadora_Auditoria (CodUsua, FechaHora DESC)
    INCLUDE (Modulo, Accion);
END;
GO

-- 3. Índice por Accion en ST_Despostadora_Auditoria
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Auditoria') 
      AND name = 'IX_ST_Despostadora_Auditoria_Accion'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_ST_Despostadora_Auditoria_Accion
    ON ST_Despostadora_Auditoria (Accion, FechaHora DESC);
END;
GO

-- 4. Índice por FechaHora descendente en ST_Despostadora_DespostesCortesLog
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes 
    WHERE object_id = OBJECT_ID('ST_Despostadora_DespostesCortesLog') 
      AND name = 'IX_ST_Despostadora_DespostesCortesLog_Fecha'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_ST_Despostadora_DespostesCortesLog_Fecha
    ON ST_Despostadora_DespostesCortesLog (FechaHora DESC)
    INCLUDE (NroOrdenDesposte, IDDetalle, NroLote, CodProdCorte, TipoAccion, DiferenciaPeso, CodUsua);
END;
GO
