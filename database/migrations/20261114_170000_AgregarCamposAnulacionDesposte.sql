-- ==============================================================================
-- Migración: 20261114_170000_AgregarCamposAnulacionDesposte.sql
-- Propósito: Agregar columnas de auditoría y motivo para la anulación de órdenes
--            de desposte en ST_Despostadora_Despostes.
-- Prefijo estricto: ST_Despostadora_
-- ==============================================================================

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') 
      AND name = 'MotivoAnulacion'
)
BEGIN
    ALTER TABLE ST_Despostadora_Despostes 
    ADD MotivoAnulacion VARCHAR(255) NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') 
      AND name = 'FechaHoraAnulacion'
)
BEGIN
    ALTER TABLE ST_Despostadora_Despostes 
    ADD FechaHoraAnulacion DATETIME2 NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') 
      AND name = 'CodUsuaAnulacion'
)
BEGIN
    ALTER TABLE ST_Despostadora_Despostes 
    ADD CodUsuaAnulacion VARCHAR(20) NULL;
END;
GO
