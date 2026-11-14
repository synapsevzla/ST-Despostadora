/**
 * ST-Despostadora - Módulo de Configuración Operativa
 * Vanilla ES6+, 100% compliant con Content-Security-Policy.
 */

const byId = id => document.getElementById(id);
const escapeHtml = str => (!str ? '' : String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])));

function switchConfigTab(tabId, btn) {
    document.querySelectorAll('.config-tab-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    const target = byId(tabId);
    if (target) target.style.display = 'block';
    if (btn) btn.classList.add('active');
}

function actualizarPreviewCorrelativo(tipo) {
    const pref = (byId('pref_' + tipo)?.value || '').trim();
    const long = Math.min(20, Math.max(8, parseInt(byId('long_' + tipo)?.value, 10) || 20));
    const num = (parseInt(byId('num_' + tipo)?.value, 10) || 0) + 1;
    const padded = String(num).padStart(Math.max(1, long - pref.length), '0');
    const el = byId('prev_' + tipo);
    if (el) el.textContent = (pref + padded).substring(0, 20);
}

async function guardarConfigGeneral(e) {
    e.preventDefault();
    try {
        const res = await fetch('/api/config/general', { method: 'POST', body: new FormData(e.target) });
        const data = await res.json();
        alert(data.success ? (data.message || 'Configuración guardada exitosamente.') : ('Error: ' + (data.error || 'No se pudo guardar')));
    } catch {
        alert('Error de conexión al guardar configuración.');
    }
}

async function guardarCorrelativo(e, form) {
    e.preventDefault();
    const formData = new FormData(form);
    const tipo = form.dataset.tipo || formData.get('tipo');
    try {
        const res = await fetch('/api/config/correlativo', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            alert(data.message || 'Correlativo actualizado.');
            if (data.preview && byId('prev_' + tipo)) byId('prev_' + tipo).textContent = data.preview;
        } else {
            alert('Error: ' + (data.error || 'No se pudo actualizar'));
        }
    } catch {
        alert('Error de conexión al actualizar correlativo.');
    }
}

function limpiarFormEspecie() {
    const form = byId('formEspecie');
    if (form) form.reset();
    if (byId('esp_codprod_hidden')) byId('esp_codprod_hidden').value = '';
    if (window.ProductoPicker) {
        window.ProductoPicker.limpiar('canal');
        window.ProductoPicker.limpiar('merma');
        window.ProductoPicker.bloquear('canal', false);
    }
    if (byId('tituloFormEspecie')) byId('tituloFormEspecie').textContent = 'Configurar Especie / Canal de Entrada';
    if (byId('btnGuardarEspecie')) byId('btnGuardarEspecie').textContent = 'Guardar Especie';
    if (byId('btnCancelarEdicionEspecie')) byId('btnCancelarEdicionEspecie').style.display = 'none';

    if (byId('esp_margen')) byId('esp_margen').value = '30.0000';
    if (byId('esp_refrig')) byId('esp_refrig').value = '15';
    if (byId('esp_congel')) byId('esp_congel').value = '180';
    if (byId('esp_activo')) byId('esp_activo').value = '1';
}

function editarEspecie(codProd, nombre, merma, nombreMerma, margen, refrig, congel, activo) {
    aplicarDatosEdicionEspecie(codProd, nombre, merma, nombreMerma, margen, refrig, congel, activo);
}

function aplicarDatosEdicionEspecie(codProd, nombre, merma, nombreMerma, margen, refrig, congel, activo) {
    if (byId('esp_codprod_hidden')) byId('esp_codprod_hidden').value = codProd;

    if (window.ProductoPicker) {
        window.ProductoPicker.setSeleccionado('canal', { codprod: codProd, nombre: nombre });
        window.ProductoPicker.bloquear('canal', true);
        window.ProductoPicker.setSeleccionado('merma', { codprod: merma, nombre: nombreMerma || merma });
    }

    if (byId('esp_margen')) byId('esp_margen').value = Number(margen).toFixed(4);
    if (byId('esp_refrig')) byId('esp_refrig').value = refrig;
    if (byId('esp_congel')) byId('esp_congel').value = congel;
    if (byId('esp_activo')) byId('esp_activo').value = activo;

    if (byId('tituloFormEspecie')) byId('tituloFormEspecie').textContent = `Editar Especie: [${codProd}] ${nombre}`;
    if (byId('btnGuardarEspecie')) byId('btnGuardarEspecie').textContent = 'Actualizar Especie';
    if (byId('btnCancelarEdicionEspecie')) byId('btnCancelarEdicionEspecie').style.display = 'inline-block';

    const card = byId('cardFormEspecie') || byId('formEspecie');
    if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function guardarEspecie(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const codCanal = byId('esp_codprod_hidden')?.value || byId('sel_codprod')?.value || '';
    if (!codCanal) {
        alert('Por favor seleccione el Producto Canal.');
        return;
    }
    const codMerma = byId('sel_codprod_merma')?.value || '';
    if (!codMerma) {
        alert('Por favor seleccione el Producto para Merma / Desperdicio.');
        return;
    }
    formData.set('codprod', codCanal);
    formData.set('codprod_merma', codMerma);

    try {
        const res = await fetch('/api/config/especie', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            alert(data.message || 'Especie guardada.');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'No se pudo guardar la especie.'));
        }
    } catch {
        alert('Error de conexión al guardar especie.');
    }
}

async function administrarCortes(codCanal) {
    const panel = byId('panel_cortes_' + codCanal);
    if (!panel) return;
    if (panel.style.display === 'block') {
        panel.style.display = 'none';
        return;
    }
    panel.style.display = 'block';
    await cargarListaCortes(codCanal);
}

async function cargarListaCortes(codCanal) {
    const cont = byId('lista_cortes_' + codCanal);
    if (!cont) return;
    cont.innerHTML = '<span style="color: var(--text-muted); font-size: 0.85rem;">Cargando cortes...</span>';

    try {
        const res = await fetch('/api/config/cortes?canal=' + encodeURIComponent(codCanal));
        const data = await res.json();
        if (!data.success || !data.cuts.length) {
            cont.innerHTML = `<span style="color: var(--text-muted); font-size: 0.85rem;">${!data.success ? 'Error cargando cortes.' : 'No hay cortes vinculados aún.'}</span>`;
            return;
        }

        let html = '<div style="display: flex; flex-direction: column; gap: 0.5rem;">';
        data.cuts.forEach(c => {
            html += `
            <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); padding: 0.65rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); flex-wrap: wrap; gap: 0.5rem;">
                <div>
                    <strong style="color: var(--text-primary); font-size: 0.95rem;">[${c.CodProdCorte}] ${escapeHtml(c.NombreCorte)}</strong>
                    <div style="font-size: 0.8rem; color: var(--text-secondary); display: flex; gap: 1rem; margin-top: 0.2rem; flex-wrap: wrap;">
                        <span>Costo ERP Base: $${Number(c.CostActBase).toFixed(2)}</span>
                        <span>Precio1 ERP: $${Number(c.Precio1ERP).toFixed(2)}</span>
                        <span>% Rendimiento: <strong>${Number(c.RendimientoEsperadoPct).toFixed(2)}%</strong></span>
                        <span>Margen: <strong>${Number(c.MargenUtilidadPct).toFixed(2)}%</strong></span>
                        <span>Vida Útil: <strong>${c.DiasVencimiento} días</strong></span>
                    </div>
                </div>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <button type="button" class="btn btn-outline btn-editar-corte" style="padding: 0.35rem 0.65rem; font-size: 0.8rem; width: auto;"
                            data-canal="${codCanal}" data-corte="${c.CodProdCorte}" data-nombre="${escapeHtml(c.NombreCorte)}"
                            data-rendimiento="${c.RendimientoEsperadoPct}" data-margen="${c.MargenUtilidadPct}" data-dias="${c.DiasVencimiento}">
                        Editar
                    </button>
                    <button type="button" class="btn btn-desvincular-corte" style="background: rgba(238, 103, 55, 0.15); color: var(--danger); border: 1px solid rgba(238, 103, 55, 0.3); padding: 0.35rem 0.65rem; font-size: 0.8rem;" 
                            data-canal="${codCanal}" data-corte="${c.CodProdCorte}">
                        Desvincular
                    </button>
                </div>
            </div>`;
        });
        html += '</div>';
        cont.innerHTML = html;
    } catch {
        cont.innerHTML = '<span style="color: var(--danger); font-size: 0.85rem;">Error cargando cortes.</span>';
    }
}

let currentModalCanal = '';

function abrirModalAgregarCorte(codCanal, nombreCanal) {
    currentModalCanal = codCanal;
    byId('formAgregarCorte')?.reset();
    if (byId('modal_canal_cod')) byId('modal_canal_cod').value = codCanal;
    if (byId('modal_canal_nombre')) byId('modal_canal_nombre').value = `[${codCanal}] ${nombreCanal || codCanal}`;
    if (byId('tituloModalCorte')) byId('tituloModalCorte').textContent = `Asociar Corte a Especie: [${codCanal}]`;
    if (byId('modal_corte_hidden')) byId('modal_corte_hidden').value = '';
    if (byId('grupo_modal_sel_corte')) byId('grupo_modal_sel_corte').style.display = 'block';
    if (byId('grupo_modal_corte_estatico')) byId('grupo_modal_corte_estatico').style.display = 'none';

    if (window.ProductoPicker) {
        window.ProductoPicker.limpiar('corte');
    }

    if (byId('btnSubmitModalCorte')) byId('btnSubmitModalCorte').textContent = 'Vincular Corte';
    if (byId('modal_corte_rendimiento')) byId('modal_corte_rendimiento').value = '0.0000';
    if (byId('modal_corte_margen')) byId('modal_corte_margen').value = '30.0000';
    if (byId('modal_corte_dias')) byId('modal_corte_dias').value = '15';

    const modal = byId('modalCorte');
    if (modal) { modal.style.display = 'flex'; modal.classList.add('active'); }
}

function abrirModalEditarCorte(codCanal, codCorte, nombreCorte, rend, margen, dias) {
    currentModalCanal = codCanal;
    if (byId('modal_canal_cod')) byId('modal_canal_cod').value = codCanal;
    if (byId('modal_canal_nombre')) byId('modal_canal_nombre').value = `[${codCanal}]`;
    if (byId('tituloModalCorte')) byId('tituloModalCorte').textContent = `Editar Corte: [${codCorte}] ${nombreCorte}`;
    if (byId('modal_corte_hidden')) byId('modal_corte_hidden').value = codCorte;

    if (byId('grupo_modal_sel_corte')) byId('grupo_modal_sel_corte').style.display = 'none';
    if (byId('grupo_modal_corte_estatico')) byId('grupo_modal_corte_estatico').style.display = 'block';
    if (byId('modal_corte_nombre_estatico')) byId('modal_corte_nombre_estatico').value = `[${codCorte}] ${nombreCorte}`;

    if (byId('modal_corte_rendimiento')) byId('modal_corte_rendimiento').value = Number(rend).toFixed(4);
    if (byId('modal_corte_margen')) byId('modal_corte_margen').value = Number(margen).toFixed(4);
    if (byId('modal_corte_dias')) byId('modal_corte_dias').value = dias;
    if (byId('btnSubmitModalCorte')) byId('btnSubmitModalCorte').textContent = 'Guardar Cambios';

    const modal = byId('modalCorte');
    if (modal) { modal.style.display = 'flex'; modal.classList.add('active'); }
}

function cerrarModalCorte() {
    const modal = byId('modalCorte');
    if (modal) { modal.style.display = 'none'; modal.classList.remove('active'); }
}

async function guardarNuevoCorte(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const hiddenCorte = byId('modal_corte_hidden')?.value || byId('modal_sel_corte')?.value || '';
    if (!hiddenCorte) {
        alert('Por favor seleccione un Corte a asociar.');
        return;
    }
    formData.set('corte', hiddenCorte);

    try {
        const res = await fetch('/api/config/cortes/agregar', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            cerrarModalCorte();
            await cargarListaCortes(currentModalCanal);
        } else {
            alert('Error: ' + (data.error || 'No se pudo guardar el corte.'));
        }
    } catch {
        alert('Error de conexión al vincular/editar corte.');
    }
}

async function eliminarCorte(codCanal, codCorte) {
    if (!confirm('¿Desea desvincular este corte de la especie?')) return;
    const formData = new FormData();
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
    formData.append('canal', codCanal);
    formData.append('corte', codCorte);

    try {
        const res = await fetch('/api/config/cortes/eliminar', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            await cargarListaCortes(codCanal);
        } else {
            alert('Error: ' + (data.error || 'No se pudo eliminar el corte.'));
        }
    } catch {
        alert('Error al desvincular corte.');
    }
}

// -------------------------------------------------------------
// Funciones para Gestión de Mesas y Líneas Operativas
// -------------------------------------------------------------

function renderizarListaMesas(mesas) {
    const contenedor = byId('contenedorListaMesas');
    const badgeTotal = byId('badgeTotalMesas');
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
    if (byId('mesa_id')) byId('mesa_id').value = '';
    if (byId('mesa_nombre')) byId('mesa_nombre').value = '';
    if (byId('mesa_descripcion')) byId('mesa_descripcion').value = '';
    if (byId('mesa_activo')) byId('mesa_activo').value = '1';
    if (byId('tituloFormMesa')) byId('tituloFormMesa').textContent = 'Nueva Línea o Mesa Operativa';
    if (byId('btnCancelarEdicionMesa')) byId('btnCancelarEdicionMesa').style.display = 'none';
    if (byId('btnGuardarMesa')) byId('btnGuardarMesa').textContent = 'Guardar Mesa';
}

function editarMesa(id, nombre, descripcion, activo) {
    if (byId('mesa_id')) byId('mesa_id').value = id;
    if (byId('mesa_nombre')) byId('mesa_nombre').value = nombre;
    if (byId('mesa_descripcion')) byId('mesa_descripcion').value = descripcion;
    if (byId('mesa_activo')) byId('mesa_activo').value = activo;
    if (byId('tituloFormMesa')) byId('tituloFormMesa').textContent = `Editar Mesa: ${nombre}`;
    if (byId('btnCancelarEdicionMesa')) byId('btnCancelarEdicionMesa').style.display = 'inline-block';
    if (byId('btnGuardarMesa')) byId('btnGuardarMesa').textContent = 'Actualizar Mesa';
    byId('mesa_nombre')?.focus();
}

async function guardarMesa(e) {
    e.preventDefault();
    try {
        const res = await fetch('/api/config/mesas/guardar', { method: 'POST', body: new FormData(e.target) });
        const data = await res.json();
        if (data.success) {
            alert(data.message || 'Mesa guardada exitosamente.');
            cancelarEdicionMesa();
            if (data.mesas) renderizarListaMesas(data.mesas);
        } else {
            alert('Error: ' + (data.error || 'No se pudo guardar la mesa'));
        }
    } catch {
        alert('Error de conexión al guardar la mesa.');
    }
}

async function toggleEstadoMesa(id, nuevoActivo) {
    const formData = new FormData();
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
    formData.append('id', id);
    formData.append('activo', nuevoActivo);

    try {
        const res = await fetch('/api/config/mesas/estado', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success && data.mesas) renderizarListaMesas(data.mesas);
        else if (!data.success) alert('Error: ' + (data.error || 'No se pudo modificar el estado'));
    } catch {
        alert('Error de conexión al cambiar estado de la mesa.');
    }
}

async function eliminarMesa(id, nombre) {
    if (!confirm(`¿Está seguro de eliminar la mesa "${nombre}"? Esta acción solo es posible si no tiene órdenes de desposte asociadas.`)) return;
    const formData = new FormData();
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
    formData.append('id', id);

    try {
        const res = await fetch('/api/config/mesas/eliminar', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            alert(data.message || 'Mesa eliminada correctamente.');
            if (data.mesas) renderizarListaMesas(data.mesas);
        } else {
            alert('Error: ' + (data.error || 'No se pudo eliminar la mesa'));
        }
    } catch {
        alert('Error de conexión al eliminar la mesa.');
    }
}

// -------------------------------------------------------------
// Inicialización de Event Listeners (100% CSP compliant)
// -------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', (e) => {
        const tabBtn = e.target.closest('.tab-btn[data-tab]');
        if (tabBtn) { switchConfigTab(tabBtn.dataset.tab, tabBtn); return; }

        if (e.target.closest('[data-action="cerrar-modal-corte"]')) { cerrarModalCorte(); return; }

        // Pickers de productos para Canal, Merma y Cortes
        const pickerCanal = e.target.closest('#picker_canal');
        if (pickerCanal) {
            if (e.target.closest('#btn_clear_canal')) {
                if (window.ProductoPicker) window.ProductoPicker.limpiar('canal');
                return;
            }
            if (!pickerCanal.classList.contains('disabled') && window.ProductoPicker) {
                window.ProductoPicker.abrir({
                    title: 'Seleccionar Producto Canal',
                    subtitle: 'Elija la materia prima / canal de entrada para desposte',
                    onSelect: (prod) => window.ProductoPicker.setSeleccionado('canal', prod)
                });
            }
            return;
        }

        const pickerMerma = e.target.closest('#picker_merma');
        if (pickerMerma) {
            if (e.target.closest('#btn_clear_merma')) {
                if (window.ProductoPicker) window.ProductoPicker.limpiar('merma');
                return;
            }
            if (window.ProductoPicker) {
                window.ProductoPicker.abrir({
                    title: 'Seleccionar Producto para Merma / Desperdicio',
                    subtitle: 'Elija el producto de inventario donde se computará la merma',
                    onSelect: (prod) => window.ProductoPicker.setSeleccionado('merma', prod)
                });
            }
            return;
        }

        const pickerCorte = e.target.closest('#picker_corte');
        if (pickerCorte) {
            if (e.target.closest('#btn_clear_corte')) {
                if (window.ProductoPicker) window.ProductoPicker.limpiar('corte');
                return;
            }
            if (window.ProductoPicker) {
                window.ProductoPicker.abrir({
                    title: 'Seleccionar Corte a Asociar',
                    subtitle: `Elija el producto cárnico para la especie [${currentModalCanal}]`,
                    excludeCodProd: currentModalCanal,
                    onSelect: (prod) => window.ProductoPicker.setSeleccionado('corte', prod)
                });
            }
            return;
        }

        const btnEditarEsp = e.target.closest('.btn-editar-especie');
        if (btnEditarEsp) {
            const d = btnEditarEsp.dataset;
            editarEspecie(d.codprod, d.nombre, d.merma, d.nombreMerma, d.margen, d.refrig, d.congel, d.activo);
            return;
        }

        if (e.target.closest('#btnCancelarEdicionEspecie') || e.target.closest('#btnNuevaEspecie')) {
            limpiarFormEspecie();
            return;
        }

        const btnAdmin = e.target.closest('.btn-admin-cortes');
        if (btnAdmin && btnAdmin.dataset.canal) { administrarCortes(btnAdmin.dataset.canal); return; }

        const btnAbrirModal = e.target.closest('.btn-abrir-modal-corte');
        if (btnAbrirModal && btnAbrirModal.dataset.canal) {
            abrirModalAgregarCorte(btnAbrirModal.dataset.canal, btnAbrirModal.dataset.nombre);
            return;
        }

        const btnEditarCorte = e.target.closest('.btn-editar-corte');
        if (btnEditarCorte) {
            const d = btnEditarCorte.dataset;
            abrirModalEditarCorte(d.canal, d.corte, d.nombre, d.rendimiento, d.margen, d.dias);
            return;
        }

        const btnDesvincular = e.target.closest('.btn-desvincular-corte');
        if (btnDesvincular && btnDesvincular.dataset.canal && btnDesvincular.dataset.corte) {
            eliminarCorte(btnDesvincular.dataset.canal, btnDesvincular.dataset.corte);
            return;
        }

        const btnEditarMesa = e.target.closest('.btn-editar-mesa');
        if (btnEditarMesa) {
            editarMesa(parseInt(btnEditarMesa.dataset.id, 10), btnEditarMesa.dataset.nombre, btnEditarMesa.dataset.descripcion, btnEditarMesa.dataset.activo);
            return;
        }

        if (e.target.closest('#btnCancelarEdicionMesa')) { cancelarEdicionMesa(); return; }

        const btnToggleMesa = e.target.closest('.btn-toggle-mesa');
        if (btnToggleMesa) {
            toggleEstadoMesa(parseInt(btnToggleMesa.dataset.id, 10), parseInt(btnToggleMesa.dataset.activo, 10));
            return;
        }

        const btnEliminarMesa = e.target.closest('.btn-eliminar-mesa');
        if (btnEliminarMesa) {
            eliminarMesa(parseInt(btnEliminarMesa.dataset.id, 10), btnEliminarMesa.dataset.nombre);
            return;
        }
    });

    document.addEventListener('input', (e) => {
        const inputCorr = e.target.closest('.corr-input');
        if (inputCorr && inputCorr.dataset.tipo) actualizarPreviewCorrelativo(inputCorr.dataset.tipo);
    });

    byId('formConfigGeneral')?.addEventListener('submit', guardarConfigGeneral);
    byId('formEspecie')?.addEventListener('submit', guardarEspecie);
    byId('formAgregarCorte')?.addEventListener('submit', guardarNuevoCorte);
    byId('formMesa')?.addEventListener('submit', guardarMesa);

    document.querySelectorAll('.form-correlativo').forEach(f => {
        f.addEventListener('submit', (e) => guardarCorrelativo(e, f));
    });
});
