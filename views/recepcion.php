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
            <button type="button" class="btn btn-primary" style="width: auto; min-width: 190px;" data-action="abrir-modal-recepcion">
                <svg class="icon" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                + Nueva Recepción
            </button>
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

<!-- Modal Formulario de Nueva Recepción -->
<div id="modalRecepcion" class="sidebar-overlay" style="display: none; align-items: center; justify-content: center; z-index: 100;">
    <div class="card" style="max-width: 680px; width: 95%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
            <div>
                <h4 style="margin: 0; font-size: 1.2rem; color: var(--text-primary);">Nueva Entrada de Carne en Canal</h4>
                <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-secondary);">Seleccione especie y el método de pesaje para la recepción.</p>
            </div>
            <button type="button" data-action="cerrar-modal-recepcion" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.5rem;">&times;</button>
        </div>

        <form id="formNuevaRecepcion">
            <input type="hidden" name="csrf_token" value="<?= Security::escape($csrf_token) ?>">
            
            <!-- Datos Base -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="rec_codprod">Especie / Canal Habilitada</label>
                    <select name="codprod" id="rec_codprod" class="form-input" required>
                        <option value="">-- Seleccione Especie --</option>
                        <?php foreach ($species as $sp): ?>
                            <option value="<?= Security::escape($sp['CodProd']) ?>" data-costo="<?= (float)$sp['CostActCanal'] ?>" data-unidad="<?= Security::escape($sp['Unidad']) ?>">
                                [<?= Security::escape($sp['CodProd']) ?>] <?= Security::escape($sp['NombreEspecie']) ?> - Costo: $<?= number_format((float)$sp['CostActCanal'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="rec_codubic">Depósito de Entrada</label>
                    <select name="codubic" id="rec_codubic" class="form-input" required>
                        <?php foreach ($deposits as $dep): ?>
                            <option value="<?= Security::escape($dep['CodUbic']) ?>" <?= $defaultCodUbic === $dep['CodUbic'] ? 'selected' : '' ?>>
                                [<?= Security::escape($dep['CodUbic']) ?>] <?= Security::escape($dep['Descrip']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Selector de los 3 Métodos de Entrada -->
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label">Método de Registro de la Canal</label>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 0.75rem;">
                    <label class="metodo-radio-btn active" id="lbl_metodo_1">
                        <input type="radio" name="metodo_entrada" value="SOLO_PESO" checked>
                        <span style="font-weight: 700; display: block; font-size: 0.95rem;">1. Solo Peso</span>
                        <span style="font-size: 0.75rem; color: var(--text-muted);">Peso global neto recibido</span>
                    </label>
                    <label class="metodo-radio-btn" id="lbl_metodo_2">
                        <input type="radio" name="metodo_entrada" value="PESO_CANTIDAD">
                        <span style="font-weight: 700; display: block; font-size: 0.95rem;">2. Peso y Cantidad</span>
                        <span style="font-size: 0.75rem; color: var(--text-muted);">Lote con cálculo de promedio</span>
                    </label>
                    <label class="metodo-radio-btn" id="lbl_metodo_3">
                        <input type="radio" name="metodo_entrada" value="PESO_INDIVIDUAL">
                        <span style="font-weight: 700; display: block; font-size: 0.95rem;">3. Pieza por Pieza</span>
                        <span style="font-size: 0.75rem; color: var(--text-muted);">Pesaje individual por res/canal</span>
                    </label>
                </div>
            </div>

            <!-- Panel para Método 1: Solo Peso y Método 2: Peso y Cantidad -->
            <div id="panel_pesos_estandar">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group" id="grp_cantidad_especies" style="display: none; margin-bottom: 0;">
                        <label class="form-label">N° de Cabezas / Especies</label>
                        <input type="number" name="cantidad" id="rec_cantidad" class="form-input" min="1" value="1">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Peso Bruto (Kg)</label>
                        <input type="number" step="0.01" min="0" name="peso_bruto" id="rec_peso_bruto" class="form-input" value="0.00">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Tara (Kg)</label>
                        <input type="number" step="0.01" min="0" name="tara" id="rec_tara" class="form-input" value="0.00">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Peso Neto (Kg)</label>
                        <input type="number" step="0.01" min="0" name="peso_neto" id="rec_peso_neto" class="form-input" value="0.00" readonly style="background: var(--bg-main); font-weight: 700; color: var(--primary);">
                    </div>
                </div>

                <!-- Resumen en Vivo para Método 2 -->
                <div id="box_promedio_cabeza" style="display: none; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                        <span>Peso Promedio por Cabeza: <strong id="lbl_peso_promedio" style="color: var(--primary);">0.00 kg</strong></span>
                        <span>Costo Estimado por Cabeza: <strong id="lbl_costo_promedio" style="color: var(--success);">$0.00</strong></span>
                    </div>
                </div>
            </div>

            <!-- Panel para Método 3: Peso por Especie Separada (Pesaje Individual) -->
            <div id="panel_pesos_individual" style="display: none; margin-bottom: 1.25rem;">
                <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem;">
                    <label class="form-label" style="margin-bottom: 0.5rem;">Captura Rápida de Pesaje Individual (Balanza / Teclado)</label>
                    
                    <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                        <input type="number" step="0.01" min="0.1" id="input_peso_individual" class="form-input" placeholder="Ingrese peso en Kg (ej. 295.4)" style="font-size: 1.15rem; font-weight: 700;">
                        <button type="button" class="btn btn-primary" style="width: auto; min-width: 140px;" data-action="agregar-pieza-individual">
                            + Agregar
                        </button>
                    </div>

                    <!-- Resumen del Lote Individual -->
                    <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); padding: 0.75rem; border-radius: 6px; margin-bottom: 0.75rem; font-size: 0.9rem; flex-wrap: wrap; gap: 0.5rem;">
                        <span>Piezas: <strong id="lbl_ind_total_piezas" style="color: var(--primary);">0</strong></span>
                        <span>Peso Total: <strong id="lbl_ind_total_peso" style="color: var(--primary);">0.00 kg</strong></span>
                        <span>Promedio: <strong id="lbl_ind_peso_promedio" style="color: var(--accent);">0.00 kg/pieza</strong></span>
                    </div>

                    <!-- Lista de Piezas Registradas -->
                    <div id="lista_piezas_individuales" style="max-height: 180px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.35rem; padding-right: 0.25rem;">
                        <span style="color: var(--text-muted); font-size: 0.85rem; text-align: center; display: block; padding: 1rem;">
                            No hay pesajes capturados. Ingrese los pesos de cada novillo/canal individualmente.
                        </span>
                    </div>
                </div>
            </div>

            <!-- Observaciones -->
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" for="rec_observaciones">Observaciones / Nro de Guía de Matadero</label>
                <input type="text" name="observaciones" id="rec_observaciones" class="form-input" placeholder="Ej. Guía Matadero #49102, Transporte refrigerado #12">
            </div>

            <!-- Botones de Acción -->
            <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button type="button" class="btn" data-action="cerrar-modal-recepcion" style="background: var(--bg-sidebar); border: 1px solid var(--border-color); color: var(--text-secondary); width: auto;">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary" style="width: auto; min-width: 190px;" id="btnGuardarRecepcion">
                    Procesar Recepción
                </button>
            </div>
        </form>
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
