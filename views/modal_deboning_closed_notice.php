<?php
declare(strict_types=1);
?>
<!-- MODAL DE NOTIFICACIÓN BLOQUEANTE: ORDEN FINALIZADA POR OTRO USUARIO -->
<div id="modalOrderClosedNotice" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(20,15,12,0.92); z-index:150; align-items:center; justify-content:center; padding:1.5rem; backdrop-filter:blur(6px);">
    <div class="card" style="width:100%; max-width:520px; box-shadow:0 20px 50px rgba(0,0,0,0.8); border:2px solid var(--danger); text-align:center; padding:2rem 1.5rem;">
        <div style="width:64px; height:64px; border-radius:50%; background:rgba(255,59,48,0.15); display:flex; align-items:center; justify-content:center; margin:0 auto 1.25rem auto;">
            <svg class="icon" viewBox="0 0 24 24" style="width:36px; height:36px; stroke:var(--danger);"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        </div>

        <h2 style="font-size:1.35rem; font-weight:800; color:var(--text-primary); margin-bottom:0.75rem;">
            Orden de Desposte Finalizada
        </h2>

        <p style="color:var(--text-secondary); font-size:0.95rem; line-height:1.5; margin-bottom:1.25rem;" id="orderClosedNoticeText">
            Esta orden de desposte ha sido cerrada y finalizada por otro usuario en la mesa.
        </p>

        <div style="background:rgba(0,0,0,0.25); border:1px solid var(--border-color); border-radius:8px; padding:0.75rem 1rem; margin-bottom:1.5rem; text-align:left; font-size:0.85rem; color:var(--text-secondary);">
            <div><strong>Operador de Cierre:</strong> <span id="closedNoticeUser" style="color:var(--primary); font-weight:700;">-</span></div>
            <div><strong>Hora de Cierre:</strong> <span id="closedNoticeTime" style="color:var(--text-primary);">-</span></div>
            <div style="margin-top:0.25rem; font-size:0.8rem; color:var(--text-muted);">Los documentos de inventario en espera han sido puestos en Saint ERP.</div>
        </div>

        <button type="button" class="btn btn-primary" id="btnRedirectClosedBoard" style="width:100%; justify-content:center; height:46px; font-weight:700; font-size:1rem;">
            Volver al Tablero de Mesas (<span id="closedNoticeCountdown">4</span>s)
        </button>
    </div>
</div>
