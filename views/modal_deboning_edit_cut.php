<?php
declare(strict_types=1);
?>
<!-- MODAL: CORRECCIÓN Y MODIFICACIÓN DE PESO DE CORTE -->
<div id="modalEditCutWeight" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(20,15,12,0.85); z-index:105; align-items:center; justify-content:center; padding:1rem; backdrop-filter:blur(4px);">
    <div class="card" style="width:100%; max-width:440px; box-shadow:0 16px 40px rgba(0,0,0,0.7); border:1px solid var(--border-color); padding:1.25rem;">
        <input type="hidden" id="editCutId" value="">
        <input type="hidden" id="editCutOriginalWeight" value="">

        <div style="background:rgba(0,0,0,0.3); border:1px solid var(--border-color); border-radius:8px; padding:0.6rem 0.85rem; margin-bottom:0.75rem; position:relative;">
            <div style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.04em;">Corregir Pesaje de Corte:</div>
            <div id="editCutDisplay" style="font-size:1.05rem; font-weight:700; color:var(--primary); margin-top:2px; padding-right:1.5rem;">
                ...
            </div>
            <div style="font-size:0.8rem; color:var(--text-secondary); margin-top:2px;">
                Lote: <strong id="editCutBatchDisplay" style="font-family:monospace; color:var(--text-primary);">-</strong> • Peso Actual: <strong id="editCutCurrentWeightDisplay" style="color:var(--text-primary);">-</strong> kg
            </div>
            <button type="button" class="btn btn-outline" data-action="close-modal-edit-cut" style="position:absolute; top:8px; right:8px; padding:0 6px; font-size:1.2rem; line-height:1.2; border:none; color:var(--text-muted);" title="Cerrar">&times;</button>
        </div>

        <div id="modalEditCutAlert" class="alert alert-danger" style="display:none; margin-bottom:0.75rem; padding:0.5rem 0.75rem; font-size:0.85rem;"></div>

        <div style="font-size:0.85rem; font-weight:700; color:var(--text-secondary); margin-bottom:0.4rem; display:flex; align-items:center; gap:0.4rem;">
            <svg class="icon" viewBox="0 0 24 24" style="width:16px; height:16px; stroke:var(--primary);"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><line x1="8" y1="12" x2="16" y2="12"></line></svg>
            Nuevo Peso Corregido
        </div>

        <div class="weigh-input-wrapper" style="margin-bottom:0.6rem;">
            <input type="text" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" id="editCutInput" class="weigh-input" placeholder="0.0000" autocomplete="off" style="font-size:1.5rem; text-align:right; font-weight:800; font-family:monospace;">
            <span class="weigh-unit-badge">kg</span>
        </div>

        <!-- Teclado numérico táctil para corrección rápida -->
        <div id="editKeypadContainer" class="keypad-grid" style="margin-bottom:0.75rem;">
            <button type="button" class="btn btn-secondary keypad-btn" data-edit-key="7">7</button><button type="button" class="btn btn-secondary keypad-btn" data-edit-key="8">8</button><button type="button" class="btn btn-secondary keypad-btn" data-edit-key="9">9</button>
            <button type="button" class="btn btn-secondary keypad-btn" data-edit-key="4">4</button><button type="button" class="btn btn-secondary keypad-btn" data-edit-key="5">5</button><button type="button" class="btn btn-secondary keypad-btn" data-edit-key="6">6</button>
            <button type="button" class="btn btn-secondary keypad-btn" data-edit-key="1">1</button><button type="button" class="btn btn-secondary keypad-btn" data-edit-key="2">2</button><button type="button" class="btn btn-secondary keypad-btn" data-edit-key="3">3</button>
            <button type="button" class="btn btn-secondary keypad-btn" data-edit-key="C" style="background:#5c352a; color:#ff9d80;">C</button><button type="button" class="btn btn-secondary keypad-btn" data-edit-key="0">0</button><button type="button" class="btn btn-secondary keypad-btn" data-edit-key=".">.</button>
        </div>

        <div style="margin-bottom:0.85rem;">
            <label for="editCutMotivo" style="display:block; font-size:0.8rem; font-weight:600; color:var(--text-secondary); margin-bottom:0.25rem;">
                Motivo del Cambio (Obligatorio para auditoría):
            </label>
            <input type="text" id="editCutMotivo" class="form-input" placeholder="Ej: Corrección de tara de bandeja / Error de digitación" maxlength="255" style="width:100%; font-size:0.85rem; padding:0.5rem 0.75rem;">
        </div>

        <div style="display:flex; gap:0.5rem;">
            <button type="button" class="btn btn-outline" data-action="close-modal-edit-cut" style="flex:1; justify-content:center; height:44px;">
                Cancelar
            </button>
            <button type="button" class="btn btn-primary" id="btnSaveEditCut" style="flex:2; justify-content:center; height:44px; font-size:1rem; font-weight:700;">
                <svg class="icon" viewBox="0 0 24 24" style="width:16px; height:16px; margin-right:6px;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                GUARDAR AJUSTE
            </button>
        </div>
    </div>
</div>
