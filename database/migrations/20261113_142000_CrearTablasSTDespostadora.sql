-- ==============================================================================
-- Migración: 20261113_142000_CrearTablasSTDespostadora.sql
-- Propósito: Estructuras base para el sistema de desposte y procesamiento cárnico.
-- Prefijo estricto: ST_Despostadora_
-- ==============================================================================

-- 1. Tabla de Correlativos Configurables
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ST_Despostadora_Correlativos')
BEGIN
    CREATE TABLE ST_Despostadora_Correlativos (
        Tipo VARCHAR(30) NOT NULL PRIMARY KEY,
        Descripcion VARCHAR(100) NOT NULL,
        Prefijo VARCHAR(10) NOT NULL,
        UltimoNumero BIGINT NOT NULL DEFAULT 0,
        Longitud INT NOT NULL DEFAULT 20,
        Activo BIT NOT NULL DEFAULT 1,
        FechaModif DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        CodUsuaModif VARCHAR(20) NOT NULL DEFAULT 'SYSTEM'
    );

    -- Semillas iniciales para los 4 correlativos del sistema
    INSERT INTO ST_Despostadora_Correlativos (Tipo, Descripcion, Prefijo, UltimoNumero, Longitud, Activo, CodUsuaModif)
    VALUES 
        ('RECEPCION', 'Recepción de Canales / Especies', 'REC-', 0, 20, 1, 'SYSTEM'),
        ('DESPOSTE', 'Órdenes de Desposte', 'DESP-', 0, 20, 1, 'SYSTEM'),
        ('TRANSFERENCIA', 'Órdenes de Transferencia / Cargo', 'TRF-', 0, 20, 1, 'SYSTEM'),
        ('LOTE', 'Regla de Lotes de Producción', 'LOT-', 0, 20, 1, 'SYSTEM');
END;
GO

-- 2. Tabla de Configuración Operativa General
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ST_Despostadora_Configuracion')
BEGIN
    CREATE TABLE ST_Despostadora_Configuracion (
        Clave VARCHAR(50) NOT NULL PRIMARY KEY,
        Valor VARCHAR(255) NOT NULL,
        FechaModif DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        CodUsua VARCHAR(20) NOT NULL DEFAULT 'SYSTEM'
    );

    INSERT INTO ST_Despostadora_Configuracion (Clave, Valor, CodUsua)
    VALUES 
        ('DEFAULT_CODUBIC', '', 'SYSTEM'),
        ('LOTE_PREFIJO', 'LOT-', 'SYSTEM'),
        ('LOTE_FORMATO_FECHA', 'AAAAMMDD', 'SYSTEM'),
        ('METODO_MERMA_DEFAULT', 'PONDERADO_VALOR_COMERCIAL', 'SYSTEM'),
        ('MARGEN_UTILIDAD_DEFAULT', '30.00', 'SYSTEM');
END;
GO

-- 3. Configuración de Especies / Canales de Entrada
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ST_Despostadora_EspeciesConfig')
BEGIN
    CREATE TABLE ST_Despostadora_EspeciesConfig (
        CodProd VARCHAR(15) NOT NULL PRIMARY KEY,
        CodProdMerma VARCHAR(15) NOT NULL,
        MargenUtilidadDefault DECIMAL(18,2) NOT NULL DEFAULT 30.00,
        DiasVencimientoRefrig INT NOT NULL DEFAULT 15,
        DiasVencimientoCongel INT NOT NULL DEFAULT 180,
        Activo BIT NOT NULL DEFAULT 1,
        FechaRegistro DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
    );
END;
GO

-- 4. Matriz de Cortes Permitidos por Especie
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ST_Despostadora_EspeciesCortes')
BEGIN
    CREATE TABLE ST_Despostadora_EspeciesCortes (
        CodProdCanal VARCHAR(15) NOT NULL,
        CodProdCorte VARCHAR(15) NOT NULL,
        RendimientoEsperadoPct DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        MargenUtilidadPct DECIMAL(18,2) NOT NULL DEFAULT 30.00,
        DiasVencimiento INT NOT NULL DEFAULT 15,
        Activo BIT NOT NULL DEFAULT 1,
        CONSTRAINT PK_ST_Despostadora_EspeciesCortes PRIMARY KEY (CodProdCanal, CodProdCorte)
    );
END;
GO

-- 5. Encabezado de Recepción de Especies / Canales
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ST_Despostadora_Recepciones')
BEGIN
    CREATE TABLE ST_Despostadora_Recepciones (
        NroRecepcion VARCHAR(20) NOT NULL PRIMARY KEY,
        FechaHora DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        CodUsua VARCHAR(20) NOT NULL,
        CodUbic VARCHAR(10) NOT NULL,
        CodProdCanal VARCHAR(15) NOT NULL,
        CostActCanal DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        MetodoEntrada VARCHAR(20) NOT NULL,
        CantidadEspecies INT NOT NULL DEFAULT 0,
        PesoTotalBruto DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        TaraTotal DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        PesoTotalNeto DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        CostoTotalNeto DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        PesoDisponible DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        Estado VARCHAR(20) NOT NULL DEFAULT 'DISPONIBLE',
        Observaciones VARCHAR(255) NULL
    );
    CREATE INDEX IX_ST_Despostadora_Recepciones_Estado ON ST_Despostadora_Recepciones(Estado, CodProdCanal);
END;
GO

-- 6. Detalle de Recepción para Pesaje Individual Pieza a Pieza
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ST_Despostadora_RecepcionesDetalle')
BEGIN
    CREATE TABLE ST_Despostadora_RecepcionesDetalle (
        ID BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        NroRecepcion VARCHAR(20) NOT NULL,
        NroEspecie INT NOT NULL,
        PesoNeto DECIMAL(18,4) NOT NULL,
        CostoEspecie DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        EstadoDesposte VARCHAR(20) NOT NULL DEFAULT 'DISPONIBLE',
        NroOrdenDesposte VARCHAR(20) NULL
    );
    CREATE INDEX IX_ST_Despostadora_RecepcionesDetalle_Rec ON ST_Despostadora_RecepcionesDetalle(NroRecepcion, EstadoDesposte);
END;
GO

-- 7. Encabezado de Órdenes de Desposte Multi-Línea
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ST_Despostadora_Despostes')
BEGIN
    CREATE TABLE ST_Despostadora_Despostes (
        NroOrdenDesposte VARCHAR(20) NOT NULL PRIMARY KEY,
        NroRecepcion VARCHAR(20) NOT NULL,
        LineaMesa VARCHAR(50) NOT NULL DEFAULT 'Mesa 1',
        FechaHoraInicio DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        FechaHoraCierre DATETIME2 NULL,
        CodUsua VARCHAR(20) NOT NULL,
        CodProdCanal VARCHAR(15) NOT NULL,
        CostActCanal DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        CantidadProcesada INT NOT NULL DEFAULT 1,
        PesoCanalEntrada DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        CostoTotalCanal DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        PesoTotalCortes DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        PesoMerma DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        CostoPerdidaMerma DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        RendimientoPct DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        MermaPct DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        MetodoDistribucionCosto VARCHAR(30) NOT NULL DEFAULT 'PONDERADO_VALOR_COMERCIAL',
        Estado VARCHAR(20) NOT NULL DEFAULT 'EN_PROCESO',
        Observaciones VARCHAR(255) NULL
    );
    CREATE INDEX IX_ST_Despostadora_Despostes_Estado ON ST_Despostadora_Despostes(Estado);
END;
GO

-- 8. Detalle de Cortes Pesados con Asignación Automática de Lote
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ST_Despostadora_DespostesDetalle')
BEGIN
    CREATE TABLE ST_Despostadora_DespostesDetalle (
        ID BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        NroOrdenDesposte VARCHAR(20) NOT NULL,
        NroItem INT NOT NULL,
        NroLote VARCHAR(30) NOT NULL,
        CodProdCorte VARCHAR(15) NOT NULL,
        PesoNeto DECIMAL(18,4) NOT NULL,
        RendimientoCortePct DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        CostActBase DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        CostoMermaAbsorbidoKg DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        CostoRealUnitario DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        CostoRealTotal DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        MargenUtilidadPct DECIMAL(18,2) NOT NULL DEFAULT 30.00,
        PrecioVentaSugerido DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        FechaElaboracion DATE NOT NULL,
        FechaVencimiento DATE NOT NULL,
        FechaHoraPesaje DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
    );
    CREATE INDEX IX_ST_Despostadora_DespostesDetalle_Ord ON ST_Despostadora_DespostesDetalle(NroOrdenDesposte);
    CREATE INDEX IX_ST_Despostadora_DespostesDetalle_Lote ON ST_Despostadora_DespostesDetalle(NroLote);
END;
GO

-- 9. Control Granular de Existencias Internas por Lote
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ST_Despostadora_Existencias')
BEGIN
    CREATE TABLE ST_Despostadora_Existencias (
        CodUbic VARCHAR(10) NOT NULL,
        CodProd VARCHAR(15) NOT NULL,
        NroLote VARCHAR(30) NOT NULL,
        TipoExistencia VARCHAR(10) NOT NULL DEFAULT 'CORTE',
        StockActual DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        StockComprometido DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        CostoUnitarioReal DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
        FechaElaboracion DATE NULL,
        FechaVencimiento DATE NULL,
        UltimaActualizacion DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        CONSTRAINT PK_ST_Despostadora_Existencias PRIMARY KEY (CodUbic, CodProd, NroLote)
    );
    CREATE INDEX IX_ST_Despostadora_Existencias_Prod ON ST_Despostadora_Existencias(CodProd, StockActual);
END;
GO

-- 10. Log Inmutable de Trazabilidad y Auditoría
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ST_Despostadora_Auditoria')
BEGIN
    CREATE TABLE ST_Despostadora_Auditoria (
        ID BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        Modulo VARCHAR(30) NOT NULL,
        DocumentoID VARCHAR(30) NOT NULL,
        Accion VARCHAR(30) NOT NULL,
        CodUsua VARCHAR(20) NOT NULL,
        FechaHora DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        IP VARCHAR(45) NOT NULL DEFAULT '127.0.0.1',
        DetalleJSON NVARCHAR(MAX) NULL
    );
    CREATE INDEX IX_ST_Despostadora_Auditoria_Doc ON ST_Despostadora_Auditoria(Modulo, DocumentoID);
END;
GO
