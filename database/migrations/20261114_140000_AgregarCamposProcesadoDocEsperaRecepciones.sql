-- ==============================================================================
-- Migración: 20261114_140000_AgregarCamposProcesadoDocEsperaRecepciones.sql
-- Propósito: Columnas para persistir el estado PROCESADO oficial en Saint ERP
--            y registrar el consecutivo definitivo (NumeroD) y TipoOpI final.
-- ==============================================================================

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Recepciones') 
      AND name = 'DocEsperaProcesado'
)
BEGIN
    ALTER TABLE ST_Despostadora_Recepciones 
    ADD DocEsperaProcesado BIT NOT NULL CONSTRAINT DF_ST_Despostadora_Recepciones_DocEspProc DEFAULT 0;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Recepciones') 
      AND name = 'DocEsperaNumeroDOficial'
)
BEGIN
    ALTER TABLE ST_Despostadora_Recepciones 
    ADD DocEsperaNumeroDOficial VARCHAR(20) NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Recepciones') 
      AND name = 'FechaDocEsperaProcesado'
)
BEGIN
    ALTER TABLE ST_Despostadora_Recepciones 
    ADD FechaDocEsperaProcesado DATETIME2 NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('ST_Despostadora_Recepciones') 
      AND name = 'DocEsperaTipoOpIFinal'
)
BEGIN
    ALTER TABLE ST_Despostadora_Recepciones 
    ADD DocEsperaTipoOpIFinal VARCHAR(5) NULL;
END;
GO
