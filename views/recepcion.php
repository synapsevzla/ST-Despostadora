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
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem;">
            <h4 style="margin: 0; color: var(--text-primary); font-size: 1.1rem;">
                Historial de Recepciones en Canal
            </h4>
            <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 400;">
                Total: <strong><?= $pagination['total'] ?? count($receptions) ?></strong> recepciones
                <?php if (!empty($fechaDesde) || !empty($fechaHasta)): ?>
                    <span style="color: var(--primary); margin-left: 0.25rem;">(filtrado)</span>
                <?php endif; ?>
            </span>
        </div>

        <!-- Barra de Búsqueda por Rango de Fecha -->
        <form method="GET" action="/recepcion" class="filter-bar">
            <div class="filter-group">
                <svg class="icon" viewBox="0 0 24 24" style="color: var(--primary); width: 16px; height: 16px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                <label for="rec_fecha_desde" class="filter-label">Desde:</label>
                <input type="date" id="rec_fecha_desde" name="fecha_desde" class="filter-input" value="<?= Security::escape($fechaDesde ?? '') ?>">
                <label for="rec_fecha_hasta" class="filter-label" style="margin-left: 0.25rem;">Hasta:</label>
                <input type="date" id="rec_fecha_hasta" name="fecha_hasta" class="filter-input" value="<?= Security::escape($fechaHasta ?? '') ?>">
                <button type="submit" class="btn btn-primary" style="padding: 0.45rem 0.9rem; font-size: 0.85rem; width: auto; min-width: auto;">
                    <svg class="icon" viewBox="0 0 24 24" style="width: 14px; height: 14px; margin-right: 4px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    Filtrar
                </button>
                <?php if (!empty($fechaDesde) || !empty($fechaHasta)): ?>
                    <a href="/recepcion" class="btn btn-outline" style="padding: 0.45rem 0.8rem; font-size: 0.85rem; width: auto; text-decoration: none;">
                        Limpiar
                    </a>
                <?php endif; ?>
            </div>
            <?php if (!empty($fechaDesde) || !empty($fechaHasta)): ?>
                <div style="font-size: 0.8rem; color: var(--accent);">
                    Rango: <?= Security::escape($fechaDesde ?: 'Inicio') ?> al <?= Security::escape($fechaHasta ?: 'Hoy') ?>
                </div>
            <?php endif; ?>
        </form>

        <?php if (empty($receptions)): ?>
            <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                <svg class="icon" viewBox="0 0 24 24" style="width: 48px; height: 48px; stroke: var(--border-color); margin-bottom: 0.75rem;">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                </svg>
                <p style="font-size: 1rem; margin-bottom: 0.5rem;">
                    <?= (!empty($fechaDesde) || !empty($fechaHasta)) ? 'No se encontraron recepciones en el rango de fechas seleccionado.' : 'No se han registrado recepciones de carne en canal.' ?>
                </p>
                <?php if (!empty($fechaDesde) || !empty($fechaHasta)): ?>
                    <a href="/recepcion" class="btn btn-outline" style="width: auto; margin-top: 0.5rem; text-decoration: none;">
                        Ver Todas las Recepciones
                    </a>
                <?php else: ?>
                    <p style="font-size: 0.85rem;">Haga clic en <strong>+ Nueva Recepción</strong> para registrar la primera entrada.</p>
                <?php endif; ?>
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
                                    <?= number_format((float)$rec['PesoTotalNeto'], 4) ?> <?= Security::escape($rec['Unidad']) ?>
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 700; color: <?= (float)$rec['PesoDisponible'] > 0 ? 'var(--success)' : 'var(--text-muted)' ?>;">
                                    <?= number_format((float)$rec['PesoDisponible'], 4) ?>
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right; color: var(--text-secondary);">
                                    $<?= number_format((float)$rec['CostActCanal'], 4) ?>
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

            <!-- Controles de Paginación -->
            <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
                <?php
                $queryParams = [];
                if (!empty($fechaDesde)) $queryParams['fecha_desde'] = $fechaDesde;
                if (!empty($fechaHasta)) $queryParams['fecha_hasta'] = $fechaHasta;

                $makeUrl = function(int $p) use ($queryParams): string {
                    $params = $queryParams;
                    $params['page'] = $p;
                    return '/recepcion?' . http_build_query($params);
                };

                $curPage = $pagination['page'];
                $totPages = $pagination['total_pages'];
                ?>
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Página <strong><?= $curPage ?></strong> de <strong><?= $totPages ?></strong> (Total: <strong><?= $pagination['total'] ?></strong> recepciones)
                    </div>
                    <div class="pagination">
                        <?php if ($curPage > 1): ?>
                            <a href="<?= $makeUrl($curPage - 1) ?>" class="page-btn">« Anterior</a>
                        <?php else: ?>
                            <span class="page-btn disabled">« Anterior</span>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $curPage - 2);
                        $endPage = min($totPages, $curPage + 2);

                        if ($startPage > 1) {
                            echo '<a href="' . $makeUrl(1) . '" class="page-btn">1</a>';
                            if ($startPage > 2) echo '<span class="page-btn disabled">...</span>';
                        }

                        for ($i = $startPage; $i <= $endPage; $i++) {
                            if ($i === $curPage) {
                                echo '<span class="page-btn active">' . $i . '</span>';
                            } else {
                                echo '<a href="' . $makeUrl($i) . '" class="page-btn">' . $i . '</a>';
                            }
                        }

                        if ($endPage < $totPages) {
                            if ($endPage < $totPages - 1) echo '<span class="page-btn disabled">...</span>';
                            echo '<a href="' . $makeUrl($totPages) . '" class="page-btn">' . $totPages . '</a>';
                        }
                        ?>

                        <?php if ($curPage < $totPages): ?>
                            <a href="<?= $makeUrl($curPage + 1) ?>" class="page-btn">Siguiente »</a>
                        <?php else: ?>
                            <span class="page-btn disabled">Siguiente »</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
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
