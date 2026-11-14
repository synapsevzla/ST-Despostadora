<?php
declare(strict_types=1);
?>
<!-- MODAL: CONFIRMACIÓN DE CIERRE DEFINITIVO DE DESPOSTE -->
<div id="modalCloseOrder" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(20,15,12,0.8); z-index:100; align-items:center; justify-content:center; padding:1.5rem; backdrop-filter:blur(3px);">
    <div class="card" style="width:100%; max-width:540px; box-shadow:0 12px 32px rgba(0,0,0,0.6);">
        <h2 style="font-size:1.25rem; font-weight:700; color:var(--text-primary); margin-bottom:0.75rem;">Confirmar Cierre de Orden de Desposte</h2>
        <p style="color:var(--text-secondary); font-size:0.9rem; line-height:1.5; margin-bottom:1rem;">
            Al confirmar, se registrarán internamente los lotes y costos reales, y se emitirán en espera los <strong>documentos de descargo (canal) y cargo (cortes y merma)</strong> hacia el ERP Saint.
        </p>
        <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
            <button type="button" class="btn btn-outline" data-action="close-modal-close-order">Cancelar</button>
            <button type="button" class="btn btn-danger" id="btnConfirmCloseOrder">Confirmar y Cerrar Desposte</button>
        </div>
    </div>
</div>
