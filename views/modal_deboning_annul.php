<?php
declare(strict_types=1);
?>
<!-- MODAL: CONFIRMACIÓN Y ASENTAMIENTO DE ANULACIÓN DE DESPOSTE -->
<div id="modalAnnulOrder" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(20,15,12,0.85); z-index:110; align-items:center; justify-content:center; padding:1.25rem; backdrop-filter:blur(4px);">
    <div class="card" style="width:100%; max-width:560px; box-shadow:0 16px 40px rgba(0,0,0,0.7); border-top:4px solid var(--danger);">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem;">
            <div>
                <h2 style="font-size:1.25rem; font-weight:700; color:var(--text-primary); margin:0; display:flex; align-items:center; gap:0.5rem;">
                    <svg class="icon" viewBox="0 0 24 24" style="width:22px; height:22px; stroke:var(--danger);"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                    Anular Orden de Desposte
                </h2>
                <div style="font-family:monospace; font-size:0.85rem; color:var(--text-secondary); margin-top:0.25rem;" id="annulOrderNumber">
                    DESP-0000000000000000
                </div>
            </div>
            <button type="button" class="btn btn-outline btn-sm" data-action="close-modal-annul-order" style="padding:0.25rem 0.5rem; line-height:1;">✕</button>
        </div>

        <div style="background:rgba(238,103,55,0.08); border:1px solid rgba(238,103,55,0.25); border-radius:8px; padding:0.85rem 1rem; margin-bottom:1.25rem;">
            <div style="font-weight:600; color:var(--danger); font-size:0.9rem; margin-bottom:0.4rem;">
                ⚠️ Acción irreversible de reversión de materia prima:
            </div>
            <ul style="margin:0; padding-left:1.2rem; font-size:0.85rem; color:var(--text-secondary); line-height:1.5;">
                <li>Se liberará la canal ingresada (<strong id="annulPesoCanal" style="color:var(--text-primary);">-</strong>) y regresará al disponible de la recepción <strong id="annulRecepcion" style="color:var(--text-primary);">-</strong>.</li>
                <li id="annulPiezaRow" style="display:none;">La res o pieza individual quedará liberada nuevamente para ser seleccionada.</li>
                <li id="annulCortesRow" style="display:none;">Se descartarán <strong id="annulCortesCount" style="color:var(--danger);">-</strong> corte(s) pesados en esta mesa (quedarán auditados como anulados).</li>
                <li>La mesa quedará liberada inmediatamente. No se enviará ningún documento al ERP Saint.</li>
            </ul>
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label class="form-label" style="font-size:0.85rem; margin-bottom:0.4rem;">
                Motivos Frecuentes (Toque rápido):
            </label>
            <div style="display:grid; grid-template-columns:repeat(2, 1fr); gap:0.4rem; margin-bottom:0.75rem;">
                <button type="button" class="btn btn-outline btn-sm btn-quick-reason" data-reason="Error de selección / mesa equivocada" style="font-size:0.78rem; text-align:left; padding:0.4rem 0.6rem;">
                    🔄 Error de mesa / selección
                </button>
                <button type="button" class="btn btn-outline btn-sm btn-quick-reason" data-reason="Canal con anomalía o decomiso veterinario" style="font-size:0.78rem; text-align:left; padding:0.4rem 0.6rem;">
                    🥩 Anomalía o decomiso en canal
                </button>
                <button type="button" class="btn btn-outline btn-sm btn-quick-reason" data-reason="Falla técnica en sierra de corte o balanza" style="font-size:0.78rem; text-align:left; padding:0.4rem 0.6rem;">
                    ⚙️ Falla en sierra o balanza
                </button>
                <button type="button" class="btn btn-outline btn-sm btn-quick-reason" data-reason="Fin de jornada laboral / suspensión de sala" style="font-size:0.78rem; text-align:left; padding:0.4rem 0.6rem;">
                    ⏱️ Fin de turno / suspensión
                </button>
            </div>

            <label class="form-label" for="inputMotivoAnulacion" style="font-size:0.85rem;">
                Motivo / Justificación Obligatoria *:
            </label>
            <textarea id="inputMotivoAnulacion" class="form-input" rows="3" placeholder="Especifique con claridad la causa de la anulación (mínimo 5 caracteres)..." required style="resize:vertical; font-size:0.9rem;"></textarea>
            <small style="color:var(--text-muted); font-size:0.78rem; display:block; margin-top:4px;">
                Este motivo quedará asentado en la bitácora inmutable de auditoría del sistema.
            </small>
        </div>

        <div id="annulAlertError" class="alert alert-danger" style="display:none; margin-bottom:1rem; font-size:0.85rem;"></div>

        <div style="display:flex; justify-content:flex-end; gap:0.75rem; align-items:center;">
            <button type="button" class="btn btn-outline" data-action="close-modal-annul-order" id="btnCancelAnnulOrder">
                Regresar al Pesaje
            </button>
            <button type="button" class="btn btn-danger" id="btnConfirmAnnulOrder" style="font-weight:700;">
                <svg class="icon" viewBox="0 0 24 24" style="width:16px; height:16px; margin-right:4px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                Confirmar Anulación y Regresar Canal
            </button>
        </div>
    </div>
</div>
