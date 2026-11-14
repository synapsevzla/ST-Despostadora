<?php
declare(strict_types=1);
?>
<!-- MODAL: DETALLES DE DOCUMENTOS EN ESPERA Y CRUCE CON EL ERP SAINT -->
<div id="modalErpDocsDetails" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(20,15,12,0.85); z-index:110; align-items:center; justify-content:center; padding:1rem; backdrop-filter:blur(4px);">
    <div class="card" style="width:100%; max-width:680px; box-shadow:0 16px 40px rgba(0,0,0,0.7); border:1px solid var(--border-color); padding:1.25rem; max-height:92vh; overflow-y:auto;">
        <!-- Cabecera del Modal -->
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
            <div>
                <div style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted); font-weight:700; letter-spacing:0.05em;">
                    Integración y Trazabilidad Saint ERP
                </div>
                <h2 style="font-size:1.25rem; font-weight:700; color:var(--text-primary); margin:0.25rem 0 0 0; display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                    <span id="erpModalOrderTitle">DESP-0000000000000000</span>
                    <span id="erpModalGlobalBadge" class="badge">CONSULTANDO...</span>
                </h2>
            </div>
            <button type="button" class="btn btn-outline" data-action="close-modal-erp-docs" style="padding:0 8px; font-size:1.4rem; line-height:1.2; border:none; color:var(--text-muted);" title="Cerrar">&times;</button>
        </div>

        <!-- Alerta de estado / mensajes -->
        <div id="erpModalAlert" class="alert" style="display:none; margin-bottom:1rem; font-size:0.85rem;"></div>

        <!-- Cuadrícula de los dos documentos (Descargo y Cargo) -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1rem; margin-bottom:1.25rem;">
            <!-- TARJETA 1: DESCARGO DE CANAL (SALIDA) -->
            <div class="card" style="background:rgba(0,0,0,0.2); border:1px solid var(--border-color); padding:1rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                    <strong style="font-size:0.95rem; color:var(--text-primary); display:flex; align-items:center; gap:0.4rem;">
                        <span style="color:#ff7043;"> </span> Descargo de Canal
                    </strong>
                    <span id="erpModalDescargoStatusBadge" class="badge">EN ESPERA</span>
                </div>
                <div style="font-size:0.85rem; line-height:1.6; color:var(--text-secondary);">
                    <div>
                        <span style="color:var(--text-muted);">Doc. en Espera:</span>
                        <strong id="erpModalDescargoNumeroD" style="font-family:monospace; color:var(--primary); font-size:0.95rem;">-</strong>
                    </div>
                    <div>
                        <span style="color:var(--text-muted);">Tipo Original:</span>
                        <span id="erpModalDescargoTipoOpI" style="font-family:monospace; font-weight:700;">R (Espera)</span>
                    </div>
                    <div style="margin-top:0.5rem; padding-top:0.5rem; border-top:1px dashed var(--border-color);">
                        <span style="color:var(--text-muted);">Nuevo Doc. Saint ERP:</span>
                        <div id="erpModalDescargoOficialContainer" style="margin-top:0.25rem;">
                            <strong id="erpModalDescargoNumeroDOficial" style="font-family:monospace; font-size:1.05rem; color:var(--success);">-</strong>
                        </div>
                    </div>
                    <div>
                        <span style="color:var(--text-muted);">Tipo Operación Saint:</span>
                        <strong id="erpModalDescargoTipoFinal" style="font-family:monospace;">-</strong>
                    </div>
                    <div>
                        <span style="color:var(--text-muted);">Fecha Proceso ERP:</span>
                        <span id="erpModalDescargoFecha" style="font-size:0.8rem;">-</span>
                    </div>
                </div>
            </div>

            <!-- TARJETA 2: CARGO DE CORTES (ENTRADA) -->
            <div class="card" style="background:rgba(0,0,0,0.2); border:1px solid var(--border-color); padding:1rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                    <strong style="font-size:0.95rem; color:var(--text-primary); display:flex; align-items:center; gap:0.4rem;">
                        <span style="color:#66bb6a;"> </span> Cargo de Cortes y Merma
                    </strong>
                    <span id="erpModalCargoStatusBadge" class="badge">EN ESPERA</span>
                </div>
                <div style="font-size:0.85rem; line-height:1.6; color:var(--text-secondary);">
                    <div>
                        <span style="color:var(--text-muted);">Doc. en Espera:</span>
                        <strong id="erpModalCargoNumeroD" style="font-family:monospace; color:var(--primary); font-size:0.95rem;">-</strong>
                    </div>
                    <div>
                        <span style="color:var(--text-muted);">Tipo Original:</span>
                        <span id="erpModalCargoTipoOpI" style="font-family:monospace; font-weight:700;">R (Espera)</span>
                    </div>
                    <div style="margin-top:0.5rem; padding-top:0.5rem; border-top:1px dashed var(--border-color);">
                        <span style="color:var(--text-muted);">Nuevo Doc. Saint ERP:</span>
                        <div id="erpModalCargoOficialContainer" style="margin-top:0.25rem;">
                            <strong id="erpModalCargoNumeroDOficial" style="font-family:monospace; font-size:1.05rem; color:var(--success);">-</strong>
                        </div>
                    </div>
                    <div>
                        <span style="color:var(--text-muted);">Tipo Operación Saint:</span>
                        <strong id="erpModalCargoTipoFinal" style="font-family:monospace;">-</strong>
                    </div>
                    <div>
                        <span style="color:var(--text-muted);">Fecha Proceso ERP:</span>
                        <span id="erpModalCargoFecha" style="font-size:0.8rem;">-</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Trazabilidad inmutable en SAITEMOPI.Descrip10 -->
        <div style="background:rgba(0,0,0,0.15); border-radius:6px; padding:0.6rem 0.85rem; margin-bottom:1.25rem; font-size:0.78rem; color:var(--text-muted); line-height:1.4;">
             <strong>Trazabilidad Saint:</strong> El ERP Saint reemplaza el número de espera original (<code>D-...</code> / <code>C-...</code>) por su propio número consecutivo al procesar la operación. La trazabilidad perpetua queda asegurada en la descripción ampliada del producto con los sufijos  (<code>-DES</code> y <code>-CAR</code>).
        </div>

        <!-- Botones de Acción -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
            <button type="button" class="btn btn-outline" id="btnRefreshErpModal" style="display:inline-flex; align-items:center; gap:6px;">
                <svg class="icon" viewBox="0 0 24 24" style="width:15px; height:15px;"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                <span>Verificar Estado ERP en Vivo</span>
            </button>
            <button type="button" class="btn btn-primary" data-action="close-modal-erp-docs" style="min-width:100px; justify-content:center;">
                Cerrar
            </button>
        </div>
    </div>
</div>
