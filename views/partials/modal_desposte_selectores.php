<?php
declare(strict_types=1);

use Config\Security;

$species = $species ?? [];
?>

<!-- MODAL 1: SELECTOR DE ESPECIE / TIPO DE CANAL -->
<div id="modalSelectorEspecie" class="modal-busqueda-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="modalEspecieTitulo">
    <div class="modal-busqueda-card" style="max-width:680px;">
        <div class="modal-busqueda-header">
            <div>
                <h4 id="modalEspecieTitulo" style="display:flex; align-items:center; gap:0.5rem;">
                    <svg class="icon" viewBox="0 0 24 24" style="width:20px; height:20px; color:var(--primary);"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                    Seleccionar Especie / Canal
                </h4>
                <p>Especies cárnicas configuradas en la matriz de desposte</p>
            </div>
            <button type="button" data-action="cerrar-modal-especie" class="picker-btn-clear" style="font-size:1.8rem;" title="Cerrar">&times;</button>
        </div>

        <div class="modal-busqueda-filtros">
            <div class="form-group" style="margin-bottom:0;">
                <input type="text" id="modalEspecieSearchInput" class="form-input" placeholder="🔍 Buscar especie por código o nombre..." autocomplete="off">
            </div>
            <div class="modal-busqueda-info-bar">
                <span id="modalEspecieInfoBar"><?= count($species) ?> especie(s) configurada(s)</span>
                <span style="font-size:0.75rem; color:var(--text-muted);">Toque una especie para seleccionarla</span>
            </div>
        </div>

        <div class="modal-busqueda-resultados" id="modalEspecieResultados" style="max-height:55vh;">
            <div id="modalEspecieLista" style="display:flex; flex-direction:column; gap:0.65rem;">
                <?php foreach ($species as $sp): 
                    $cCanal = (string)($sp['CodProdCanal'] ?? $sp['CodProd'] ?? '');
                    $nCanal = (string)($sp['NombreCanal'] ?? $sp['NombreEspecie'] ?? $cCanal);
                    $unidad = (string)($sp['Unidad'] ?? 'kg');
                    $cortes = (int)($sp['TotalCortes'] ?? 0);
                ?>
                    <div class="producto-item-card especie-modal-item" 
                         data-codprod="<?= Security::escape($cCanal) ?>" 
                         data-nombre="<?= Security::escape($nCanal) ?>"
                         data-unidad="<?= Security::escape($unidad) ?>"
                         tabindex="0" role="button">
                        <div class="producto-item-top">
                            <span class="producto-item-cod">[<?= Security::escape($cCanal) ?>]</span>
                            <span class="badge badge-secondary" style="font-size:0.75rem; font-family:monospace;"><?= $cortes ?> corte(s) asociados</span>
                        </div>
                        <div class="producto-item-title" style="font-size:1.05rem; font-weight:700; margin:0.35rem 0;"><?= Security::escape($nCanal) ?></div>
                        <div class="producto-item-bottom">
                            <span>Unidad: <strong><?= Security::escape($unidad) ?></strong></span>
                            <span class="producto-item-sel-action">Seleccionar Especie →</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="modalEspecieVacio" style="display:none; text-align:center; padding:3rem 1rem; color:var(--text-muted);">
                <svg class="icon" viewBox="0 0 24 24" style="width:40px; height:40px; margin-bottom:0.5rem; opacity:0.5;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <p style="margin:0; font-size:0.95rem; font-weight:600; color:var(--text-secondary);">No se encontraron especies coincidentes</p>
                <p style="margin:0.25rem 0 0; font-size:0.8rem;">Verifique el término ingresado en la búsqueda.</p>
            </div>
        </div>

        <div class="modal-busqueda-footer">
            <span style="font-size:0.82rem; color:var(--text-secondary);">Presione ESC para cerrar</span>
            <button type="button" class="btn btn-outline btn-sm" data-action="cerrar-modal-especie">
                Cerrar
            </button>
        </div>
    </div>
</div>

<!-- MODAL 2: SELECTOR DE RECEPCIÓN DE CANAL CON SALDO -->
<div id="modalSelectorRecepcion" class="modal-busqueda-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="modalRecepcionTitulo">
    <div class="modal-busqueda-card" style="max-width:720px;">
        <div class="modal-busqueda-header">
            <div>
                <h4 id="modalRecepcionTitulo" style="display:flex; align-items:center; gap:0.5rem;">
                    <svg class="icon" viewBox="0 0 24 24" style="width:20px; height:20px; color:var(--primary);"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg>
                    Seleccionar Recepción con Saldo
                </h4>
                <p id="modalRecepcionSubtitulo">Lotes disponibles con peso para desposte</p>
            </div>
            <button type="button" data-action="cerrar-modal-recepcion" class="picker-btn-clear" style="font-size:1.8rem;" title="Cerrar">&times;</button>
        </div>

        <div class="modal-busqueda-filtros">
            <div class="form-group" style="margin-bottom:0;">
                <input type="text" id="modalRecepcionSearchInput" class="form-input" placeholder="🔍 Buscar por N° recepción (REC-...), método o fecha..." autocomplete="off">
            </div>
            <div class="modal-busqueda-info-bar">
                <span id="modalRecepcionInfoBar">Cargando recepciones...</span>
                <span style="font-size:0.75rem; color:var(--text-muted);">FIFO: Se muestran recepciones con saldo disponible</span>
            </div>
        </div>

        <div class="modal-busqueda-resultados" id="modalRecepcionResultados" style="max-height:55vh;">
            <div id="modalRecepcionSpinner" style="display:none; text-align:center; padding:2.5rem; color:var(--primary); font-size:0.9rem; font-weight:600;">
                <span style="display:inline-flex; align-items:center; gap:0.5rem;">
                    <svg class="icon" viewBox="0 0 24 24" style="width:20px; height:20px; animation:spin 1s linear infinite;"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg>
                    Consultando recepciones disponibles...
                </span>
            </div>

            <div id="modalRecepcionLista" style="display:flex; flex-direction:column; gap:0.65rem;">
                <!-- Se llena dinámicamente con las recepciones AJAX -->
            </div>

            <div id="modalRecepcionVacio" style="display:none; text-align:center; padding:3rem 1rem; color:var(--text-muted);">
                <svg class="icon" viewBox="0 0 24 24" style="width:40px; height:40px; margin-bottom:0.5rem; opacity:0.5;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <p style="margin:0; font-size:0.95rem; font-weight:600; color:var(--text-secondary);">Sin recepciones con saldo disponible</p>
                <p style="margin:0.25rem 0 0; font-size:0.8rem;">No hay inventario disponible en canal para esta especie. Registre primero una recepción.</p>
            </div>
        </div>

        <div class="modal-busqueda-footer">
            <span id="modalRecepcionFooterInfo" style="font-size:0.82rem; color:var(--text-secondary);">Seleccione una recepción</span>
            <button type="button" class="btn btn-outline btn-sm" data-action="cerrar-modal-recepcion">
                Cerrar
            </button>
        </div>
    </div>
</div>

<!-- MODAL 3: SELECTOR DE RES / PIEZA INDIVIDUAL (MÉTODO 3) -->
<div id="modalSelectorPieza" class="modal-busqueda-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="modalPiezaTitulo">
    <div class="modal-busqueda-card" style="max-width:680px;">
        <div class="modal-busqueda-header">
            <div>
                <h4 id="modalPiezaTitulo" style="display:flex; align-items:center; gap:0.5rem;">
                    <svg class="icon" viewBox="0 0 24 24" style="width:20px; height:20px; color:var(--primary);"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                    Seleccionar Res / Pieza Individual
                </h4>
                <p id="modalPiezaSubtitulo">Piezas disponibles en la recepción seleccionada</p>
            </div>
            <button type="button" data-action="cerrar-modal-pieza" class="picker-btn-clear" style="font-size:1.8rem;" title="Cerrar">&times;</button>
        </div>

        <div class="modal-busqueda-filtros">
            <div class="form-group" style="margin-bottom:0;">
                <input type="text" id="modalPiezaSearchInput" class="form-input" placeholder="🔍 Filtrar por N° de res (ej. 1, 2, 3...)..." autocomplete="off">
            </div>
            <div class="modal-busqueda-info-bar">
                <span id="modalPiezaInfoBar">Cargando piezas...</span>
                <span style="font-size:0.75rem; color:var(--text-muted);">Toque la res para cargar su pesaje exacto</span>
            </div>
        </div>

        <div class="modal-busqueda-resultados" id="modalPiezaResultados" style="max-height:55vh;">
            <div id="modalPiezaSpinner" style="display:none; text-align:center; padding:2.5rem; color:var(--primary); font-size:0.9rem; font-weight:600;">
                <span style="display:inline-flex; align-items:center; gap:0.5rem;">
                    <svg class="icon" viewBox="0 0 24 24" style="width:20px; height:20px; animation:spin 1s linear infinite;"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg>
                    Cargando piezas individuales...
                </span>
            </div>

            <div id="modalPiezaLista" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:0.75rem;">
                <!-- Se llena dinámicamente con las reses disponibles -->
            </div>

            <div id="modalPiezaVacio" style="display:none; text-align:center; padding:3rem 1rem; color:var(--text-muted);">
                <svg class="icon" viewBox="0 0 24 24" style="width:40px; height:40px; margin-bottom:0.5rem; opacity:0.5;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <p style="margin:0; font-size:0.95rem; font-weight:600; color:var(--text-secondary);">Sin piezas disponibles</p>
                <p style="margin:0.25rem 0 0; font-size:0.8rem;">Todas las piezas de esta recepción ya han sido despostadas o están en proceso.</p>
            </div>
        </div>

        <div class="modal-busqueda-footer">
            <span id="modalPiezaFooterInfo" style="font-size:0.82rem; color:var(--text-secondary);">Seleccione una res</span>
            <button type="button" class="btn btn-outline btn-sm" data-action="cerrar-modal-pieza">
                Cerrar
            </button>
        </div>
    </div>
</div>

<style>
@keyframes spin { 100% { transform: rotate(360deg); } }

.recepcion-item-card {
    background-color: var(--bg-main);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 0.85rem 1rem;
    cursor: pointer;
    transition: all 0.15s ease;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.recepcion-item-card:hover, .recepcion-item-card:focus-visible {
    background-color: #46362f;
    border-color: var(--primary);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
    outline: none;
}

.pieza-item-card {
    background-color: var(--bg-main);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 0.85rem;
    cursor: pointer;
    transition: all 0.15s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0.35rem;
}

.pieza-item-card:hover, .pieza-item-card:focus-visible {
    background-color: #46362f;
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    outline: none;
}
</style>
