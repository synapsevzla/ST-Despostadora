-- ==============================================================================
-- Migración: 20261115_120000_AgregarCampoData1ConfiguracionLicencia.sql
-- Propósito: Agregar columna DATA1 a ST_Despostadora_Configuracion para
--            almacenar la licencia cifrada y auditoría de ejecución local.
-- ==============================================================================

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Configuracion') 
    AND name = 'DATA1'
)
BEGIN
    ALTER TABLE ST_Despostadora_Configuracion ADD DATA1 VARCHAR(MAX) NULL;
END;
GO

IF NOT EXISTS (SELECT 1 FROM ST_Despostadora_Configuracion WHERE Clave = 'DATA1')
BEGIN
    INSERT INTO ST_Despostadora_Configuracion (Clave, Valor, DATA1, CodUsua)
    VALUES ('DATA1', 'LICENCIA_CENTRALIZADA', NULL, 'SYSTEM');
END;
GO
