-- ==============================================================================
-- Migración: 20261114_130000_AgregarControlDocEsperaRecepciones.sql
-- Propósito: Agregar columnas de control para el Documento en Espera de Inventario
--            en Saint ERP (SAOPEI / SAITEMOPI) y configuraciones por defecto.
-- ==============================================================================

-- 1. Agregar columnas de control en ST_Despostadora_Recepciones
IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Recepciones') 
      AND name = 'DocEsperaEnviado'
)
BEGIN
    ALTER TABLE ST_Despostadora_Recepciones 
    ADD DocEsperaEnviado BIT NOT NULL CONSTRAINT DF_ST_Despostadora_Recepciones_DocEspEnv DEFAULT 0;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Recepciones') 
      AND name = 'DocEsperaNumeroD'
)
BEGIN
    ALTER TABLE ST_Despostadora_Recepciones 
    ADD DocEsperaNumeroD VARCHAR(20) NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Recepciones') 
      AND name = 'FechaDocEspera'
)
BEGIN
    ALTER TABLE ST_Despostadora_Recepciones 
    ADD FechaDocEspera DATETIME2 NULL;
END;
GO

-- 2. Claves de Configuración para Documentos en Espera ERP
IF NOT EXISTS (SELECT 1 FROM ST_Despostadora_Configuracion WHERE Clave = 'ERP_CODOPER_RECEPCION')
BEGIN
    INSERT INTO ST_Despostadora_Configuracion (Clave, Valor, CodUsua)
    VALUES ('ERP_CODOPER_RECEPCION', 'CMP', 'SYSTEM');
END;
GO

IF NOT EXISTS (SELECT 1 FROM ST_Despostadora_Configuracion WHERE Clave = 'ERP_CODESTA_DEFAULT')
BEGIN
    INSERT INTO ST_Despostadora_Configuracion (Clave, Valor, CodUsua)
    VALUES ('ERP_CODESTA_DEFAULT', 'DESPOSTE', 'SYSTEM');
END;
GO

IF NOT EXISTS (SELECT 1 FROM ST_Despostadora_Configuracion WHERE Clave = 'ERP_AUTORI_PREFIJO')
BEGIN
    INSERT INTO ST_Despostadora_Configuracion (Clave, Valor, CodUsua)
    VALUES ('ERP_AUTORI_PREFIJO', 'DOC.ESP-', 'SYSTEM');
END;
GO
