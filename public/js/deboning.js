/**
 * ST-Despostadora - Módulo de Pesaje Táctil y Tablero de Desposte
 * Vanilla ES6+, 100% compliant con Content-Security-Policy (sin inline handlers).
 */

let currentOrder = null;
let selectedCutCode = null;
let currentCutsCatalog = [];
const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

// Feedback sonoro nativo con Web Audio API
const audioCtx = (typeof window.AudioContext !== 'undefined' || typeof window.webkitAudioContext !== 'undefined')
    ? new (window.AudioContext || window.webkitAudioContext)() : null;

function playBeep(type = 'success') {
    if (!audioCtx) return;
    try {
        if (audioCtx.state === 'suspended') audioCtx.resume();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        if (type === 'success') {
            osc.frequency.setValueAtTime(880, audioCtx.currentTime);
            gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.12);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.12);
        } else if (type === 'error') {
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(220, audioCtx.currentTime);
            gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.25);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.25);
        }
    } catch (e) {}
}

function showAlert(message, type = 'success') {
    const el = document.getElementById('deboningAlert');
    if (!el) return;
    el.className = `alert alert-${type}`;
    el.textContent = message;
    el.style.display = 'block';
    if (type === 'success') setTimeout(() => { el.style.display = 'none'; }, 4000);
}

function switchView(view) {
    const vBoard = document.getElementById('viewBoard');
    const vStation = document.getElementById('viewStation');
    if (view === 'board') {
        vBoard.style.display = 'block';
        vStation.style.display = 'none';
        refreshActiveOrders();
    } else {
        vBoard.style.display = 'none';
        vStation.style.display = 'block';
    }
}

function pressKeypad(key) {
    const input = document.getElementById('weighInput');
    if (!input) return;
    if (key === 'C') {
        input.value = '';
    } else if (key === '.') {
        if (!input.value.includes('.')) input.value = (input.value === '') ? '0.' : input.value + '.';
    } else {
        if (input.value.includes('.')) {
            const decimals = input.value.split('.')[1] || '';
            if (decimals.length >= 4) return; // Máximo 4 decimales
        }
        input.value += key;
    }
    input.focus();
}

async function loadOrderInStation(nroOrden) {
    try {
        const res = await fetch(`/api/desposte/balance?nro_orden=${encodeURIComponent(nroOrden)}`);
        const json = await res.json();
        if (!json.success || !json.balance) {
            showAlert(json.error || 'No se pudo cargar la orden', 'danger');
            return;
        }

        currentOrder = json.balance.orden;
        switchView('station');
        const stationBadge = document.getElementById('stationMesaBadge');
        if (stationBadge) {
            if (currentOrder.LineaMesa && currentOrder.LineaMesa.trim() !== '') {
                stationBadge.textContent = currentOrder.LineaMesa;
                stationBadge.style.display = 'inline-block';
            } else {
                stationBadge.style.display = 'none';
            }
        }
        document.getElementById('stationOrderTitle').textContent = currentOrder.NroOrdenDesposte;
        document.getElementById('stationCanalSubtitle').textContent = `${currentOrder.NombreCanal} (${currentOrder.CodProdCanal})`;
        await loadSpeciesCutsButtons(currentOrder.CodProdCanal);
        renderBalanceAndTable(json.balance);
    } catch (e) {
        showAlert('Error al cargar la orden seleccionada', 'danger');
    }
}

async function loadSpeciesCutsButtons(canal) {
    const grid = document.getElementById('cutsButtonGrid');
    grid.innerHTML = '<div style="color:var(--text-muted); font-size:0.85rem; grid-column:1/-1;">Cargando cortes...</div>';
    try {
        const res = await fetch(`/api/config/cortes?canal=${encodeURIComponent(canal)}`);
        const json = await res.json();
        if (json.success && json.cortes) {
            currentCutsCatalog = json.cortes;
            grid.innerHTML = '';
            json.cortes.forEach(c => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'cut-btn';
                btn.id = `cut_btn_${c.CodProdCorte}`;
                btn.textContent = c.NombreCorte;
                btn.addEventListener('click', () => selectCutForWeighing(c));
                grid.appendChild(btn);
            });
            if (json.cortes.length > 0) selectCutForWeighing(json.cortes[0]);
        } else {
            grid.innerHTML = '<div style="color:var(--danger); font-size:0.85rem; grid-column:1/-1;">Sin cortes configurados.</div>';
        }
    } catch (e) {
        grid.innerHTML = '<div style="color:var(--danger); font-size:0.85rem; grid-column:1/-1;">Error cargando cortes.</div>';
    }
}

function selectCutForWeighing(corte) {
    selectedCutCode = corte.CodProdCorte;
    document.querySelectorAll('.cut-btn').forEach(b => b.classList.remove('selected'));
    const activeBtn = document.getElementById(`cut_btn_${corte.CodProdCorte}`);
    if (activeBtn) activeBtn.classList.add('selected');
    document.getElementById('selectedCutDisplay').textContent = `${corte.NombreCorte} (${corte.CodProdCorte}) - Venc: ${corte.DiasVencimiento} días`;
    const weighInput = document.getElementById('weighInput');
    weighInput.value = '';
    weighInput.focus();
}

async function registerCutWeight() {
    if (!currentOrder) return showAlert('No hay una orden de desposte activa', 'danger');
    if (!selectedCutCode) {
        playBeep('error');
        return showAlert('Seleccione un corte antes de registrar el pesaje', 'danger');
    }
    const inputWeight = document.getElementById('weighInput');
    const peso = parseFloat(inputWeight.value);
    if (isNaN(peso) || peso <= 0) {
        playBeep('error');
        inputWeight.focus();
        return showAlert('Ingrese un peso válido mayor a cero', 'danger');
    }

    const btn = document.getElementById('btnRegisterCut');
    btn.disabled = true;
    btn.textContent = 'REGISTRANDO...';

    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('nro_orden', currentOrder.NroOrdenDesposte);
    formData.append('codprod_corte', selectedCutCode);
    formData.append('peso_neto', peso.toFixed(4));

    try {
        const res = await fetch('/api/desposte/pesar', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success && json.balance) {
            playBeep('success');
            showAlert(json.message, 'success');
            renderBalanceAndTable(json.balance);
            inputWeight.value = '';
            inputWeight.focus();
        } else {
            playBeep('error');
            showAlert(json.error || 'Error registrando pesaje', 'danger');
        }
    } catch (e) {
        playBeep('error');
        showAlert('Error de conexión al registrar pesaje', 'danger');
    } finally {
        btn.disabled = false;
        btn.textContent = 'REGISTRAR CORTE';
    }
}

async function deleteCutItem(id) {
    if (!confirm('¿Confirma que desea anular este pesaje de corte?')) return;
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('nro_orden', currentOrder.NroOrdenDesposte);
    formData.append('id', id.toString());

    try {
        const res = await fetch('/api/desposte/eliminar-corte', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success && json.balance) {
            showAlert(json.message, 'success');
            renderBalanceAndTable(json.balance);
        } else {
            showAlert(json.error || 'Error eliminando pesaje', 'danger');
        }
    } catch (e) {
        showAlert('Error al procesar la eliminación', 'danger');
    }
}

function renderBalanceAndTable(balance) {
    const ord = balance.orden;
    const cortes = balance.cortes || [];
    currentOrder = ord;

    const isFinalized = (ord.Estado === 'FINALIZADO');

    // Controlar botón de cierre
    const btnCloseStation = document.getElementById('btnPromptCloseStation');
    if (btnCloseStation) {
        btnCloseStation.style.display = isFinalized ? 'none' : 'inline-flex';
    }

    // Controlar botón de registro
    const btnReg = document.getElementById('btnRegisterCut');
    if (btnReg) {
        btnReg.disabled = isFinalized;
        btnReg.textContent = isFinalized ? 'ORDEN FINALIZADA' : 'REGISTRAR CORTE';
    }

    document.getElementById('statPesoCanal').textContent = `${parseFloat(ord.PesoCanalEntrada).toFixed(4)} kg`;
    document.getElementById('statCostoCanal').textContent = `$${parseFloat(ord.CostActCanal).toFixed(2)} /kg`;
    document.getElementById('statPesoCortes').textContent = `${parseFloat(ord.PesoTotalCortes).toFixed(4)} kg`;
    document.getElementById('statCantCortes').textContent = cortes.length.toString();
    document.getElementById('statPesoMerma').textContent = `${parseFloat(ord.PesoMerma).toFixed(4)} kg`;
    document.getElementById('statPerdidaMerma').textContent = `Pérdida: $${parseFloat(ord.PerdidaMermaDinero).toFixed(2)}`;
    document.getElementById('statRendimiento').textContent = `${parseFloat(ord.RendimientoPct).toFixed(2)}%`;
    document.getElementById('statMermaPct').textContent = `${parseFloat(ord.MermaPct).toFixed(2)}%`;

    const descuadreEl = document.getElementById('descuadreAlert');
    descuadreEl.style.display = ord.DescuadreFisico ? 'block' : 'none';

    const tbody = document.getElementById('cutsTableBody');
    if (cortes.length === 0) {
        tbody.innerHTML = `<tr><td colspan="11" style="text-align:center; padding:2rem; color:var(--text-muted);">No se han registrado pesajes todavía.</td></tr>`;
        return;
    }

    tbody.innerHTML = '';
    cortes.forEach(c => {
        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid var(--border-color)';
        const deleteBtnHtml = isFinalized
            ? '<span style="color:var(--text-muted); font-size:0.75rem;">-</span>'
            : `<button type="button" class="btn btn-outline btn-delete-cut" style="padding:2px 6px; font-size:0.75rem; color:var(--danger);" data-id="${c.ID}" title="Eliminar pesaje">&times;</button>`;

        tr.innerHTML = `
            <td style="padding:0.6rem 0.5rem; font-weight:700;">${c.NroItem}</td>
            <td style="padding:0.6rem 0.5rem;"><span class="badge" style="background:#55433a; color:var(--primary); font-family:monospace; font-size:0.8rem;">${c.NroLote}</span></td>
            <td style="padding:0.6rem 0.5rem; font-weight:600; color:var(--text-primary);">${c.NombreCorte}</td>
            <td style="padding:0.6rem 0.5rem; text-align:right; font-weight:700;">${parseFloat(c.PesoNeto).toFixed(4)} kg</td>
            <td style="padding:0.6rem 0.5rem; text-align:right; color:var(--success); font-weight:600;">${parseFloat(c.RendimientoPct).toFixed(2)}%</td>
            <td style="padding:0.6rem 0.5rem; text-align:right; color:var(--text-muted);">$${parseFloat(c.CostActBase).toFixed(2)}</td>
            <td style="padding:0.6rem 0.5rem; text-align:right; color:var(--danger); font-weight:500;">+$${parseFloat(c.CostoMermaKg).toFixed(2)}</td>
            <td style="padding:0.6rem 0.5rem; text-align:right; font-weight:700; color:var(--primary);">$${parseFloat(c.CostoRealUnitario).toFixed(2)}</td>
            <td style="padding:0.6rem 0.5rem; text-align:right; font-weight:700; color:var(--success);">$${parseFloat(c.PrecioVentaSugerido).toFixed(2)}</td>
            <td style="padding:0.6rem 0.5rem; text-align:center; font-size:0.75rem; color:var(--text-muted);">${c.FechaVencimiento}</td>
            <td style="padding:0.6rem 0.5rem; text-align:center;">
                ${deleteBtnHtml}
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function promptCloseOrder() {
    if (!currentOrder) return;
    if (currentOrder.DescuadreFisico) {
        playBeep('error');
        return showAlert('No se puede cerrar la orden con descuadre físico.', 'danger');
    }
    const modal = document.getElementById('modalCloseOrder');
    if (modal) modal.style.display = 'flex';
}

function closeCloseOrderModal() {
    const modal = document.getElementById('modalCloseOrder');
    if (modal) modal.style.display = 'none';
}

async function executeCloseOrder() {
    if (!currentOrder) return;
    const btn = document.getElementById('btnConfirmCloseOrder');
    btn.disabled = true;
    btn.textContent = 'Procesando cierre atómico...';

    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('nro_orden', currentOrder.NroOrdenDesposte);

    try {
        const res = await fetch('/api/desposte/cerrar', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            closeCloseOrderModal();
            showAlert(json.message, 'success');
            playBeep('success');
            switchView('board');
        } else {
            playBeep('error');
            showAlert(json.error || 'Error al cerrar la orden', 'danger');
        }
    } catch (e) {
        playBeep('error');
        showAlert('Error de conexión', 'danger');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Confirmar y Cerrar Desposte';
    }
}

async function refreshActiveOrders() {
    try {
        const res = await fetch('/api/desposte/activas');
        const json = await res.json();
        if (json.success && json.orders) {
            document.getElementById('countActiveOrders').textContent = json.orders.length.toString();
            const grid = document.getElementById('activeOrdersGrid');
            if (json.orders.length === 0) {
                grid.innerHTML = `
                    <div class="card" style="grid-column: 1 / -1; text-align:center; padding:3rem 1.5rem;">
                        <h3 style="font-size:1.1rem; color:var(--text-primary); margin-bottom:0.5rem;">No hay órdenes activas</h3>
                        <p style="color:var(--text-secondary); max-width:450px; margin:0 auto 1.5rem auto; font-size:0.9rem;">
                            Inicie una nueva orden asignando una mesa y una canal.
                        </p>
                        <a href="/desposte/nueva" class="btn btn-primary">Iniciar Primera Orden</a>
                    </div>`;
                return;
            }

            grid.innerHTML = '';
            json.orders.forEach(ord => {
                const card = document.createElement('div');
                card.className = 'card';
                card.style.borderLeft = '4px solid var(--primary)';
                card.style.display = 'flex';
                card.style.flexDirection = 'column';
                card.style.justifyContent = 'space-between';
                card.innerHTML = `
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem;">
                            <div>
                                ${(ord.LineaMesa && ord.LineaMesa.trim() !== '') ? `<span class="badge" style="background:#55433a; color:var(--primary); font-weight:700; font-size:0.8rem;">${ord.LineaMesa}</span>` : ''}
                                <h3 style="font-size:1.05rem; font-weight:700; margin-top:0.35rem; color:var(--text-primary);">${ord.NroOrdenDesposte}</h3>
                            </div>
                            <span class="badge badge-success" style="font-size:0.75rem;">EN PROCESO</span>
                        </div>
                        <div style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:1rem; line-height:1.5;">
                            <div><strong>Especie:</strong> ${ord.NombreCanal} (${ord.CodProdCanal})</div>
                            <div><strong>Recepción:</strong> ${ord.NroRecepcion}</div>
                            <div><strong>Inicio:</strong> ${ord.FechaHoraInicio.substring(0, 16)}</div>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; background:rgba(0,0,0,0.15); padding:0.75rem; border-radius:8px; margin-bottom:1rem;">
                            <div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">Canal Entrada</div>
                                <div style="font-size:1.1rem; font-weight:700; color:var(--text-primary);">${parseFloat(ord.PesoCanalEntrada).toFixed(4)} kg</div>
                            </div>
                            <div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">Cortes Pesados</div>
                                <div style="font-size:1.1rem; font-weight:700; color:var(--primary);">${parseFloat(ord.PesoTotalCortes).toFixed(4)} kg</div>
                            </div>
                            <div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">Rendimiento</div>
                                <div style="font-size:1rem; font-weight:600; color:var(--success);">${parseFloat(ord.RendimientoPct).toFixed(2)}%</div>
                            </div>
                            <div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">Merma Actual</div>
                                <div style="font-size:1rem; font-weight:600; color:var(--danger);">${parseFloat(ord.MermaPct).toFixed(2)}%</div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-operate-order" style="width:100%; justify-content:center;" data-order="${ord.NroOrdenDesposte}">
                        <svg class="icon" viewBox="0 0 24 24" style="width:16px; height:16px; margin-right:6px;"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                        Operar Mesa / Pesar
                    </button>
                `;
                grid.appendChild(card);
            });
        }
    } catch (e) {
        console.error('Error refrescando órdenes:', e);
    }
}

// Inicialización de Event Listeners (sin inline handlers, 100% CSP compliant)
document.addEventListener('DOMContentLoaded', () => {
    // Si viene de abrir una nueva orden con ?order=..., cargar directamente la estación de pesaje
    const urlParams = new URLSearchParams(window.location.search);
    const orderFromUrl = urlParams.get('order');
    if (orderFromUrl) {
        loadOrderInStation(orderFromUrl);
    }

    document.addEventListener('click', (e) => {
        const target = e.target.closest('[data-action]');
        if (target) {
            const action = target.dataset.action;
            if (action === 'view-board') switchView('board');
            else if (action === 'prompt-close-order') promptCloseOrder();
            else if (action === 'close-modal-close-order') closeCloseOrderModal();
            return;
        }

        const btnOperate = e.target.closest('.btn-operate-order');
        if (btnOperate && btnOperate.dataset.order) {
            loadOrderInStation(btnOperate.dataset.order);
            return;
        }

        const btnKey = e.target.closest('.keypad-btn');
        if (btnKey && btnKey.dataset.key) {
            pressKeypad(btnKey.dataset.key);
            return;
        }

        const btnDelCut = e.target.closest('.btn-delete-cut');
        if (btnDelCut && btnDelCut.dataset.id) {
            deleteCutItem(parseInt(btnDelCut.dataset.id, 10));
            return;
        }
    });

    const btnRegCut = document.getElementById('btnRegisterCut');
    if (btnRegCut) btnRegCut.addEventListener('click', registerCutWeight);

    const btnConfClose = document.getElementById('btnConfirmCloseOrder');
    if (btnConfClose) btnConfClose.addEventListener('click', executeCloseOrder);
});
