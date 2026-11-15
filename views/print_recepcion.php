<?php
declare(strict_types=1);

use Config\Security;
use Config\Assets;
use Src\Utils\BarcodeService;

/**
 * Vista de impresión optimizada para Hoja Carta (Letter).
 * Comprobante de Recepción de Carne en Canal con Cruce Directo Saint ERP.
 *
 * @var array<string, mixed> $data Datos retornados por ReceptionService::getReceptionPrintData
 * @var bool $autoprint Si dispara automáticamente el diálogo de impresión
 */

$rec = $data['recepcion'] ?? [];
$emp = $data['empresa'] ?? [];
$piezas = $data['piezas'] ?? [];
$erp = $data['erp'] ?? [];

$nroRec = (string)($rec['NroRecepcion'] ?? '');
$barcodeSvg = BarcodeService::generateCode128Svg($nroRec, 32, 1, false);
$autoprint = !empty($autoprint) || (!empty($_GET['autoprint']) && $_GET['autoprint'] === '1');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recepción <?= Security::escape($nroRec) ?> - ST Despostadora</title>
    <link rel="icon" type="image/svg+xml" href="<?= Assets::faviconDataUri() ?>">
    <style>
        @page { size: letter portrait; margin: 8mm 10mm; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; font-size: 8pt; line-height: 1.2; color: #111; background: #e5e5e5; }
        .sheet-container { width: 215.9mm; min-height: 279.4mm; margin: 10px auto; background: #fff; padding: 8mm 10mm; box-shadow: 0 4px 15px rgba(0,0,0,0.15); position: relative; }
        @media print {
            body { background: #fff !important; }
            .sheet-container { width: 100% !important; min-height: 0 !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; }
            .no-print { display: none !important; }
        }
        .print-toolbar { position: fixed; top: 10px; left: 50%; transform: translateX(-50%); z-index: 1000; background: #2b201a; color: #fff; padding: 8px 16px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); display: flex; align-items: center; gap: 12px; }
        .btn-toolbar { background: #e86339; color: #fff; border: none; padding: 6px 14px; border-radius: 4px; font-weight: 700; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .btn-toolbar:hover { background: #d0532b; }
        .btn-toolbar-close { background: transparent; color: #ccc; border: 1px solid #555; padding: 5px 12px; border-radius: 4px; font-size: 13px; cursor: pointer; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 5px; border-bottom: 2px solid #222; padding-bottom: 4px; }
        .company-title { font-size: 11pt; font-weight: 800; color: #111; margin: 0; text-transform: uppercase; }
        .company-subtitle { font-size: 7.5pt; color: #444; margin: 1px 0 0 0; }
        .doc-title { font-size: 12pt; font-weight: 800; color: #a02800; margin: 0; text-align: right; text-transform: uppercase; }
        .doc-number { font-family: monospace, Courier, monospace; font-size: 11pt; font-weight: 700; color: #111; text-align: right; }
        .erp-box { border: 1.5px solid #222; background: #fdfdfd; border-radius: 4px; padding: 5px 8px; margin-bottom: 6px; break-inside: avoid; }
        .erp-box-header { font-size: 7.5pt; font-weight: 800; text-transform: uppercase; color: #a02800; border-bottom: 1px dashed #aaa; padding-bottom: 2px; margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center; }
        .erp-grid { display: grid; grid-template-columns: 2fr 1.2fr; gap: 8px; align-items: center; }
        .erp-details { display: grid; grid-template-columns: 1fr 1fr; gap: 3px 8px; font-size: 7.5pt; }
        .erp-label { color: #555; font-weight: 600; }
        .erp-val { font-weight: 700; color: #111; font-family: monospace, Courier, monospace; }
        .erp-barcode-col { text-align: center; border-left: 1px solid #ddd; padding-left: 8px; overflow: hidden; }
        .erp-barcode-col .barcode-svg { max-width: 100%; height: 32px; display: block; margin: 0 auto; }
        .metrics-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; margin-bottom: 6px; }
        .metric-cell { border: 1px solid #ccc; border-radius: 3px; padding: 3px 5px; background: #fafafa; }
        .metric-label { font-size: 6.5pt; text-transform: uppercase; color: #666; font-weight: 700; }
        .metric-value { font-size: 9.5pt; font-weight: 800; color: #111; font-family: monospace, Courier, monospace; }
        .pieces-section { border: 1px solid #222; border-radius: 3px; margin-bottom: 6px; break-inside: avoid; }
        .pieces-header { background: #333; color: #fff; font-size: 7pt; font-weight: 700; text-transform: uppercase; padding: 3px 6px; display: flex; justify-content: space-between; }
        .pieces-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0; background: #fff; }
        .piece-item { display: flex; justify-content: space-between; padding: 2px 6px; border-bottom: 1px solid #eee; border-right: 1px solid #eee; font-size: 7.2pt; }
        .piece-item:nth-child(3n) { border-right: none; }
        .piece-num { font-weight: 700; color: #555; }
        .piece-weight { font-family: monospace, Courier, monospace; font-weight: 800; color: #111; }
        .obs-box { border: 1px dashed #aaa; border-radius: 3px; padding: 3px 6px; font-size: 7pt; margin-bottom: 6px; min-height: 22px; color: #333; }
        .signatures-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 6px; break-inside: avoid; }
        .sig-card { border: 1px solid #444; border-radius: 4px; padding: 5px 8px; background: #fff; }
        .sig-title { font-size: 7pt; font-weight: 800; text-transform: uppercase; color: #222; border-bottom: 1px solid #ccc; padding-bottom: 2px; margin-bottom: 4px; }
        .sig-line { margin-top: 24px; border-top: 1px solid #555; padding-top: 2px; font-size: 6.8pt; color: #444; display: flex; justify-content: space-between; }
        .sig-meta { font-size: 6.8pt; color: #555; line-height: 1.3; }
        .footer-note { margin-top: 5px; text-align: center; font-size: 6.2pt; color: #777; text-transform: uppercase; letter-spacing: 0.04em; }
    </style>
</head>
<body>
    <div class="print-toolbar no-print">
        <button type="button" class="btn-toolbar" id="btnPrintDocument">
            <svg style="width:16px;height:16px;fill:currentColor" viewBox="0 0 24 24"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
            Imprimir Hoja Carta
        </button>
        <button type="button" class="btn-toolbar-close" id="btnCloseWindow">Cerrar</button>
    </div>

    <div class="sheet-container">
        <!-- ENCABEZADO INSTITUCIONAL -->
        <table class="header-table">
            <tr>
                <td style="vertical-align: top;">
                    <h1 class="company-title"><?= Security::escape($emp['Nombre']) ?></h1>
                    <p class="company-subtitle">
                        <strong>RIF / Serial:</strong> <?= Security::escape($emp['RIF']) ?> &nbsp;|&nbsp;
                        <strong>Depósito:</strong> <?= Security::escape($rec['NombreUbicacion']) ?> [<?= Security::escape($rec['CodUbic']) ?>]
                    </p>
                    <p class="company-subtitle">
                        <strong>Operador Emisión:</strong> <?= Security::escape($rec['NombreUsuario']) ?> (<?= Security::escape($rec['CodUsua']) ?>) &nbsp;|&nbsp;
                        <strong>Fecha / Hora:</strong> <?= Security::escape(substr((string)$rec['FechaHora'], 0, 16)) ?>
                    </p>
                </td>
                <td style="vertical-align: top; text-align: right; width: 40%;">
                    <div class="doc-title">RECEPCIÓN DE CANALES</div>
                    <div class="doc-number"><?= Security::escape($nroRec) ?></div>
                    <div style="font-size: 7.5pt; font-weight: 700; margin-top: 2px;">
                        ESTADO: <span style="color: #a02800;"><?= Security::escape($rec['Estado']) ?></span>
                    </div>
                </td>
            </tr>
        </table>

        <!-- CAJA DE CRUCE DIRECTO CON SAINT ERP -->
        <div class="erp-box">
            <div class="erp-box-header">
                <span>Cruce Directo con Saint ERP &nbsp;&mdash;&nbsp; Módulo: Operaciones de Inventario &rarr; Cargar Doc. en Espera</span>
                <span>
                    <?php if (!empty($erp['Procesado'])): ?>
                        <strong style="color: #2e7d32;">PROCESADO OFICIAL SAINT #<?= Security::escape($erp['NumeroDOficial']) ?></strong>
                    <?php else: ?>
                        <strong style="color: #0277bd;">DOCUMENTO EN ESPERA (PENDIENTE DE CARGA)</strong>
                    <?php endif; ?>
                </span>
            </div>
            <div class="erp-grid">
                <div class="erp-details">
                    <div><span class="erp-label">Tipo en Espera:</span> <span class="erp-val">R (Cargo de Inventario)</span></div>
                    <div><span class="erp-label">Operación ERP:</span> <span class="erp-val"><?= Security::escape($erp['CodOper']) ?> (Signo +1)</span></div>
                    <div><span class="erp-label">Doc. en Espera (NumeroD):</span> <span class="erp-val" style="font-size: 8.5pt; color: #a02800;"><?= Security::escape($erp['NumeroD']) ?></span></div>
                    <div><span class="erp-label">Depósito Destino:</span> <span class="erp-val"><?= Security::escape($rec['CodUbic']) ?></span></div>
                    <div><span class="erp-label">Clave Trazabilidad:</span> <span class="erp-val"><?= Security::escape($erp['Autori']) ?></span></div>
                    <div><span class="erp-label">Monto Total Cargo:</span> <span class="erp-val" style="color: #2e7d32;">$<?= number_format((float)$rec['CostoTotalNeto'], 2) ?></span></div>
                </div>
                <div class="erp-barcode-col">
                    <div style="font-size: 6.5pt; color: #555; text-transform: uppercase; margin-bottom: 2px;">Pistolear en Saint ERP:</div>
                    <?= $barcodeSvg ?>
                    <div style="font-family: monospace; font-size: 7.5pt; font-weight: 700; margin-top: 1px;"><?= Security::escape($erp['NumeroD']) ?></div>
                </div>
            </div>
        </div>

        <!-- DATOS DE LA CANAL Y PARÁMETROS OPERATIVOS -->
        <div style="background: #f0f0f0; border: 1px solid #ccc; border-radius: 3px; padding: 4px 8px; margin-bottom: 6px; font-size: 7.8pt;">
            <strong>Especie / Canal:</strong> <?= Security::escape($rec['NombreCanal']) ?>
            <span style="color:#666;">[Código: <?= Security::escape($rec['CodProdCanal']) ?>]</span> &nbsp;|&nbsp;
            <strong>Método de Entrada:</strong> <?= Security::escape($rec['MetodoEntrada']) ?> &nbsp;|&nbsp;
            <strong>Costo Unitario Base:</strong> $<?= number_format((float)$rec['CostActCanal'], 4) ?> / <?= Security::escape($rec['Unidad']) ?>
        </div>

        <div class="metrics-grid">
            <div class="metric-cell">
                <div class="metric-label">Peso Bruto Total</div>
                <div class="metric-value"><?= number_format((float)$rec['PesoTotalBruto'], 2) ?> <span style="font-size:7pt;"><?= Security::escape($rec['Unidad']) ?></span></div>
            </div>
            <div class="metric-cell">
                <div class="metric-label">Tara Báscula / Ganchos</div>
                <div class="metric-value"><?= number_format((float)$rec['TaraTotal'], 2) ?> <span style="font-size:7pt;"><?= Security::escape($rec['Unidad']) ?></span></div>
            </div>
            <div class="metric-cell" style="background: #fff8f5; border-color: #e86339;">
                <div class="metric-label" style="color: #a02800;">Peso Neto Recibido</div>
                <div class="metric-value" style="color: #a02800; font-size: 10.5pt;"><?= number_format((float)$rec['PesoTotalNeto'], 2) ?> <span style="font-size:7pt;"><?= Security::escape($rec['Unidad']) ?></span></div>
            </div>
            <div class="metric-cell">
                <div class="metric-label"><?= $rec['MetodoEntrada'] === 'PESO_INDIVIDUAL' ? 'Total Piezas Pesadas' : 'Cantidad Declarada' ?></div>
                <div class="metric-value">
                    <?= (int)$rec['CantidadEspecies'] > 0 ? (int)$rec['CantidadEspecies'] . ' Piezas' : 'Global' ?>
                    <?php if ((int)$rec['CantidadEspecies'] > 0 && (float)$rec['PesoTotalNeto'] > 0): ?>
                        <span style="font-size: 7pt; color: #555; font-weight: normal;">(<?= number_format((float)$rec['PesoTotalNeto'] / (int)$rec['CantidadEspecies'], 1) ?> kg/prom)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- DETALLE DE PIEZAS (GRID MULTI-COLUMNA) -->
        <?php if (!empty($piezas)): ?>
            <div class="pieces-section">
                <div class="pieces-header">
                    <span>Desglose de Pesaje Pieza a Pieza (Grid Multi-Columna Compacto)</span>
                    <span>Total: <?= count($piezas) ?> Piezas Registradas</span>
                </div>
                <div class="pieces-grid">
                    <?php foreach ($piezas as $p): ?>
                        <div class="piece-item">
                            <span class="piece-num">#<?= str_pad((string)$p['NroEspecie'], 2, '0', STR_PAD_LEFT) ?>:</span>
                            <span class="piece-weight"><?= number_format((float)$p['PesoNeto'], 2) ?> <?= Security::escape($rec['Unidad']) ?></span>
                            <span style="font-size: 6.5pt; color: #777;">
                                <?= $p['EstadoDesposte'] === 'DESPOSTADO' ? Security::escape($p['NroOrdenDesposte'] ?: 'PROCESADO') : 'DISPONIBLE' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- OBSERVACIONES -->
        <div class="obs-box">
            <strong>Observaciones / Incidencias de Entrada:</strong>
            <?= Security::escape($rec['Observaciones'] ?: 'Sin observaciones registradas.') ?>
        </div>

        <!-- CONTROL DE FIRMAS -->
        <div class="signatures-grid">
            <div class="sig-card">
                <div class="sig-title">Sala de Recepción / Báscula (Entrega)</div>
                <div class="sig-meta">
                    <div><strong>Operario Báscula:</strong> <?= Security::escape($rec['NombreUsuario']) ?></div>
                    <div><strong>C.I. / Identificación:</strong> ____________________________________</div>
                    <div><strong>Fecha / Hora Pesaje:</strong> <?= Security::escape(substr((string)$rec['FechaHora'], 0, 16)) ?></div>
                </div>
                <div class="sig-line">
                    <span>Firma Responsable Báscula</span>
                    <span>Sello de Planta</span>
                </div>
            </div>

            <div class="sig-card">
                <div class="sig-title">Departamento de Inventarios / Saint ERP (Recepción)</div>
                <div class="sig-meta">
                    <div><strong>Usuario Procesador Saint:</strong> _____________________________</div>
                    <div><strong>N° Operación Definitiva Saint:</strong> <span style="font-family: monospace; font-weight: 700; color: #2e7d32;"><?= !empty($erp['NumeroDOficial']) ? Security::escape($erp['NumeroDOficial']) : '_________________________' ?></span></div>
                    <div><strong>Fecha / Hora de Carga:</strong> <?= !empty($erp['FechaProcesado']) ? Security::escape(substr((string)$erp['FechaProcesado'], 0, 16)) : '____/____/2026 ____:____' ?></div>
                </div>
                <div class="sig-line">
                    <span>Firma / Sello Administrativo</span>
                    <span>Cruce Conforme</span>
                </div>
            </div>
        </div>

        <div class="footer-note">
            Comprobante oficial de pesaje y control de inventario &bull; Sistema ST Despostadora &bull; Enlace nativo Microsoft SQL Server / Saint ERP Enterprise
        </div>
    </div>

    <script src="<?= Security::baseUrl('/js/print_toolbar.js') ?>"></script>
</body>
</html>
