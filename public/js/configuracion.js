/**
 * ST-Despostadora - Módulo de Configuración Operativa
 * Vanilla ES6+, 100% compliant con Content-Security-Policy (sin inline handlers).
 */

function switchConfigTab(tabId, btn) {
    document.querySelectorAll('.config-tab-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    
    const target = document.getElementById(tabId);
    if (target) target.style.display = 'block';
    if (btn) btn.classList.add('active');
}

function actualizarPreviewCorrelativo(tipo) {
    const prefEl = document.getElementById('pref_' + tipo);
    const longEl = document.getElementById('long_' + tipo);
    const numEl = document.getElementById('num_' + tipo);
    if (!prefEl || !longEl || !numEl) return;

    const pref = prefEl.value.trim();
    let long = parseInt(longEl.value, 10) || 20;
    let num = (parseInt(numEl.value, 10) || 0) + 1;
    
    if (long > 20) long = 20;
    if (long < 8) long = 8;
    
    const espacio = Math.max(1, long - pref.length);
    const padded = String(num).padStart(espacio, '0');
    const preview = (pref + padded).substring(0, 20);
    
    const el = document.getElementById('prev_' + tipo);
    if (el) el.textContent = preview;
}

async function guardarConfigGeneral(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    
    try {
        const res = await fetch('/api/config/general', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message || 'Configuración guardada exitosamente.');
        } else {
            alert('Error: ' + (data.error || 'No se pudo guardar'));
        }
    } catch (err) {
        alert('Error de conexión al guardar configuración.');
    }
}

async function guardarCorrelativo(e, form) {
    e.preventDefault();
    const formData = new FormData(form);
    const tipo = form.dataset.tipo || formData.get('tipo');
    
    try {
        const res = await fetch('/api/config/correlativo', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message || 'Correlativo actualizado correctamente.');
            if (data.preview) {
                const el = document.getElementById('prev_' + tipo);
                if (el) el.textContent = data.preview;
            }
        } else {
            alert('Error: ' + (data.error || 'No se pudo actualizar'));
        }
    } catch (err) {
        alert('Error de conexión al actualizar correlativo.');
    }
}

let productosDisponiblesCache = [];

async function cargarProductosEspecie() {
    const instEl = document.getElementById('filtro_instancia');
    const inst = instEl ? instEl.value : '';
    try {
        const res = await fetch('/api/config/productos?codinst=' + encodeURIComponent(inst));
        const data = await res.json();
        if (data.success) {
            productosDisponiblesCache = data.products;
            
            const selCanal = document.getElementById('sel_codprod');
            const selMerma = document.getElementById('sel_codprod_merma');
            if (!selCanal || !selMerma) return;
            
            selCanal.innerHTML = '<option value="">-- Seleccione Producto Canal --</option>';
            selMerma.innerHTML = '<option value="">-- Seleccione Producto Merma --</option>';
            
            data.products.forEach(p => {
                const optCanal = document.createElement('option');
                optCanal.value = p.CodProd;
                optCanal.textContent = `[${p.CodProd}] ${p.DescripcionCompleta} (${p.Unidad}) - Costo: $${Number(p.CostAct).toFixed(2)}`;
                selCanal.appendChild(optCanal);
                
                const optMerma = document.createElement('option');
                optMerma.value = p.CodProd;
                optMerma.textContent = `[${p.CodProd}] ${p.DescripcionCompleta} (${p.Unidad})`;
                selMerma.appendChild(optMerma);
            });
        }
    } catch (err) {
        console.error('Error cargando productos:', err);
    }
}

async function guardarEspecie(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    
    try {
        const res = await fetch('/api/config/especie', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message || 'Especie guardada.');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'No se pudo guardar la especie.'));
        }
    } catch (err) {
        alert('Error de conexión al guardar especie.');
    }
}

function limpiarFormEspecie() {
    const form = document.getElementById('formEspecie');
    if (form) form.reset();
}

async function administrarCortes(codCanal) {
    const panel = document.getElementById('panel_cortes_' + codCanal);
    if (!panel) return;
    
    if (panel.style.display === 'block') {
        panel.style.display = 'none';
        return;
    }
    
    panel.style.display = 'block';
    await cargarListaCortes(codCanal);
}

async function cargarListaCortes(codCanal) {
    const cont = document.getElementById('lista_cortes_' + codCanal);
    if (!cont) return;
    
    cont.innerHTML = '<span style="color: var(--text-muted); font-size: 0.85rem;">Cargando cortes...</span>';
    
    try {
        const res = await fetch('/api/config/cortes?canal=' + encodeURIComponent(codCanal));
        const data = await res.json();
        if (data.success) {
            if (data.cuts.length === 0) {
                cont.innerHTML = '<span style="color: var(--text-muted); font-size: 0.85rem;">No hay cortes vinculados aún a esta especie.</span>';
                return;
            }
            
            let html = '<div style="display: flex; flex-direction: column; gap: 0.5rem;">';
            data.cuts.forEach(c => {
                html += `
                <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); padding: 0.65rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); flex-wrap: wrap; gap: 0.5rem;">
                    <div>
                        <strong style="color: var(--text-primary); font-size: 0.95rem;">[${c.CodProdCorte}] ${c.NombreCorte}</strong>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); display: flex; gap: 1rem; margin-top: 0.2rem; flex-wrap: wrap;">
                            <span>Costo ERP Base: $${Number(c.CostActBase).toFixed(2)}</span>
                            <span>Precio1 ERP: $${Number(c.Precio1ERP).toFixed(2)}</span>
                            <span>Margen Sugerido: ${c.MargenUtilidadPct}%</span>
                            <span>Vida Útil: ${c.DiasVencimiento} días</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-desvincular-corte" style="background: rgba(238, 103, 55, 0.15); color: var(--danger); border: 1px solid rgba(238, 103, 55, 0.3); padding: 0.35rem 0.65rem; font-size: 0.8rem;" data-canal="${codCanal}" data-corte="${c.CodProdCorte}">
                        Desvincular
                    </button>
                </div>
                `;
            });
            html += '</div>';
            cont.innerHTML = html;
        }
    } catch (err) {
        cont.innerHTML = '<span style="color: var(--danger); font-size: 0.85rem;">Error cargando cortes.</span>';
    }
}

let currentModalCanal = '';
async function abrirModalAgregarCorte(codCanal) {
    currentModalCanal = codCanal;
    document.getElementById('modal_canal_cod').value = codCanal;
    
    const modalNombre = document.getElementById('modal_canal_nombre');
    modalNombre.value = codCanal;
    
    const selCorte = document.getElementById('modal_sel_corte');
    selCorte.innerHTML = '<option value="">-- Cargando productos disponibles --</option>';
    
    const modal = document.getElementById('modalCorte');
    modal.style.display = 'flex';
    modal.classList.add('active');
    
    try {
        const res = await fetch('/api/config/productos');
        const data = await res.json();
        if (data.success) {
            selCorte.innerHTML = '<option value="">-- Seleccione Corte a Asociar --</option>';
            data.products.forEach(p => {
                if (p.CodProd !== codCanal) {
                    const opt = document.createElement('option');
                    opt.value = p.CodProd;
                    opt.textContent = `[${p.CodProd}] ${p.DescripcionCompleta} (${p.Unidad}) - Costo: $${Number(p.CostAct).toFixed(2)}`;
                    selCorte.appendChild(opt);
                }
            });
        }
    } catch (err) {
        selCorte.innerHTML = '<option value="">Error cargando cortes</option>';
    }
}

function cerrarModalCorte() {
    const modal = document.getElementById('modalCorte');
    if (!modal) return;
    modal.style.display = 'none';
    modal.classList.remove('active');
}

async function guardarNuevoCorte(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    
    try {
        const res = await fetch('/api/config/cortes/agregar', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            cerrarModalCorte();
            await cargarListaCortes(currentModalCanal);
        } else {
            alert('Error: ' + (data.error || 'No se pudo vincular el corte.'));
        }
    } catch (err) {
        alert('Error de conexión al vincular corte.');
    }
}

async function eliminarCorte(codCanal, codCorte) {
    if (!confirm('¿Desea desvincular este corte de la especie?')) return;
    
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('canal', codCanal);
    formData.append('corte', codCorte);
    
    try {
        const res = await fetch('/api/config/cortes/eliminar', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            await cargarListaCortes(codCanal);
        } else {
            alert('Error: ' + (data.error || 'No se pudo eliminar el corte.'));
        }
    } catch (err) {
        alert('Error al desvincular corte.');
    }
}

// -------------------------------------------------------------
// Funciones para Gestión de Mesas y Líneas Operativas
// -------------------------------------------------------------

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function renderizarListaMesas(mesas) {
    const contenedor = document.getElementById('contenedorListaMesas');
    const badgeTotal = document.getElementById('badgeTotalMesas');
    if (!contenedor) return;

    const total = mesas.length;
    if (badgeTotal) {
        badgeTotal.className = `badge ${total > 0 ? 'badge-success' : 'badge-warning'}`;
        badgeTotal.textContent = `${total} ${total === 1 ? 'mesa' : 'mesas'}`;
    }

    if (total === 0) {
        contenedor.innerHTML = `
            <div class="empty-state-mesas" style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted); background: var(--bg-main); border: 1px dashed var(--border-color); border-radius: 8px;">
                <svg class="icon" viewBox="0 0 24 24" style="width: 36px; height: 36px; margin-bottom: 0.5rem; opacity: 0.5;"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                <p style="margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--text-secondary);">No hay mesas o líneas configuradas</p>
                <p style="margin: 0.25rem 0 0; font-size: 0.82rem;">El sistema abrirá órdenes de desposte sin requerir asignación de mesa.</p>
            </div>`;
        return;
    }

    let html = '<div style="display: flex; flex-direction: column; gap: 0.75rem;">';
    mesas.forEach(m => {
        const esActiva = parseInt(m.Activo, 10) === 1;
        html += `<div class="item-mesa-card" style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.85rem 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <strong style="font-size: 1rem; color: var(--text-primary);">${escapeHtml(m.Nombre)}</strong>
                    <span class="badge ${esActiva ? 'badge-success' : 'badge-warning'}" style="font-size: 0.7rem;">${esActiva ? 'ACTIVA' : 'INACTIVA'}</span>
                </div>
                ${m.Descripcion ? `<span style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-top: 0.2rem;">${escapeHtml(m.Descripcion)}</span>` : ''}
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button type="button" class="btn btn-outline btn-sm btn-editar-mesa" data-id="${m.ID}" data-nombre="${escapeHtml(m.Nombre)}" data-descripcion="${escapeHtml(m.Descripcion || '')}" data-activo="${m.Activo}" style="padding: 0.35rem 0.65rem; font-size: 0.8rem; width: auto;">Editar</button>
                <button type="button" class="btn btn-sm btn-toggle-mesa" data-id="${m.ID}" data-activo="${esActiva ? 0 : 1}" style="background: var(--bg-sidebar); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 0.35rem 0.65rem; font-size: 0.8rem; width: auto;">${esActiva ? 'Desactivar' : 'Activar'}</button>
                <button type="button" class="btn btn-outline btn-sm btn-eliminar-mesa" data-id="${m.ID}" data-nombre="${escapeHtml(m.Nombre)}" style="border-color: rgba(220, 53, 69, 0.4); color: var(--danger); padding: 0.35rem 0.65rem; font-size: 0.8rem; width: auto;">Eliminar</button>
            </div>
        </div>`;
    });
    html += '</div>';
    contenedor.innerHTML = html;
}

function cancelarEdicionMesa() {
    const idEl = document.getElementById('mesa_id');
    const nomEl = document.getElementById('mesa_nombre');
    const descEl = document.getElementById('mesa_descripcion');
    const actEl = document.getElementById('mesa_activo');
    const titEl = document.getElementById('tituloFormMesa');
    const btnCancel = document.getElementById('btnCancelarEdicionMesa');
    const btnGuardar = document.getElementById('btnGuardarMesa');

    if (idEl) idEl.value = '';
    if (nomEl) nomEl.value = '';
    if (descEl) descEl.value = '';
    if (actEl) actEl.value = '1';
    if (titEl) titEl.textContent = 'Nueva Línea o Mesa Operativa';
    if (btnCancel) btnCancel.style.display = 'none';
    if (btnGuardar) btnGuardar.textContent = 'Guardar Mesa';
}

function editarMesa(id, nombre, descripcion, activo) {
    const idEl = document.getElementById('mesa_id');
    const nomEl = document.getElementById('mesa_nombre');
    const descEl = document.getElementById('mesa_descripcion');
    const actEl = document.getElementById('mesa_activo');
    const titEl = document.getElementById('tituloFormMesa');
    const btnCancel = document.getElementById('btnCancelarEdicionMesa');
    const btnGuardar = document.getElementById('btnGuardarMesa');

    if (idEl) idEl.value = id;
    if (nomEl) nomEl.value = nombre;
    if (descEl) descEl.value = descripcion;
    if (actEl) actEl.value = activo;
    if (titEl) titEl.textContent = `Editar Mesa: ${nombre}`;
    if (btnCancel) btnCancel.style.display = 'inline-block';
    if (btnGuardar) btnGuardar.textContent = 'Actualizar Mesa';

    if (nomEl) nomEl.focus();
}

async function guardarMesa(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    try {
        const res = await fetch('/api/config/mesas/guardar', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message || 'Mesa guardada exitosamente.');
            cancelarEdicionMesa();
            if (data.mesas) {
                renderizarListaMesas(data.mesas);
            }
        } else {
            alert('Error: ' + (data.error || 'No se pudo guardar la mesa'));
        }
    } catch (err) {
        alert('Error de conexión al guardar la mesa.');
    }
}

async function toggleEstadoMesa(id, nuevoActivo) {
    const csrfTokenEl = document.querySelector('input[name="csrf_token"]');
    const csrfToken = csrfTokenEl ? csrfTokenEl.value : '';

    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('id', id);
    formData.append('activo', nuevoActivo);

    try {
        const res = await fetch('/api/config/mesas/estado', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            if (data.mesas) {
                renderizarListaMesas(data.mesas);
            }
        } else {
            alert('Error: ' + (data.error || 'No se pudo modificar el estado'));
        }
    } catch (err) {
        alert('Error de conexión al cambiar estado de la mesa.');
    }
}

async function eliminarMesa(id, nombre) {
    if (!confirm(`¿Está seguro de eliminar la mesa "${nombre}"? Esta acción solo es posible si no tiene órdenes de desposte asociadas.`)) {
        return;
    }

    const csrfTokenEl = document.querySelector('input[name="csrf_token"]');
    const csrfToken = csrfTokenEl ? csrfTokenEl.value : '';

    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('id', id);

    try {
        const res = await fetch('/api/config/mesas/eliminar', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message || 'Mesa eliminada correctamente.');
            if (data.mesas) {
                renderizarListaMesas(data.mesas);
            }
        } else {
            alert('Error: ' + (data.error || 'No se pudo eliminar la mesa'));
        }
    } catch (err) {
        alert('Error de conexión al eliminar la mesa.');
    }
}

// Inicialización de Event Listeners (sin inline handlers, 100% CSP compliant)
document.addEventListener('DOMContentLoaded', () => {
    cargarProductosEspecie();

    // Pestañas
    document.addEventListener('click', (e) => {
        const tabBtn = e.target.closest('.tab-btn[data-tab]');
        if (tabBtn) {
            switchConfigTab(tabBtn.dataset.tab, tabBtn);
            return;
        }

        const btnCerrarModal = e.target.closest('[data-action="cerrar-modal-corte"]');
        if (btnCerrarModal) {
            cerrarModalCorte();
            return;
        }

        const btnAdmin = e.target.closest('.btn-admin-cortes');
        if (btnAdmin && btnAdmin.dataset.canal) {
            administrarCortes(btnAdmin.dataset.canal);
            return;
        }

        const btnAbrirModal = e.target.closest('.btn-abrir-modal-corte');
        if (btnAbrirModal && btnAbrirModal.dataset.canal) {
            abrirModalAgregarCorte(btnAbrirModal.dataset.canal);
            return;
        }

        const btnDesvincular = e.target.closest('.btn-desvincular-corte');
        if (btnDesvincular && btnDesvincular.dataset.canal && btnDesvincular.dataset.corte) {
            eliminarCorte(btnDesvincular.dataset.canal, btnDesvincular.dataset.corte);
            return;
        }

        const btnNuevaEsp = e.target.closest('#btnNuevaEspecie');
        if (btnNuevaEsp) {
            limpiarFormEspecie();
            return;
        }

        const btnEditarMesa = e.target.closest('.btn-editar-mesa');
        if (btnEditarMesa) {
            editarMesa(
                parseInt(btnEditarMesa.dataset.id, 10),
                btnEditarMesa.dataset.nombre,
                btnEditarMesa.dataset.descripcion,
                btnEditarMesa.dataset.activo
            );
            return;
        }

        const btnCancelMesa = e.target.closest('#btnCancelarEdicionMesa');
        if (btnCancelMesa) {
            cancelarEdicionMesa();
            return;
        }

        const btnToggleMesa = e.target.closest('.btn-toggle-mesa');
        if (btnToggleMesa) {
            toggleEstadoMesa(
                parseInt(btnToggleMesa.dataset.id, 10),
                parseInt(btnToggleMesa.dataset.activo, 10)
            );
            return;
        }

        const btnEliminarMesa = e.target.closest('.btn-eliminar-mesa');
        if (btnEliminarMesa) {
            eliminarMesa(
                parseInt(btnEliminarMesa.dataset.id, 10),
                btnEliminarMesa.dataset.nombre
            );
            return;
        }
    });

    // Inputs dinámicos de correlativos
    document.addEventListener('input', (e) => {
        const inputCorr = e.target.closest('.corr-input');
        if (inputCorr && inputCorr.dataset.tipo) {
            actualizarPreviewCorrelativo(inputCorr.dataset.tipo);
        }
    });

    // Formularios
    const formConfigGen = document.getElementById('formConfigGeneral');
    if (formConfigGen) formConfigGen.addEventListener('submit', guardarConfigGeneral);

    const formEsp = document.getElementById('formEspecie');
    if (formEsp) formEsp.addEventListener('submit', guardarEspecie);

    const formAddCorte = document.getElementById('formAgregarCorte');
    if (formAddCorte) formAddCorte.addEventListener('submit', guardarNuevoCorte);

    const formMesa = document.getElementById('formMesa');
    if (formMesa) formMesa.addEventListener('submit', guardarMesa);

    document.querySelectorAll('.form-correlativo').forEach(f => {
        f.addEventListener('submit', (e) => guardarCorrelativo(e, f));
    });

    // Filtro de instancia
    const filtroInst = document.getElementById('filtro_instancia');
    if (filtroInst) filtroInst.addEventListener('change', cargarProductosEspecie);
});
