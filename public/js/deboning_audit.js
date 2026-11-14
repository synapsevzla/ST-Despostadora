/**
 * ST-Despostadora - Trazabilidad y Corrección de Pesajes (Bitácora de Mesa)
 * Maneja el modal de bitácora cronológica y la corrección de pesajes con auditoría inmutable.
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Eventos para la Bitácora de Mesa
    document.addEventListener('click', (e) => {
        const btnAudit = e.target.closest('[data-action="show-audit-log"]');
        if (btnAudit) {
            openAuditLogModal();
            return;
        }

        const btnCloseAudit = e.target.closest('[data-action="close-modal-audit"]');
        if (btnCloseAudit) {
            closeAuditLogModal();
            return;
        }

        const btnCloseEdit = e.target.closest('[data-action="close-modal-edit-cut"]');
        if (btnCloseEdit) {
            closeEditCutModal();
            return;
        }

        const btnEditCut = e.target.closest('.btn-edit-cut');
        if (btnEditCut) {
            const cutId = parseInt(btnEditCut.dataset.id, 10);
            const batch = btnEditCut.dataset.lote || '';
            const weight = parseFloat(btnEditCut.dataset.peso || '0');
            const cutName = btnEditCut.dataset.corte || 'Corte';
            openEditCutModal(cutId, batch, weight, cutName);
            return;
        }

        const editKeyBtn = e.target.closest('[data-edit-key]');
        if (editKeyBtn) {
            pressEditKeypad(editKeyBtn.dataset.editKey);
            return;
        }
    });

    const btnSaveEdit = document.getElementById('btnSaveEditCut');
    if (btnSaveEdit) {
        btnSaveEdit.addEventListener('click', submitEditCutWeight);
    }

    const editInput = document.getElementById('editCutInput');
    if (editInput) {
        editInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitEditCutWeight();
            }
        });
    }
});

async function openAuditLogModal() {
    if (!window.currentOrder || !window.currentOrder.NroOrdenDesposte) {
        if (typeof showAlert === 'function') showAlert('No hay orden activa seleccionada.', 'warning');
        return;
    }

    const modal = document.getElementById('modalDeboningAudit');
    const content = document.getElementById('auditLogContent');
    const subtitle = document.getElementById('auditSubtitle');

    if (subtitle) {
        subtitle.textContent = `Orden: ${window.currentOrder.NroOrdenDesposte} (${window.currentOrder.NombreCanal || ''})`;
    }
    if (content) {
        content.innerHTML = '<div style="text-align:center; padding:2rem; color:var(--text-muted);">Cargando registros de auditoría...</div>';
    }
    if (modal) modal.style.display = 'flex';

    try {
        const res = await fetch(`/api/desposte/bitacora?nro_orden=${encodeURIComponent(window.currentOrder.NroOrdenDesposte)}`);
        const json = await res.json();

        if (!json.success || !json.log) {
            content.innerHTML = `<div style="text-align:center; padding:2rem; color:var(--danger);">${json.error || 'Error cargando bitácora'}</div>`;
            return;
        }

        renderAuditTimeline(json.log);
    } catch (e) {
        if (content) {
            content.innerHTML = '<div style="text-align:center; padding:2rem; color:var(--danger);">Error de conexión al consultar bitácora.</div>';
        }
    }
}

function closeAuditLogModal() {
    const modal = document.getElementById('modalDeboningAudit');
    if (modal) modal.style.display = 'none';
}

function renderAuditTimeline(logs) {
    const content = document.getElementById('auditLogContent');
    if (!content) return;

    if (!logs || logs.length === 0) {
        content.innerHTML = '<div style="text-align:center; padding:2.5rem; color:var(--text-muted);">No hay eventos de pesaje o cambios registrados aún en esta orden.</div>';
        return;
    }

    let html = '<div class="audit-timeline" style="display:flex; flex-direction:column; gap:0.6rem;">';

    logs.forEach(item => {
        let badgeColor = 'var(--primary)';
        let badgeBg = 'rgba(235, 94, 40, 0.15)';
        let actionLabel = 'Pesaje Inicial';
        let icon = '⚖️';

        if (item.TipoAccion === 'MODIFICACION_PESO') {
            badgeColor = '#3498db';
            badgeBg = 'rgba(52, 152, 219, 0.15)';
            actionLabel = 'Ajuste de Peso';
            icon = '✏️';
        } else if (item.TipoAccion === 'ANULACION') {
            badgeColor = 'var(--danger)';
            badgeBg = 'rgba(255, 59, 48, 0.15)';
            actionLabel = 'Anulación';
            icon = '🗑️';
        }

        const autor = item.NombreUsuario || item.CodUsua || 'Operador';
        const horaFmt = (item.FechaHora || '').substring(0, 19).replace('T', ' ');
        const corteFmt = item.NombreCorte ? `${item.NombreCorte} (${item.CodProdCorte})` : item.CodProdCorte;

        let deltaHtml = '';
        if (item.TipoAccion === 'MODIFICACION_PESO') {
            const dif = parseFloat(item.DiferenciaPeso || '0');
            const difFmt = (dif >= 0 ? `+${dif.toFixed(4)}` : `${dif.toFixed(4)}`) + ' kg';
            deltaHtml = `
                <div style="font-size:0.85rem; margin-top:0.25rem; font-family:monospace;">
                    De <strong style="color:var(--text-muted); text-decoration:line-through;">${parseFloat(item.PesoAnterior).toFixed(4)} kg</strong>
                    a <strong style="color:var(--primary);">${parseFloat(item.PesoNuevo).toFixed(4)} kg</strong>
                    (<span style="color:${dif >= 0 ? 'var(--success)' : 'var(--danger)'}; font-weight:700;">Δ ${difFmt}</span>)
                </div>
            `;
        } else if (item.TipoAccion === 'PESAJE_INICIAL') {
            deltaHtml = `
                <div style="font-size:0.85rem; margin-top:0.25rem; font-family:monospace;">
                    Peso: <strong style="color:var(--primary); font-size:0.95rem;">${parseFloat(item.PesoNuevo).toFixed(4)} kg</strong>
                </div>
            `;
        } else if (item.TipoAccion === 'ANULACION') {
            deltaHtml = `
                <div style="font-size:0.85rem; margin-top:0.25rem; color:var(--danger); font-family:monospace;">
                    Peso anulado: <strong>${parseFloat(item.PesoAnterior).toFixed(4)} kg</strong>
                </div>
            `;
        }

        const motivoHtml = item.Motivo && item.Motivo.trim() !== ''
            ? `<div style="margin-top:0.3rem; font-size:0.8rem; background:rgba(0,0,0,0.2); padding:0.3rem 0.5rem; border-radius:4px; color:var(--text-secondary);"><strong>Motivo:</strong> ${item.Motivo}</div>`
            : '';

        html += `
            <div style="background:rgba(255,255,255,0.02); border:1px solid var(--border-color); border-left:3px solid ${badgeColor}; border-radius:6px; padding:0.6rem 0.85rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.4rem;">
                    <div style="display:flex; align-items:center; gap:0.4rem;">
                        <span class="badge" style="background:${badgeBg}; color:${badgeColor}; font-weight:700; font-size:0.75rem;">
                            ${icon} ${actionLabel}
                        </span>
                        <strong style="font-size:0.88rem; color:var(--text-primary);">${corteFmt}</strong>
                        <span class="badge" style="background:#55433a; color:var(--primary); font-family:monospace; font-size:0.72rem;">${item.NroLote}</span>
                    </div>
                    <span style="font-size:0.75rem; color:var(--text-muted);">${horaFmt}</span>
                </div>
                ${deltaHtml}
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:0.35rem; font-size:0.78rem; color:var(--text-muted);">
                    <div>👤 <strong>${autor}</strong> <span style="font-size:0.7rem;">(${item.CodUsua})</span></div>
                    <div style="font-size:0.7rem;">IP: ${item.IP || '127.0.0.1'}</div>
                </div>
                ${motivoHtml}
            </div>
        `;
    });

    html += '</div>';
    content.innerHTML = html;
}

function openEditCutModal(id, batch, currentWeight, cutName) {
    const modal = document.getElementById('modalEditCutWeight');
    const alertEl = document.getElementById('modalEditCutAlert');
    if (alertEl) alertEl.style.display = 'none';

    document.getElementById('editCutId').value = id.toString();
    document.getElementById('editCutOriginalWeight').value = currentWeight.toFixed(4);
    document.getElementById('editCutDisplay').textContent = cutName;
    document.getElementById('editCutBatchDisplay').textContent = batch;
    document.getElementById('editCutCurrentWeightDisplay').textContent = currentWeight.toFixed(4);

    const input = document.getElementById('editCutInput');
    const motivo = document.getElementById('editCutMotivo');
    if (input) {
        input.value = currentWeight.toFixed(4);
        setTimeout(() => {
            input.focus();
            input.select();
        }, 80);
    }
    if (motivo) motivo.value = '';

    if (modal) modal.style.display = 'flex';
}

function closeEditCutModal() {
    const modal = document.getElementById('modalEditCutWeight');
    if (modal) modal.style.display = 'none';
}

function pressEditKeypad(key) {
    const input = document.getElementById('editCutInput');
    if (!input) return;
    let val = (input.value || '').trim();
    if (key === 'C') val = '';
    else if (key === '.') val = val.includes('.') ? val : ((val === '') ? '0.' : val + '.');
    else if (!val.includes('.') || (val.split('.')[1] || '').length < 4) val = (val === '0') ? key : val + key;
    input.value = val;
    input.focus();
}

async function submitEditCutWeight() {
    if (!window.currentOrder) return;
    const cutId = parseInt(document.getElementById('editCutId').value, 10);
    const input = document.getElementById('editCutInput');
    const motivoInput = document.getElementById('editCutMotivo');
    const alertEl = document.getElementById('modalEditCutAlert');

    const nuevoPeso = parseFloat(input.value);
    const motivo = (motivoInput.value || '').trim();

    if (isNaN(nuevoPeso) || nuevoPeso <= 0) {
        if (alertEl) {
            alertEl.textContent = 'Ingrese un peso válido mayor a cero.';
            alertEl.style.display = 'block';
        }
        input.focus();
        return;
    }

    if (motivo === '') {
        if (alertEl) {
            alertEl.textContent = 'Debe indicar un motivo obligatorio para el ajuste en la bitácora.';
            alertEl.style.display = 'block';
        }
        motivoInput.focus();
        return;
    }

    const btn = document.getElementById('btnSaveEditCut');
    btn.disabled = true;
    btn.textContent = 'GUARDANDO...';

    const formData = new FormData();
    formData.append('csrf_token', typeof getCsrfToken === 'function' ? getCsrfToken() : '');
    formData.append('nro_orden', window.currentOrder.NroOrdenDesposte);
    formData.append('id', cutId.toString());
    formData.append('nuevo_peso', nuevoPeso.toFixed(4));
    formData.append('motivo', motivo);

    try {
        const res = await fetch('/api/desposte/modificar-corte', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();

        if (json.success && json.balance) {
            closeEditCutModal();
            if (typeof renderBalanceAndTable === 'function') {
                renderBalanceAndTable(json.balance);
            }
            if (typeof DeboningSync !== 'undefined' && json.balance.orden && json.balance.orden.Version) {
                DeboningSync.setVersion(json.balance.orden.Version);
            }
            if (typeof showAlert === 'function') showAlert(json.message, 'success');
            if (typeof playBeep === 'function') playBeep('success');
        } else {
            if (alertEl) {
                alertEl.textContent = json.error || 'Error al actualizar peso';
                alertEl.style.display = 'block';
            }
            if (typeof playBeep === 'function') playBeep('error');
        }
    } catch (e) {
        if (alertEl) {
            alertEl.textContent = 'Error de conexión con el servidor.';
            alertEl.style.display = 'block';
        }
    } finally {
        btn.disabled = false;
        btn.textContent = 'GUARDAR AJUSTE';
    }
}
