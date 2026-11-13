/**
 * ST-Despostadora - Módulo de Recepción de Carne en Canal
 * Vanilla ES6+, 100% compliant con Content-Security-Policy (sin inline handlers).
 */

let metodoActual = 'SOLO_PESO';
let listaPiezasIndividuales = [];
let costoUnitarioActualCanal = 0.0;

function abrirModalRecepcion() {
    const modal = document.getElementById('modalRecepcion');
    if (!modal) return;
    modal.style.display = 'flex';
    modal.classList.add('active');
    limpiarFormularioRecepcion();
}

function cerrarModalRecepcion() {
    const modal = document.getElementById('modalRecepcion');
    if (!modal) return;
    modal.style.display = 'none';
    modal.classList.remove('active');
}

function limpiarFormularioRecepcion() {
    const form = document.getElementById('formNuevaRecepcion');
    if (form) form.reset();
    listaPiezasIndividuales = [];
    cambiarMetodoEntrada('SOLO_PESO');
    renderizarListaIndividual();
}

function cambiarMetodoEntrada(metodo) {
    metodoActual = metodo;
    
    document.querySelectorAll('.metodo-radio-btn').forEach(lbl => lbl.classList.remove('active'));
    if (metodo === 'SOLO_PESO') document.getElementById('lbl_metodo_1')?.classList.add('active');
    if (metodo === 'PESO_CANTIDAD') document.getElementById('lbl_metodo_2')?.classList.add('active');
    if (metodo === 'PESO_INDIVIDUAL') document.getElementById('lbl_metodo_3')?.classList.add('active');

    const panelEstandar = document.getElementById('panel_pesos_estandar');
    const panelIndividual = document.getElementById('panel_pesos_individual');
    const grpCantidad = document.getElementById('grp_cantidad_especies');
    const boxPromedio = document.getElementById('box_promedio_cabeza');

    if (!panelEstandar || !panelIndividual) return;

    if (metodo === 'SOLO_PESO') {
        panelEstandar.style.display = 'block';
        panelIndividual.style.display = 'none';
        if (grpCantidad) grpCantidad.style.display = 'none';
        if (boxPromedio) boxPromedio.style.display = 'none';
    } else if (metodo === 'PESO_CANTIDAD') {
        panelEstandar.style.display = 'block';
        panelIndividual.style.display = 'none';
        if (grpCantidad) grpCantidad.style.display = 'block';
        if (boxPromedio) boxPromedio.style.display = 'block';
    } else if (metodo === 'PESO_INDIVIDUAL') {
        panelEstandar.style.display = 'none';
        panelIndividual.style.display = 'block';
    }
}

function actualizarInfoEspecieSeleccionada() {
    const sel = document.getElementById('rec_codprod');
    if (!sel) return;
    const opt = sel.options[sel.selectedIndex];
    costoUnitarioActualCanal = parseFloat(opt?.getAttribute('data-costo') || '0');
    calcularTotalesEstandar();
}

function calcularTotalesEstandar() {
    const brutoEl = document.getElementById('rec_peso_bruto');
    const taraEl = document.getElementById('rec_tara');
    const netoEl = document.getElementById('rec_peso_neto');
    if (!brutoEl || !taraEl || !netoEl) return;

    const bruto = parseFloat(brutoEl.value || '0');
    const tara = parseFloat(taraEl.value || '0');
    const neto = Math.max(0, bruto - tara);
    netoEl.value = neto.toFixed(2);

    if (metodoActual === 'PESO_CANTIDAD') {
        const cant = parseInt(document.getElementById('rec_cantidad')?.value || '0', 10);
        const lblProm = document.getElementById('lbl_peso_promedio');
        const lblCosto = document.getElementById('lbl_costo_promedio');
        if (cant > 0 && neto > 0) {
            const promPeso = neto / cant;
            const promCosto = promPeso * costoUnitarioActualCanal;
            if (lblProm) lblProm.textContent = promPeso.toFixed(2) + ' kg';
            if (lblCosto) lblCosto.textContent = '$' + promCosto.toFixed(2);
        } else {
            if (lblProm) lblProm.textContent = '0.00 kg';
            if (lblCosto) lblCosto.textContent = '$0.00';
        }
    }
}

function agregarPiezaIndividual() {
    const input = document.getElementById('input_peso_individual');
    if (!input) return;
    const peso = parseFloat(input.value || '0');
    if (peso <= 0) {
        alert('Por favor ingrese un peso válido mayor a cero.');
        input.focus();
        return;
    }

    listaPiezasIndividuales.push(peso);
    input.value = '';
    input.focus();
    renderizarListaIndividual();
}

function eliminarPiezaIndividual(idx) {
    listaPiezasIndividuales.splice(idx, 1);
    renderizarListaIndividual();
}

function renderizarListaIndividual() {
    const cont = document.getElementById('lista_piezas_individuales');
    if (!cont) return;

    if (listaPiezasIndividuales.length === 0) {
        cont.innerHTML = '<span style="color: var(--text-muted); font-size: 0.85rem; text-align: center; display: block; padding: 1rem;">No hay pesajes capturados.</span>';
        document.getElementById('lbl_ind_total_piezas').textContent = '0';
        document.getElementById('lbl_ind_total_peso').textContent = '0.00 kg';
        document.getElementById('lbl_ind_peso_promedio').textContent = '0.00 kg/pieza';
        return;
    }

    let html = '';
    let totalPeso = 0;
    listaPiezasIndividuales.forEach((p, idx) => {
        totalPeso += p;
        html += `
        <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); padding: 0.4rem 0.75rem; border-radius: 6px; font-size: 0.85rem; border: 1px solid var(--border-color);">
            <span><strong>Animal #${idx + 1}:</strong> <span style="color: var(--primary); font-weight: 700;">${p.toFixed(2)} kg</span></span>
            <button type="button" class="btn-eliminar-pieza" data-idx="${idx}" style="background: none; border: none; color: var(--danger); cursor: pointer; font-size: 1rem; line-height: 1;">&times;</button>
        </div>`;
    });

    cont.innerHTML = html;
    const cant = listaPiezasIndividuales.length;
    const prom = cant > 0 ? (totalPeso / cant) : 0;

    document.getElementById('lbl_ind_total_piezas').textContent = cant.toString();
    document.getElementById('lbl_ind_total_peso').textContent = totalPeso.toFixed(2) + ' kg';
    document.getElementById('lbl_ind_peso_promedio').textContent = prom.toFixed(2) + ' kg/pieza';
}

async function guardarRecepcion(e) {
    e.preventDefault();
    const btn = document.getElementById('btnGuardarRecepcion');
    btn.disabled = true;
    btn.textContent = 'Procesando...';

    const form = e.target;
    const formData = new FormData(form);

    if (metodoActual === 'PESO_INDIVIDUAL') {
        if (listaPiezasIndividuales.length === 0) {
            alert('Debe ingresar al menos un pesaje individual.');
            btn.disabled = false;
            btn.textContent = 'Procesar Recepción';
            return;
        }
        formData.append('pesos_individuales', JSON.stringify(listaPiezasIndividuales));
    }

    try {
        const res = await fetch('/api/recepcion/crear', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message || 'Recepción creada exitosamente.');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'No se pudo registrar la recepción.'));
            btn.disabled = false;
            btn.textContent = 'Procesar Recepción';
        }
    } catch (err) {
        alert('Error de conexión al procesar la recepción.');
        btn.disabled = false;
        btn.textContent = 'Procesar Recepción';
    }
}

async function verDetallePiezas(nrorec) {
    document.getElementById('tituloDetallePiezas').textContent = 'Piezas Recibidas: ' + nrorec;
    const modal = document.getElementById('modalDetallePiezas');
    const cont = document.getElementById('contenedorPiezasDetalle');
    cont.innerHTML = '<span style="color: var(--text-muted); font-size: 0.85rem;">Cargando piezas...</span>';
    modal.style.display = 'flex';
    modal.classList.add('active');

    try {
        const res = await fetch('/api/recepcion/detalle?nrorec=' + encodeURIComponent(nrorec));
        const data = await res.json();
        if (data.success) {
            if (data.details.length === 0) {
                cont.innerHTML = '<span style="color: var(--text-muted); font-size: 0.85rem;">No se encontraron piezas individuales.</span>';
                return;
            }
            let html = '';
            data.details.forEach(p => {
                html += `
                <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-main); padding: 0.5rem 0.85rem; border-radius: 6px; border: 1px solid var(--border-color); font-size: 0.85rem;">
                    <span><strong>Animal #${p.NroEspecie}:</strong> <strong style="color: var(--primary);">${Number(p.PesoNeto).toFixed(2)} kg</strong></span>
                    <span class="badge ${p.EstadoDesposte === 'DISPONIBLE' ? 'badge-success' : 'badge-warning'}">${p.EstadoDesposte}</span>
                </div>`;
            });
            cont.innerHTML = html;
        }
    } catch (err) {
        cont.innerHTML = '<span style="color: var(--danger); font-size: 0.85rem;">Error cargando detalle.</span>';
    }
}

function cerrarModalDetallePiezas() {
    const modal = document.getElementById('modalDetallePiezas');
    if (!modal) return;
    modal.style.display = 'none';
    modal.classList.remove('active');
}

// Inicialización de Event Listeners (sin inline handlers, 100% CSP compliant)
document.addEventListener('DOMContentLoaded', () => {
    // Delegación global de clics
    document.addEventListener('click', (e) => {
        const target = e.target.closest('[data-action]');
        if (target) {
            const action = target.dataset.action;
            if (action === 'abrir-modal-recepcion') abrirModalRecepcion();
            else if (action === 'cerrar-modal-recepcion') cerrarModalRecepcion();
            else if (action === 'cerrar-modal-detalle-piezas') cerrarModalDetallePiezas();
            else if (action === 'agregar-pieza-individual') agregarPiezaIndividual();
            return;
        }

        const btnVerPiezas = e.target.closest('.btn-ver-piezas');
        if (btnVerPiezas && btnVerPiezas.dataset.nrorec) {
            verDetallePiezas(btnVerPiezas.dataset.nrorec);
            return;
        }

        const btnEliminarPieza = e.target.closest('.btn-eliminar-pieza');
        if (btnEliminarPieza && btnEliminarPieza.dataset.idx !== undefined) {
            eliminarPiezaIndividual(parseInt(btnEliminarPieza.dataset.idx, 10));
            return;
        }
    });

    // Formulario de recepción
    const formRec = document.getElementById('formNuevaRecepcion');
    if (formRec) formRec.addEventListener('submit', guardarRecepcion);

    // Selector de especie
    const selEspecie = document.getElementById('rec_codprod');
    if (selEspecie) selEspecie.addEventListener('change', actualizarInfoEspecieSeleccionada);

    // Radios de método de entrada
    document.querySelectorAll('input[name="metodo_entrada"]').forEach(radio => {
        radio.addEventListener('change', (e) => cambiarMetodoEntrada(e.target.value));
    });

    // Inputs de pesos
    ['rec_cantidad', 'rec_peso_bruto', 'rec_tara'].forEach(id => {
        const input = document.getElementById(id);
        if (input) input.addEventListener('input', calcularTotalesEstandar);
    });

    // Input de peso individual (Enter para agregar)
    const inputInd = document.getElementById('input_peso_individual');
    if (inputInd) {
        inputInd.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                agregarPiezaIndividual();
            }
        });
    }
});
