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

    document.querySelectorAll('.form-correlativo').forEach(f => {
        f.addEventListener('submit', (e) => guardarCorrelativo(e, f));
    });

    // Filtro de instancia
    const filtroInst = document.getElementById('filtro_instancia');
    if (filtroInst) filtroInst.addEventListener('change', cargarProductosEspecie);
});
