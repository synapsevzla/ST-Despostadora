<?php
declare(strict_types=1);
?>
<!-- MODAL: BITÁCORA Y TRAZABILIDAD DE PESAJES -->
<div id="modalDeboningAudit" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(20,15,12,0.85); z-index:110; align-items:center; justify-content:center; padding:1rem; backdrop-filter:blur(4px);">
    <div class="card" style="width:100%; max-width:680px; max-height:85vh; display:flex; flex-direction:column; box-shadow:0 16px 40px rgba(0,0,0,0.7); border:1px solid var(--border-color); padding:1.25rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
            <div>
                <h3 style="font-size:1.15rem; font-weight:700; color:var(--text-primary); margin:0; display:flex; align-items:center; gap:0.5rem;">
                    <svg class="icon" viewBox="0 0 24 24" style="width:18px; height:18px; stroke:var(--primary);"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    Bitácora de Pesajes y Cambios en Mesa
                </h3>
                <span style="font-size:0.8rem; color:var(--text-muted);" id="auditSubtitle">Orden: ...</span>
            </div>
            <button type="button" class="btn btn-outline" data-action="close-modal-audit" style="padding:0 8px; font-size:1.3rem; line-height:1; border:none; color:var(--text-muted);" title="Cerrar">&times;</button>
        </div>

        <div id="auditLogContent" style="flex:1; overflow-y:auto; padding-right:0.35rem; min-height:200px;">
            <div style="text-align:center; padding:2rem; color:var(--text-muted);">
                Cargando historial de pesajes...
            </div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1rem; padding-top:0.75rem; border-top:1px solid var(--border-color);">
            <span style="font-size:0.75rem; color:var(--text-muted);">Registros inmutables auditados por usuario y hora exacta.</span>
            <button type="button" class="btn btn-primary" data-action="close-modal-audit" style="padding:0.4rem 1.25rem;">
                Cerrar
            </button>
        </div>
    </div>
</div>
