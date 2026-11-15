<?php
declare(strict_types=1);

use Config\Security;
use Config\Assets;
use Src\Utils\BarcodeService;

/**
 * Liquidación de desposte cárnico en Hoja Carta (Letter).
 * Incluye doble cruce con Saint ERP (Descargo D-... y Cargo C-...) con códigos de barras.
 * @var array<string, mixed> $data Datos retornados por DeboningQueryService::getDeboningPrintData
 * @var bool $autoprint Si dispara automáticamente el diálogo de impresión
 */

$ord = $data['orden'] ?? [];
$emp = $data['empresa'] ?? [];
$cortes = $data['cortes'] ?? [];
$totales = $data['totales'] ?? [];
$mermaInfo = $data['merma_info'] ?? [];
$erp = $data['erp'] ?? [];

$descargo = $erp['descargo'] ?? [];
$cargo = $erp['cargo'] ?? [];

$nroOrden = (string)($ord['NroOrdenDesposte'] ?? '');

// Códigos de barra nativos Code 128
$barcodeDescargoSvg = BarcodeService::generateCode128Svg((string)($descargo['NumeroD'] ?? ''), 26, 1, false);
$barcodeCargoSvg    = BarcodeService::generateCode128Svg((string)($cargo['NumeroD'] ?? ''), 26, 1, false);

// Métricas de costo unitario por kilo de materia prima vs cortes comerciales obtenidos
$costoCanalKg = (float)($ord['CostActCanal'] ?? 0.0);
if ($costoCanalKg <= 0.0001 && (float)$ord['PesoCanalEntrada'] > 0.0001) {
    $costoCanalKg = (float)$ord['CostoTotalCanal'] / (float)$ord['PesoCanalEntrada'];
}

$pesoCortes = (float)($totales['PesoCortes'] ?? 0.0);
$totalCostoRealCortes = (float)($totales['TotalCostoRealCortes'] ?? 0.0);
$costoPromedioCortesKg = $pesoCortes > 0.0001 ? ($totalCostoRealCortes / $pesoCortes) : 0.0;
$pctVariacionCosto = $costoCanalKg > 0.0001 ? (($costoPromedioCortesKg - $costoCanalKg) / $costoCanalKg) * 100.0 : 0.0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liquidación Desposte <?= Security::escape($nroOrden) ?> - ST Despostadora</title>
    <link rel="icon" type="image/svg+xml" href="<?= Assets::faviconDataUri() ?>">
    <style>
        @page { size: letter portrait; margin: 6mm 8mm; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; font-size: 7.2pt; line-height: 1.15; color: #111; background: #e5e5e5; }
        .sheet-container { width: 215.9mm; min-height: 279.4mm; margin: 10px auto; background: #fff; padding: 6mm 8mm; box-shadow: 0 4px 15px rgba(0,0,0,0.15); position: relative; }
        @media print {
            body { background: #fff !important; }
            .sheet-container { width: 100% !important; min-height: 0 !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; }
            .no-print { display: none !important; }
        }
        .print-toolbar { position: fixed; top: 10px; left: 50%; transform: translateX(-50%); z-index: 1000; background: #2b201a; color: #fff; padding: 8px 16px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); display: flex; align-items: center; gap: 12px; }
        .btn-toolbar { background: #e86339; color: #fff; border: none; padding: 6px 14px; border-radius: 4px; font-weight: 700; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .btn-toolbar:hover { background: #d0532b; }
        .btn-toolbar-close { background: transparent; color: #ccc; border: 1px solid #555; padding: 5px 12px; border-radius: 4px; font-size: 13px; cursor: pointer; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; border-bottom: 2px solid #222; padding-bottom: 3px; }
        .company-title { font-size: 10.5pt; font-weight: 800; color: #111; margin: 0; text-transform: uppercase; }
        .company-subtitle { font-size: 6.8pt; color: #444; margin: 1px 0 0 0; }
        .doc-title { font-size: 11pt; font-weight: 800; color: #a02800; margin: 0; text-align: right; text-transform: uppercase; }
        .doc-number { font-family: monospace, Courier, monospace; font-size: 10pt; font-weight: 700; color: #111; text-align: right; }
        
        /* DOBLE CRUCE ERP CON CÓDIGOS DE BARRA RESPONSIVOS */
        .erp-double-box { border: 1.5px solid #222; background: #fbfbfb; border-radius: 4px; padding: 4px 6px; margin-bottom: 4px; break-inside: avoid; }
        .erp-header-strip { font-size: 7pt; font-weight: 800; text-transform: uppercase; color: #a02800; border-bottom: 1px dashed #aaa; padding-bottom: 2px; margin-bottom: 3px; display: flex; justify-content: space-between; }
        .erp-cards-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
        .erp-subcard { border: 1px solid #ccc; border-radius: 3px; padding: 4px 6px; background: #fff; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; }
        .erp-subcard-title { font-size: 6.8pt; font-weight: 800; text-transform: uppercase; margin-bottom: 2px; display: flex; align-items: center; gap: 4px; }
        .erp-subcard-data { font-size: 6.8pt; line-height: 1.25; }
        .erp-data-grid { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 1px 4px; }
        .erp-barcode-pane { margin-top: 3px; padding-top: 2px; border-top: 1px dashed #e0e0e0; text-align: center; background: #fafafa; border-radius: 2px; overflow: hidden; }
        .erp-barcode-pane .barcode-svg { max-width: 95%; height: 25px; margin: 0 auto; display: block; }
        .barcode-label { font-family: monospace, Courier, monospace; font-size: 6.5pt; font-weight: 700; color: #111; margin-top: 1px; letter-spacing: 0.5px; }

        .balance-banner { background: #f0f7f0; border: 1px solid #b7deb7; border-radius: 3px; padding: 3px 6px; margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center; font-size: 7pt; }
        .balance-metric { font-weight: 700; font-family: monospace; }
        
        /* MÉTRICAS EXPANDIDAS CON COSTO CANAL VS NUEVO COSTO REAL */
        .metrics-strip { display: grid; grid-template-columns: repeat(6, 1fr); gap: 4px; margin-bottom: 4px; }
        .metric-box { border: 1px solid #dcdcdc; border-radius: 3px; padding: 2px 4px; background: #fafafa; }
        .metric-box-title { font-size: 5.8pt; text-transform: uppercase; color: #666; font-weight: 700; }
        .metric-box-val { font-size: 8.5pt; font-weight: 800; font-family: monospace; color: #111; }
        .metric-box-highlight { border: 1.5px solid #0d47a1; background: #f0f4fc; }
        .metric-box-highlight .metric-box-title { color: #0d47a1; font-weight: 800; }
        .metric-box-highlight .metric-box-val { color: #0d47a1; }

        .cuts-table { width: 100%; border-collapse: collapse; font-size: 6.8pt; margin-bottom: 4px; break-inside: avoid; }
        .cuts-table th { background: #2b201a; color: #fff; font-weight: 700; text-transform: uppercase; font-size: 6.2pt; padding: 2.5px 3px; text-align: left; border: 1px solid #2b201a; }
        .cuts-table th.tar { text-align: right; }
        .cuts-table th.tac { text-align: center; }
        .cuts-table td { padding: 2px 3px; border: 1px solid #ddd; vertical-align: middle; }
        .cuts-table td.tar { text-align: right; font-family: monospace; }
        .cuts-table td.tac { text-align: center; font-family: monospace; }
        .cuts-table tr:nth-child(even) { background: #fdfdfd; }
        .cuts-table tr.merma-row { background: #fff4e5; font-weight: 700; border-top: 1.5px solid #d0532b; border-bottom: 1.5px solid #d0532b; }
        .cuts-table tr.totals-row { background: #eee; font-weight: 800; border-top: 2px solid #222; border-bottom: 2px solid #222; }
        .saint-instructions { background: #fffde7; border: 1px solid #fff59d; border-radius: 3px; padding: 3px 6px; font-size: 6.5pt; color: #5d4037; margin-bottom: 4px; }
        .signatures-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 3px; break-inside: avoid; }
        .sig-card { border: 1px solid #444; border-radius: 3px; padding: 4px 6px; background: #fff; }
        .sig-title { font-size: 6.5pt; font-weight: 800; text-transform: uppercase; color: #222; border-bottom: 1px solid #ccc; padding-bottom: 1px; margin-bottom: 3px; }
        .sig-meta { font-size: 6.5pt; color: #444; line-height: 1.25; }
        .sig-line { margin-top: 16px; border-top: 1px solid #555; padding-top: 1px; font-size: 6.2pt; color: #555; display: flex; justify-content: space-between; }
        .footer-note { margin-top: 3px; text-align: center; font-size: 5.8pt; color: #777; text-transform: uppercase; letter-spacing: 0.04em; }
    </style>
</head>
<body>
    <div class="print-toolbar no-print">
        <button type="button" class="btn-toolbar" id="btnPrintDocument">
            <svg style="width:16px;height:16px;fill:currentColor" viewBox="0 0 24 24"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
            Imprimir Hoja de Liquidación
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
                        <strong>Mesa:</strong> <?= Security::escape($ord['LineaMesa'] ?: 'Mesa 1') ?> &nbsp;|&nbsp;
                        <strong>Depósito:</strong> <?= Security::escape($ord['NombreUbicacion']) ?> [<?= Security::escape($ord['CodUbic']) ?>]
                    </p>
                    <p class="company-subtitle">
                        <strong>Especie:</strong> <?= Security::escape($ord['NombreCanal']) ?> [<?= Security::escape($ord['CodProdCanal']) ?>] &nbsp;|&nbsp;
                        <strong>Costo Canal:</strong> $<?= number_format($costoCanalKg, 2) ?>/kg &nbsp;|&nbsp;
                        <strong>Recepción:</strong> <?= Security::escape($ord['NroRecepcion']) ?> &nbsp;|&nbsp;
                        <strong>Fecha Cierre:</strong> <?= Security::escape(substr((string)($ord['FechaHoraCierre'] ?: $ord['FechaHoraInicio']), 0, 16)) ?>
                    </p>
                </td>
                <td style="vertical-align: top; text-align: right; width: 38%;">
                    <div class="doc-title">LIQUIDACIÓN DE DESPOSTE</div>
                    <div class="doc-number"><?= Security::escape($nroOrden) ?></div>
                    <div style="font-size: 7pt; font-weight: 700; margin-top: 1px;">
                        ESTADO: <span style="color: <?= $ord['Estado'] === 'FINALIZADO' ? '#2e7d32' : '#a02800' ?>;"><?= Security::escape($ord['Estado']) ?></span>
                        &nbsp;|&nbsp; Cruce: <span style="color: #0277bd;"><?= Security::escape($ord['EstadoCruceERP']) ?></span>
                    </div>
                </td>
            </tr>
        </table>

        <!-- CAJA DE DOBLE CRUCE CON SAINT ERP -->
        <div class="erp-double-box">
            <div class="erp-header-strip">
                <span>Cruce Doble con Saint ERP &nbsp;&mdash;&nbsp; Módulo: Operaciones de Inventario &rarr; Cargar Doc. en Espera</span>
                <span>Estación: <?= Security::escape($erp['CodEsta'] ?? 'DESPOSTE') ?></span>
            </div>
            <div class="erp-cards-grid">
                <!-- TARJETA 1: DESCARGO DE CANAL (SALIDA) -->
                <div class="erp-subcard" style="border-left: 3px solid #ff7043;">
                    <div class="erp-subcard-data">
                        <div class="erp-subcard-title" style="color: #d84315;"><span>&bull;</span> 1. DESCARGO DE CANAL (SALIDA)</div>
                        <div class="erp-data-grid">
                            <div><strong>Doc. Espera:</strong> <span style="font-family: monospace; font-size: 7.5pt; font-weight:700; color:#d84315;"><?= Security::escape($descargo['NumeroD']) ?></span></div>
                            <div><strong>Operación:</strong> <?= Security::escape($descargo['CodOper']) ?> (Signo -1)</div>
                            <div><strong>Canal Salida:</strong> <?= number_format((float)$descargo['Kilos'], 2) ?> kg</div>
                            <div><strong>Monto:</strong> $<?= number_format((float)$descargo['Monto'], 2) ?></div>
                        </div>
                        <div style="margin-top: 1px;">
                            <strong>Saint Oficial:</strong>
                            <?php if (!empty($descargo['Procesado'])): ?>
                                <strong style="color: #2e7d32; font-family: monospace;">#<?= Security::escape($descargo['NumeroDOficial']) ?> (<?= Security::escape($descargo['TipoOpIFinal'] ?: 'S') ?>)</strong>
                            <?php else: ?>
                                <span style="color: #777;">[ PENDIENTE EN SAINT ]</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="erp-barcode-pane">
                        <?= $barcodeDescargoSvg ?>
                        <div class="barcode-label"><?= Security::escape($descargo['NumeroD']) ?></div>
                    </div>
                </div>

                <!-- TARJETA 2: CARGO DE CORTES Y MERMA (ENTRADA) -->
                <div class="erp-subcard" style="border-left: 3px solid #66bb6a;">
                    <div class="erp-subcard-data">
                        <div class="erp-subcard-title" style="color: #2e7d32;"><span>&bull;</span> 2. CARGO DE CORTES Y MERMA (ENTRADA)</div>
                        <div class="erp-data-grid">
                            <div><strong>Doc. Espera:</strong> <span style="font-family: monospace; font-size: 7.5pt; font-weight:700; color:#2e7d32;"><?= Security::escape($cargo['NumeroD']) ?></span></div>
                            <div><strong>Operación:</strong> <?= Security::escape($cargo['CodOper']) ?> (Signo +1)</div>
                            <div><strong>Entrada Total:</strong> <?= number_format((float)$cargo['Kilos'], 2) ?> kg</div>
                            <div><strong>Monto:</strong> $<?= number_format((float)$cargo['Monto'], 2) ?></div>
                        </div>
                        <div style="margin-top: 1px;">
                            <strong>Saint Oficial:</strong>
                            <?php if (!empty($cargo['Procesado'])): ?>
                                <strong style="color: #2e7d32; font-family: monospace;">#<?= Security::escape($cargo['NumeroDOficial']) ?> (<?= Security::escape($cargo['TipoOpIFinal'] ?: 'E') ?>)</strong>
                            <?php else: ?>
                                <span style="color: #777;">[ PENDIENTE EN SAINT ]</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="erp-barcode-pane">
                        <?= $barcodeCargoSvg ?>
                        <div class="barcode-label"><?= Security::escape($cargo['NumeroD']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- BANNER DE CONCILIACIÓN MATEMÁTICA Y COSTO -->
        <div class="balance-banner">
            <div>
                <strong>Conciliación de Masa:</strong>
                Canal (<span class="balance-metric"><?= number_format((float)$ord['PesoCanalEntrada'], 2) ?> kg</span>) =
                Cortes (<span class="balance-metric"><?= number_format($pesoCortes, 2) ?> kg</span>) +
                Merma (<span class="balance-metric"><?= number_format((float)$totales['PesoMerma'], 2) ?> kg</span>)
                &nbsp;&bull;&nbsp;
                <span style="color: <?= !empty($ord['ParidadKilos']) ? '#2e7d32' : '#c62828' ?>; font-weight: 800;">
                    <?= !empty($ord['ParidadKilos']) ? 'PARIDAD 100% OK' : 'DESCUADRE (&Delta; = ' . $ord['DiferenciaKilos'] . ' kg)' ?>
                </span>
            </div>
            <div>
                <strong>Evolución de Costo:</strong>
                Canal: <span class="balance-metric">$<?= number_format($costoCanalKg, 2) ?>/kg</span>
                &rarr; <strong>Nuevo Costo Real Cortes:</strong> <span class="balance-metric" style="color: #0d47a1;">$<?= number_format($costoPromedioCortesKg, 2) ?>/kg</span>
                <span style="color: #555; font-size: 6.2pt;">(+<?= number_format($pctVariacionCosto, 1) ?>% merma)</span>
                &nbsp;&bull;&nbsp;
                <span style="color: <?= !empty($ord['CuadreMonetario']) ? '#2e7d32' : '#c62828' ?>; font-weight: 800;">
                    <?= !empty($ord['CuadreMonetario']) ? 'CUADRE $' : 'DESCUADRE $' ?>
                </span>
            </div>
        </div>

        <!-- MÉTRICAS DE RENDIMIENTO Y NUEVO COSTO REAL -->
        <div class="metrics-strip">
            <div class="metric-box">
                <div class="metric-box-title">Canal Entrada</div>
                <div class="metric-box-val"><?= number_format((float)$ord['PesoCanalEntrada'], 2) ?> kg</div>
            </div>
            <div class="metric-box">
                <div class="metric-box-title">Costo Canal / Kg</div>
                <div class="metric-box-val">$<?= number_format($costoCanalKg, 2) ?></div>
            </div>
            <div class="metric-box">
                <div class="metric-box-title">Cortes Obtenidos</div>
                <div class="metric-box-val"><?= number_format($pesoCortes, 2) ?> kg</div>
            </div>
            <div class="metric-box">
                <div class="metric-box-title">Rendimiento Útil</div>
                <div class="metric-box-val" style="color: #2e7d32;"><?= number_format((float)$ord['RendimientoPct'], 2) ?>%</div>
            </div>
            <div class="metric-box">
                <div class="metric-box-title">Merma Técnica</div>
                <div class="metric-box-val" style="color: #d84315;"><?= number_format((float)$totales['PesoMerma'], 2) ?> kg (<?= number_format((float)$ord['MermaPct'], 1) ?>%)</div>
            </div>
            <div class="metric-box metric-box-highlight">
                <div class="metric-box-title">Nuevo Costo Real / Kg</div>
                <div class="metric-box-val">$<?= number_format($costoPromedioCortesKg, 2) ?></div>
            </div>
        </div>

        <!-- TABLA DENSA DE CORTES -->
        <table class="cuts-table">
            <thead>
                <tr>
                    <th style="width: 18px;" class="tac">#</th>
                    <th style="width: 55px;">Cód. Saint</th>
                    <th>Corte Comercial / Subproducto</th>
                    <th style="width: 80px;">N° Lote Producción</th>
                    <th style="width: 45px;" class="tar">Kilos (kg)</th>
                    <th style="width: 38px;" class="tar">Rend %</th>
                    <th style="width: 55px;" class="tar">Nuevo Costo/Kg</th>
                    <th style="width: 52px;" class="tar">Total Costo</th>
                    <th style="width: 50px;" class="tar">P. Sugerido</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cortes as $c): ?>
                    <tr>
                        <td class="tac"><?= (int)$c['NroItem'] ?></td>
                        <td style="font-family: monospace; font-weight: 700;"><?= Security::escape($c['CodProdCorte']) ?></td>
                        <td><strong><?= Security::escape($c['NombreCorte']) ?></strong></td>
                        <td style="font-family: monospace; color: #555;"><?= Security::escape($c['NroLote']) ?></td>
                        <td class="tar" style="font-weight: 700;"><?= number_format((float)$c['PesoNeto'], 2) ?></td>
                        <td class="tar"><?= number_format((float)($c['RendimientoPct'] ?? $c['RendimientoCortePct'] ?? 0.0), 2) ?>%</td>
                        <td class="tar" style="font-weight: 700; color: #0d47a1;">$<?= number_format((float)$c['CostoRealUnitario'], 4) ?></td>
                        <td class="tar" style="font-weight: 700;">$<?= number_format((float)$c['CostoRealTotal'], 2) ?></td>
                        <td class="tar" style="color: #2e7d32;">$<?= number_format((float)$c['PrecioVentaSugerido'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if ((float)$mermaInfo['PesoMerma'] > 0.0001): ?>
                    <tr class="merma-row">
                        <td class="tac" style="color: #d84315;">[M]</td>
                        <td style="font-family: monospace;"><?= Security::escape($mermaInfo['CodProdMerma']) ?></td>
                        <td>
                            <strong><?= Security::escape($mermaInfo['NombreMerma']) ?></strong>
                            <span style="font-size: 5.8pt; color: #777;">(Merma física absorbida en cortes)</span>
                        </td>
                        <td style="font-family: monospace;"><?= Security::escape($mermaInfo['NroLote']) ?></td>
                        <td class="tar" style="color: #d84315; font-weight: 800;"><?= number_format((float)$mermaInfo['PesoMerma'], 2) ?></td>
                        <td class="tar" style="color: #d84315; font-weight: 800;"><?= number_format((float)$mermaInfo['MermaPct'], 2) ?>%</td>
                        <td class="tar" style="color: #777;">$0.0000</td>
                        <td class="tar" style="color: #777;">$0.00</td>
                        <td class="tar" style="color: #777;">-</td>
                    </tr>
                <?php endif; ?>

                <tr class="totals-row">
                    <td colspan="4" style="text-transform: uppercase;">
                        TOTALES CONSOLIDADOS (<?= count($cortes) ?> Cortes + Merma Absorbida)
                    </td>
                    <td class="tar" style="font-size: 7.5pt; font-weight: 800;"><?= number_format((float)$ord['TotalKilosCargo'], 2) ?></td>
                    <td class="tar" style="font-size: 7.5pt; font-weight: 800;">100.0%</td>
                    <td class="tar" style="font-size: 7.5pt; font-weight: 800; color: #0d47a1; background: #e8f0fe;" title="Nuevo Costo Promedio Ponderado por Kilo de Carne Útil">
                        $<?= number_format($costoPromedioCortesKg, 2) ?>
                    </td>
                    <td class="tar" style="font-size: 7.5pt; color: #a02800; font-weight: 800;">$<?= number_format($totalCostoRealCortes, 2) ?></td>
                    <td class="tar">-</td>
                </tr>
            </tbody>
        </table>

        <!-- NOTA EXPLICATIVA DE ABSORCIÓN DE MERMA Y NUEVO COSTO -->
        <div style="font-size: 6pt; color: #444; margin-bottom: 4px; display: flex; justify-content: space-between;">
            <span>* <strong>Nuevo Costo Real por Kilo:</strong> Costo unitario resultante tras absorber el 100% de la merma técnica en los cortes comerciales.</span>
            <span><strong>Costo Entrada:</strong> $<?= number_format($costoCanalKg, 2) ?>/kg &nbsp;|&nbsp; <strong>Nuevo Costo Promedio Carne Útil:</strong> $<?= number_format($costoPromedioCortesKg, 2) ?>/kg (+<?= number_format($pctVariacionCosto, 1) ?>%)</span>
        </div>

        <!-- INSTRUCCIONES PARA SAINT ERP -->
        <div class="saint-instructions">
            <strong>Instrucciones para el Operador de Saint ERP:</strong>
            1. En Saint ERP, abra: <em>Inventario &rarr; Operaciones de Inventario &rarr; Cargar Doc. en Espera</em>. &bull;
            2. Pistolee el código <strong><?= Security::escape($descargo['NumeroD']) ?></strong> y procese el <strong>Descargo</strong> de la materia prima. &bull;
            3. Pistolee el código <strong><?= Security::escape($cargo['NumeroD']) ?></strong> y procese el <strong>Cargo</strong> de cortes terminados. &bull;
            4. Anote abajo los N° definitivos generados y archive esta liquidación.
        </div>

        <!-- CONTROL DE FIRMAS -->
        <div class="signatures-grid">
            <div class="sig-card">
                <div class="sig-title">Sala de Desposte / Producción (Liquidado por)</div>
                <div class="sig-meta">
                    <div><strong>Operario / Supervisor:</strong> <?= Security::escape($ord['NombreUsuarioCierre'] ?: $ord['NombreUsuarioInicio']) ?></div>
                    <div><strong>C.I. / Identificación:</strong> ____________________________________</div>
                    <div><strong>Fecha / Hora Cierre:</strong> <?= Security::escape(substr((string)($ord['FechaHoraCierre'] ?: $ord['FechaHoraInicio']), 0, 16)) ?></div>
                </div>
                <div class="sig-line">
                    <span>Firma Supervisor Desposte</span>
                    <span>Sello de Producción</span>
                </div>
            </div>

            <div class="sig-card">
                <div class="sig-title">Departamento de Inventarios / Saint ERP (Procesado por)</div>
                <div class="sig-meta">
                    <div>
                        <strong>N° Oficial Descargo Saint:</strong>
                        <span style="font-family: monospace; font-weight: 700; color: #d84315;">
                            <?= !empty($descargo['NumeroDOficial']) ? Security::escape($descargo['NumeroDOficial']) : '_________________________' ?>
                        </span>
                    </div>
                    <div>
                        <strong>N° Oficial Cargo Saint:</strong>
                        <span style="font-family: monospace; font-weight: 700; color: #2e7d32;">
                            <?= !empty($cargo['NumeroDOficial']) ? Security::escape($cargo['NumeroDOficial']) : '_________________________' ?>
                        </span>
                    </div>
                    <div><strong>Firma / Sello de Carga:</strong> ___________________ <strong>Fecha:</strong> ____/____/2026</div>
                </div>
                <div class="sig-line">
                    <span>Firma / Sello Administrativo</span>
                    <span>Doble Cruce Saint Conforme</span>
                </div>
            </div>
        </div>

        <div class="footer-note">
            Liquidación oficial de rendimiento cárnico y absorción de merma &bull; Sistema ST Despostadora &bull; Paridad 1:1 con Saint ERP Enterprise
        </div>
    </div>

    <script src="<?= Security::baseUrl('/js/print_toolbar.js') ?>"></script>
</body>
</html>
