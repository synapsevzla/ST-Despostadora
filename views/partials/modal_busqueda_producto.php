<?php
declare(strict_types=1);

use Config\Security;

$instances = $instances ?? [];
?>
<div id="modalBusquedaProducto" class="modal-busqueda-overlay" style="display: none;">
    <div class="modal-busqueda-card">
        <div class="modal-busqueda-header">
            <div>
                <h4 id="modalBusquedaTitulo">Búsqueda Avanzada de Productos</h4>
                <p id="modalBusquedaSubtitulo">Catálogo general ERP (SAPROD) - Desplace hacia abajo para cargar más productos</p>
            </div>
            <button type="button" data-action="cerrar-modal-busqueda" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.5rem; line-height: 1; padding: 0.25rem 0.5rem;" title="Cerrar búsqueda">&times;</button>
        </div>

        <div class="modal-busqueda-filtros">
            <div class="modal-busqueda-grid">
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="text" id="modalBusquedaInput" class="form-input" placeholder="🔍 Buscar por código, nombre o descripción..." autocomplete="off">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <select id="modalBusquedaInstancia" class="form-input">
                        <option value="">-- Todas las Categorías --</option>
                        <?php foreach ($instances as $inst): ?>
                            <option value="<?= Security::escape($inst['CodInst']) ?>"><?= Security::escape($inst['Descrip']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="modal-busqueda-info-bar">
                <span id="modalBusquedaTotal">Cargando productos...</span>
                <!--<span class="badge" style="background: rgba(244, 107, 58, 0.15); color: var(--primary); border: 1px solid rgba(244, 107, 58, 0.3);">
                    Lotes de 50 (Lazy Load)
                </span>-->
            </div>
        </div>

        <div id="modalBusquedaResultados" class="modal-busqueda-resultados">
            <div id="modalBusquedaLista" style="display: flex; flex-direction: column; gap: 0.65rem;">
                <!-- Se llena dinámicamente con lotes de 50 productos -->
            </div>

            <div id="modalBusquedaVacio" style="display: none; text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                <svg class="icon" viewBox="0 0 24 24" style="width: 42px; height: 42px; margin-bottom: 0.5rem; opacity: 0.5;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <p style="margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--text-secondary);">No se encontraron productos coincidentes</p>
                <p style="margin: 0.25rem 0 0; font-size: 0.82rem;">Verifique el término de búsqueda o la categoría seleccionada.</p>
            </div>

            <div id="modalBusquedaSpinner" style="display: none; text-align: center; padding: 1.25rem; color: var(--primary); font-size: 0.85rem; font-weight: 600;">
                <span style="display: inline-flex; align-items: center; gap: 0.5rem;">
                    <svg class="icon" viewBox="0 0 24 24" style="width: 18px; height: 18px; animation: spin 1s linear infinite;"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg>
                    Cargando siguientes 50 productos...
                </span>
            </div>

            <div id="modalBusquedaFin" style="display: none; text-align: center; padding: 1rem; color: var(--text-muted); font-size: 0.8rem; border-top: 1px dashed var(--border-color); margin-top: 0.5rem;">
                ✓ Se han cargado todos los productos coincidentes.
            </div>
        </div>

        <div class="modal-busqueda-footer">
            <span id="modalBusquedaPaginacionInfo" style="font-size: 0.82rem; color: var(--text-secondary);">Mostrando 0 productos</span>
            <button type="button" class="btn" data-action="cerrar-modal-busqueda" style="background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-secondary); width: auto; font-size: 0.85rem; padding: 0.45rem 1rem;">
                Cerrar
            </button>
        </div>
    </div>
</div>
<style>
@keyframes spin { 100% { transform: rotate(360deg); } }
</style>
