-- ==============================================================================
-- Migración: 20261114_160000_AgregarControlMultiUsuarioYTrazabilidadDesposte.sql
-- Propósito: Columnas de versionado y concurrencia multi-usuario, auditoría de
--            autoría en cortes y tabla inmutable de log de trazabilidad de pesajes.
-- Prefijo estricto: ST_Despostadora_
-- ==============================================================================

-- 1. Campos de control de versión y concurrencia en cabecera de Despostes
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') AND name = 'Version')
BEGIN
    ALTER TABLE ST_Despostadora_Despostes ADD Version BIGINT NOT NULL DEFAULT 1;
END;
GO

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') AND name = 'UltimaModif')
BEGIN
    ALTER TABLE ST_Despostadora_Despostes ADD UltimaModif DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME();
END;
GO

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') AND name = 'UltimoUsuarioModif')
BEGIN
    ALTER TABLE ST_Despostadora_Despostes ADD UltimoUsuarioModif VARCHAR(20) NULL;
END;
GO

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') AND name = 'CodUsuaCierre')
BEGIN
    ALTER TABLE ST_Despostadora_Despostes ADD CodUsuaCierre VARCHAR(20) NULL;
END;
GO

-- 2. Campos de autoría y auditoría en detalle de Cortes
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('ST_Despostadora_DespostesDetalle') AND name = 'CodUsuaCreacion')
BEGIN
    ALTER TABLE ST_Despostadora_DespostesDetalle ADD CodUsuaCreacion VARCHAR(20) NOT NULL DEFAULT 'SYSTEM';
END;
GO

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('ST_Despostadora_DespostesDetalle') AND name = 'FechaHoraCreacion')
BEGIN
    ALTER TABLE ST_Despostadora_DespostesDetalle ADD FechaHoraCreacion DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME();
END;
GO

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('ST_Despostadora_DespostesDetalle') AND name = 'CodUsuaModif')
BEGIN
    ALTER TABLE ST_Despostadora_DespostesDetalle ADD CodUsuaModif VARCHAR(20) NULL;
END;
GO

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('ST_Despostadora_DespostesDetalle') AND name = 'FechaHoraModif')
BEGIN
    ALTER TABLE ST_Despostadora_DespostesDetalle ADD FechaHoraModif DATETIME2 NULL;
END;
GO

-- 3. Tabla inmutable de log de trazabilidad y auditoría de pesajes
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ST_Despostadora_DespostesCortesLog')
BEGIN
    CREATE TABLE ST_Despostadora_DespostesCortesLog (
        ID BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        NroOrdenDesposte VARCHAR(20) NOT NULL,
        IDDetalle BIGINT NOT NULL,
        NroLote VARCHAR(30) NOT NULL,
        CodProdCorte VARCHAR(15) NOT NULL,
        TipoAccion VARCHAR(20) NOT NULL, -- PESAJE_INICIAL, MODIFICACION_PESO, ANULACION
        PesoAnterior DECIMAL(18,4) NULL,
        PesoNuevo DECIMAL(18,4) NULL,
        DiferenciaPeso DECIMAL(18,4) NULL,
        Motivo VARCHAR(255) NULL,
        CodUsua VARCHAR(20) NOT NULL,
        IP VARCHAR(45) NOT NULL DEFAULT '127.0.0.1',
        FechaHora DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
    );

    CREATE INDEX IX_ST_Despostadora_DespostesCortesLog_Ord 
        ON ST_Despostadora_DespostesCortesLog(NroOrdenDesposte, FechaHora DESC);

    CREATE INDEX IX_ST_Despostadora_DespostesCortesLog_Det 
        ON ST_Despostadora_DespostesCortesLog(IDDetalle);
END;
GO
