-- ==============================================================================
-- Migración: 20261114_150000_AgregarControlDocEsperaDespostes.sql
-- Propósito: Agregar columnas de control para los dos documentos en espera
--            de desposte en Saint ERP (Descargo de Canal y Cargo de Cortes),
--            y estado del cruce de operaciones.
-- ==============================================================================

-- 1. Control del Documento de Descargo (Salida de Especie / Canal)
IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') 
      AND name = 'DocDescargoEnviado'
)
BEGIN
    ALTER TABLE ST_Despostadora_Despostes 
    ADD DocDescargoEnviado BIT NOT NULL CONSTRAINT DF_ST_Desp_DocDescEnv DEFAULT 0;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') 
      AND name = 'DocDescargoNumeroD'
)
BEGIN
    ALTER TABLE ST_Despostadora_Despostes 
    ADD DocDescargoNumeroD VARCHAR(20) NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') 
      AND name = 'DocDescargoProcesado'
)
BEGIN
    ALTER TABLE ST_Despostadora_Despostes 
    ADD DocDescargoProcesado BIT NOT NULL CONSTRAINT DF_ST_Desp_DocDescProc DEFAULT 0;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') 
      AND name = 'DocDescargoNumeroDOficial'
)
BEGIN
    ALTER TABLE ST_Despostadora_Despostes 
    ADD DocDescargoNumeroDOficial VARCHAR(20) NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') 
      AND name = 'DocDescargoTipoOpIFinal'
)
BEGIN
    ALTER TABLE ST_Despostadora_Despostes 
    ADD DocDescargoTipoOpIFinal VARCHAR(5) NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') 
      AND name = 'FechaDocDescargoProcesado'
)
BEGIN
    ALTER TABLE ST_Despostadora_Despostes 
    ADD FechaDocDescargoProcesado DATETIME2 NULL;
END;
GO

-- 2. Control del Documento de Cargo (Entrada de Cortes Terminados)
IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') 
      AND name = 'DocCargoEnviado'
)
BEGIN
    ALTER TABLE ST_Despostadora_Despostes 
    ADD DocCargoEnviado BIT NOT NULL CONSTRAINT DF_ST_Desp_DocCargEnv DEFAULT 0;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') 
      AND name = 'DocCargoNumeroD'
)
BEGIN
    ALTER TABLE ST_Despostadora_Despostes 
    ADD DocCargoNumeroD VARCHAR(20) NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') 
      AND name = 'DocCargoProcesado'
)
BEGIN
    ALTER TABLE ST_Despostadora_Despostes 
    ADD DocCargoProcesado BIT NOT NULL CONSTRAINT DF_ST_Desp_DocCargProc DEFAULT 0;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') 
      AND name = 'DocCargoNumeroDOficial'
)
BEGIN
    ALTER TABLE ST_Despostadora_Despostes 
    ADD DocCargoNumeroDOficial VARCHAR(20) NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') 
      AND name = 'DocCargoTipoOpIFinal'
)
BEGIN
    ALTER TABLE ST_Despostadora_Despostes 
    ADD DocCargoTipoOpIFinal VARCHAR(5) NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') 
      AND name = 'FechaDocCargoProcesado'
)
BEGIN
    ALTER TABLE ST_Despostadora_Despostes 
    ADD FechaDocCargoProcesado DATETIME2 NULL;
END;
GO

-- 3. Estado Consolidado del Cruce ERP
IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') 
      AND name = 'EstadoCruceERP'
)
BEGIN
    ALTER TABLE ST_Despostadora_Despostes 
    ADD EstadoCruceERP VARCHAR(30) NOT NULL CONSTRAINT DF_ST_Desp_EstCruce DEFAULT 'PENDIENTE_CIERRE';
END;
GO

-- 4. Claves de Configuración para Códigos de Operación de Desposte
IF NOT EXISTS (SELECT 1 FROM ST_Despostadora_Configuracion WHERE Clave = 'ERP_CODOPER_DESCARGO_DESPOSTE')
BEGIN
    INSERT INTO ST_Despostadora_Configuracion (Clave, Valor, CodUsua)
    VALUES ('ERP_CODOPER_DESCARGO_DESPOSTE', 'PRO001', 'SYSTEM');
END;
GO

IF NOT EXISTS (SELECT 1 FROM ST_Despostadora_Configuracion WHERE Clave = 'ERP_CODOPER_CARGO_DESPOSTE')
BEGIN
    INSERT INTO ST_Despostadora_Configuracion (Clave, Valor, CodUsua)
    VALUES ('ERP_CODOPER_CARGO_DESPOSTE', 'PRO002', 'SYSTEM');
END;
GO
