<?php
declare(strict_types=1);

use Config\Security;

ob_start();
?>
<div class="reception-wrapper">
    <!-- Encabezado con Botón de Acción -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 class="card-title" style="margin-bottom: 0.25rem;">Recepción de Carne en Canal y Especies</h3>
                <p class="card-desc">Ingreso y pesaje de canales (reses, cerdos, aves, pescado) por peso global, lote o pesaje individual.</p>
            </div>
            <a href="/recepcion/nueva" class="btn btn-primary" style="width: auto; min-width: 190px; text-decoration: none;">
                <svg class="icon" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                + Nueva Recepción
            </a>
        </div>
    </div>

    <!-- Listado de Recepciones Registradas -->
    <div class="card">
        <h4 style="margin-bottom: 1rem; color: var(--text-primary); font-size: 1.1rem; display: flex; align-items: center; justify-content: space-between;">
            <span>Historial de Recepciones en Canal</span>
            <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 400;">
                Total: <strong><?= count($receptions) ?></strong> recepciones
            </span>
        </h4>

        <?php if (empty($receptions)): ?>
            <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                <svg class="icon" viewBox="0 0 24 24" style="width: 48px; height: 48px; stroke: var(--border-color); margin-bottom: 0.75rem;">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                </svg>
                <p style="font-size: 1rem; margin-bottom: 0.5rem;">No se han registrado recepciones de carne en canal.</p>
                <p style="font-size: 0.85rem;">Haga clic en <strong>+ Nueva Recepción</strong> para registrar la primera entrada.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-secondary); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;">
                            <th style="padding: 0.75rem 0.5rem;">Correlativo</th>
                            <th style="padding: 0.75rem 0.5rem;">Fecha / Hora</th>
                            <th style="padding: 0.75rem 0.5rem;">Especie / Canal</th>
                            <th style="padding: 0.75rem 0.5rem;">Método</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: right;">Cant.</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: right;">Peso Neto</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: right;">Disponible</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: right;">CostAct ERP</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: right;">Costo Total</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: center;">Estado</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: center;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($receptions as $rec): ?>
                            <tr style="border-bottom: 1px solid var(--border-color); transition: background-color 0.2s;">
                                <td style="padding: 0.75rem 0.5rem; font-family: monospace; font-weight: 700; color: var(--primary);">
                                    <?= Security::escape($rec['NroRecepcion']) ?>
                                </td>
                                <td style="padding: 0.75rem 0.5rem; color: var(--text-secondary); font-size: 0.8rem; white-space: nowrap;">
                                    <?= Security::escape(substr((string)$rec['FechaHora'], 0, 16)) ?>
                                </td>
                                <td style="padding: 0.75rem 0.5rem;">
                                    <strong style="color: var(--text-primary);"><?= Security::escape($rec['NombreCanal']) ?></strong>
                                    <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">[<?= Security::escape($rec['CodProdCanal']) ?>]</span>
                                </td>
                                <td style="padding: 0.75rem 0.5rem;">
                                    <?php if ($rec['MetodoEntrada'] === 'SOLO_PESO'): ?>
                                        <span class="badge" style="background: rgba(193, 218, 198, 0.15); color: var(--success); border: 1px solid rgba(193, 218, 198, 0.3);">SOLO PESO</span>
                                    <?php elseif ($rec['MetodoEntrada'] === 'PESO_CANTIDAD'): ?>
                                        <span class="badge" style="background: rgba(216, 134, 94, 0.15); color: var(--accent); border: 1px solid rgba(216, 134, 94, 0.3);">PESO + CANTIDAD</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">PESAJE INDIVIDUAL</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 600;">
                                    <?= (int)$rec['CantidadEspecies'] > 0 ? (int)$rec['CantidadEspecies'] : '-' ?>
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 700; color: var(--text-primary);">
                                    <?= number_format((float)$rec['PesoTotalNeto'], 2) ?> <?= Security::escape($rec['Unidad']) ?>
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 700; color: <?= (float)$rec['PesoDisponible'] > 0 ? 'var(--success)' : 'var(--text-muted)' ?>;">
                                    <?= number_format((float)$rec['PesoDisponible'], 2) ?>
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right; color: var(--text-secondary);">
                                    $<?= number_format((float)$rec['CostActCanal'], 2) ?>
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 600; color: var(--text-primary);">
                                    $<?= number_format((float)$rec['CostoTotalNeto'], 2) ?>
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: center;">
                                    <?php if ($rec['Estado'] === 'DISPONIBLE'): ?>
                                        <span class="badge badge-success">DISPONIBLE</span>
                                    <?php elseif ($rec['Estado'] === 'EN_PROCESO'): ?>
                                        <span class="badge badge-warning">EN DESPOSTE</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: rgba(140, 115, 101, 0.2); color: var(--text-muted); border: 1px solid var(--border-color);"><?= Security::escape($rec['Estado']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: center;">
                                    <?php if ($rec['MetodoEntrada'] === 'PESO_INDIVIDUAL'): ?>
                                        <button type="button" class="btn btn-ver-piezas" style="background: var(--bg-card); border: 1px solid var(--border-color); font-size: 0.8rem; padding: 0.3rem 0.6rem; color: var(--primary);" 
                                                data-nrorec="<?= Security::escape($rec['NroRecepcion']) ?>">
                                            Ver Piezas
                                        </button>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.8rem;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Ver Detalle de Piezas Individuales -->
<div id="modalDetallePiezas" class="sidebar-overlay" style="display: none; align-items: center; justify-content: center; z-index: 100;">
    <div class="card" style="max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
            <h4 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);" id="tituloDetallePiezas">Detalle de Piezas Recibidas</h4>
            <button type="button" data-action="cerrar-modal-detalle-piezas" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.4rem;">&times;</button>
        </div>
        <div id="contenedorPiezasDetalle" style="display: flex; flex-direction: column; gap: 0.4rem;">
            <!-- Contenido dinámico -->
        </div>
    </div>
</div>

<script src="/js/recepcion.js"></script>

<style>
.metodo-radio-btn {
    background: var(--bg-main);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 0.75rem 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
    user-select: none;
}
.metodo-radio-btn input {
    display: none;
}
.metodo-radio-btn:hover {
    border-color: var(--accent);
}
.metodo-radio-btn.active {
    border-color: var(--primary);
    background: rgba(244, 107, 58, 0.12);
}
</style>

<?php
$content = ob_get_clean();
$title = 'Recepción de Canales - ST Despostadora';
$pageTitle = 'Recepción de Canales';
$activeNav = 'recepcion';

require __DIR__ . '/layout.php';
