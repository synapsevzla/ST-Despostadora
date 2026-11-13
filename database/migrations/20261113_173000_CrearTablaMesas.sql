-- ==============================================================================
-- Migración: 20261113_173000_CrearTablaMesas.sql
-- Propósito: Tabla para configuración de mesas/líneas operativas de desposte.
-- Prefijo estricto: ST_Despostadora_
-- ==============================================================================

-- 1. Tabla de Mesas / Líneas de Desposte Configurables
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ST_Despostadora_Mesas')
BEGIN
    CREATE TABLE ST_Despostadora_Mesas (
        ID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        Nombre VARCHAR(50) NOT NULL UNIQUE,
        Descripcion VARCHAR(150) NULL,
        Activo BIT NOT NULL DEFAULT 1,
        FechaRegistro DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
    );
END;
GO

-- 2. Permitir NULL o vacío en LineaMesa de ST_Despostadora_Despostes para cuando no se configuren mesas
IF EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('ST_Despostadora_Despostes') AND name = 'LineaMesa')
BEGIN
    ALTER TABLE ST_Despostadora_Despostes ALTER COLUMN LineaMesa VARCHAR(50) NULL;
END;
GO
