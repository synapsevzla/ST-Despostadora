/**
 * ST-Despostadora - Módulo de Pesaje Táctil y Tablero de Desposte
 * Vanilla ES6+, 100% compliant con Content-Security-Policy.
 */
let currentOrder = null;
let selectedCutCode = null;
let currentCutsCatalog = [];
const getCsrfToken = () => document.querySelector('input[name="csrf_token"]')?.value
    || document.getElementById('csrfToken')?.value
    || '';

// Feedback sonoro nativo con Web Audio API
const audioCtx = (typeof window.AudioContext !== 'undefined' || typeof window.webkitAudioContext !== 'undefined')
    ? new (window.AudioContext || window.webkitAudioContext)() : null;
function playBeep(type = 'success') {
    if (!audioCtx) return;
    try {
        if (audioCtx.state === 'suspended') audioCtx.resume();
        const osc = audioCtx.createOscillator(), gain = audioCtx.createGain();
        osc.connect(gain); gain.connect(audioCtx.destination);
        const isSuccess = (type === 'success');
        osc.type = isSuccess ? 'sine' : 'sawtooth';
        osc.frequency.setValueAtTime(isSuccess ? 880 : 220, audioCtx.currentTime);
        gain.gain.setValueAtTime(isSuccess ? 0.15 : 0.2, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + (isSuccess ? 0.12 : 0.25));
        osc.start(); osc.stop(audioCtx.currentTime + (isSuccess ? 0.12 : 0.25));
    } catch (e) {}
}

function showAlert(message, type = 'success') {
    const el = document.getElementById('deboningAlert');
    if (el) {
        el.className = `alert alert-${type}`;
        el.textContent = message;
        el.style.display = 'block';
        if (type === 'success') setTimeout(() => { el.style.display = 'none'; }, 4000);
    }
    const modalAlert = document.getElementById('modalWeighAlert');
    if (modalAlert) {
        modalAlert.className = `alert alert-${type}`;
        modalAlert.textContent = message;
        modalAlert.style.display = 'block';
        if (type === 'success') setTimeout(() => { modalAlert.style.display = 'none'; }, 4000);
    }
}

const getBasePath = () => window.location.pathname.startsWith('/desposte') ? '/desposte' : '/deboning';

function switchView(view) {
    const vBoard = document.getElementById('viewBoard');
    const vStation = document.getElementById('viewStation');
    const isBoard = (view === 'board');
    document.body.classList.toggle('station-active', !isBoard);
    vBoard.style.display = isBoard ? 'block' : 'none';
    vStation.style.display = isBoard ? 'none' : 'block';
    if (isBoard) {
        if (typeof DeboningSync !== 'undefined') DeboningSync.stop();
        window.history.replaceState({}, '', getBasePath());
        sessionStorage.removeItem('st_active_deboning_order');
        const initialEl = document.getElementById('initialOrderNumber');
        if (initialEl) initialEl.value = '';
        currentOrder = null;
        refreshActiveOrders();
    }
}

function pressKeypad(key) {
    const input = document.getElementById('weighInput');
    if (!input) return;
    let val = (input.value || '').trim();
    if (key === 'C') val = '';
    else if (key === '.') val = val.includes('.') ? val : ((val === '') ? '0.' : val + '.');
    else if (!val.includes('.') || (val.split('.')[1] || '').length < 4) val = (val === '0') ? key : val + key;
    input.value = val;
    input.focus();
}

function openWeighModal(corte) {
    if (!corte) return;
    selectedCutCode = corte.CodProdCorte;
    const codeTrim = String(corte.CodProdCorte).trim();
    document.querySelectorAll('.cut-table-row').forEach(r => r.classList.toggle('selected', String(r.dataset.code).trim() === codeTrim));
    document.querySelectorAll('.cut-item-card').forEach(c => c.classList.toggle('selected', String(c.dataset.code).trim() === codeTrim));

    const disp = document.getElementById('selectedCutDisplay');
    if (disp) {
        disp.textContent = `${corte.NombreCorte} (${corte.CodProdCorte}) - Venc: ${corte.DiasVencimiento || 30} días`;
    }

    const modalAlert = document.getElementById('modalWeighAlert');
    if (modalAlert) modalAlert.style.display = 'none';

    const modal = document.getElementById('modalWeighCalculator');
    if (modal) modal.style.display = 'flex';

    const input = document.getElementById('weighInput');
    if (input) {
        input.value = '';
        setTimeout(() => input.focus(), 60);
    }
}

function closeWeighModal() {
    const modalAlert = document.getElementById('modalWeighAlert');
    if (modalAlert) modalAlert.style.display = 'none';
    const modal = document.getElementById('modalWeighCalculator');
    if (modal) modal.style.display = 'none';
}

async function loadOrderInStation(nroOrden) {
    try {
        const res = await fetch(`/api/desposte/balance?nro_orden=${encodeURIComponent(nroOrden)}`);
        const json = await res.json();
        if (!json.success || !json.balance) return showAlert(json.error || 'No se pudo cargar la orden', 'danger');

        currentOrder = json.balance.orden;
        window.currentOrder = currentOrder;
        switchView('station');
        window.history.replaceState({ order: nroOrden }, '', `${getBasePath()}?order=${encodeURIComponent(nroOrden)}`);
        sessionStorage.setItem('st_active_deboning_order', nroOrden);

        const stationBadge = document.getElementById('stationMesaBadge');
        if (stationBadge) {
            const hasMesa = currentOrder.LineaMesa && currentOrder.LineaMesa.trim() !== '';
            stationBadge.textContent = hasMesa ? currentOrder.LineaMesa : '';
            stationBadge.style.display = hasMesa ? 'inline-block' : 'none';
        }
        document.getElementById('stationOrderTitle').textContent = currentOrder.NroOrdenDesposte;
        document.getElementById('stationCanalSubtitle').textContent = `${currentOrder.NombreCanal} (${currentOrder.CodProdCanal})`;

        if (json.balance.especie_cortes && json.balance.especie_cortes.length > 0) {
            currentCutsCatalog = json.balance.especie_cortes;
            currentCutsCatalog.sort((a, b) => (a.NombreCorte || '').localeCompare(b.NombreCorte || '', 'es', { sensitivity: 'base' }));
        } else {
            await loadSpeciesCuts(currentOrder.CodProdCanal);
        }

        renderBalanceAndTable(json.balance);
        if (typeof DeboningSync !== 'undefined' && currentOrder.Estado === 'EN_PROCESO') {
            DeboningSync.start(currentOrder.Version || 1);
        }
    } catch (e) {
        showAlert('Base de datos o servidor fuera de línea por favor contacte a soporte técnico', 'danger');
    }
}

async function loadSpeciesCuts(canal) {
    try {
        const res = await fetch(`/api/config/cortes?canal=${encodeURIComponent(canal)}`);
        const json = await res.json();
        if (json.success && json.cortes) {
            currentCutsCatalog = json.cortes;
            currentCutsCatalog.sort((a, b) => (a.NombreCorte || '').localeCompare(b.NombreCorte || '', 'es', { sensitivity: 'base' }));
        }
    } catch (e) {}
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
    formData.append('csrf_token', getCsrfToken());
    formData.append('nro_orden', currentOrder.NroOrdenDesposte);
    formData.append('codprod_corte', selectedCutCode);
    formData.append('peso_neto', peso.toFixed(4));

    try {
        const res = await fetch('/api/desposte/pesar', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success && json.balance) {
            closeWeighModal();
            playBeep('success');
            showAlert(json.message, 'success');
            if (json.balance.especie_cortes && json.balance.especie_cortes.length > 0) {
                currentCutsCatalog = json.balance.especie_cortes;
                currentCutsCatalog.sort((a, b) => (a.NombreCorte || '').localeCompare(b.NombreCorte || '', 'es', { sensitivity: 'base' }));
            }
            renderBalanceAndTable(json.balance);
            if (typeof DeboningSync !== 'undefined' && json.balance.orden && json.balance.orden.Version) {
                DeboningSync.setVersion(json.balance.orden.Version);
            }
        } else {
            playBeep('error');
            showAlert(json.error || 'Error registrando pesaje', 'danger');
        }
    } catch (e) {
        playBeep('error');
        showAlert('Base de datos o servidor fuera de línea por favor contacte a soporte técnico', 'danger');
    } finally {
        btn.disabled = false;
        btn.textContent = 'REGISTRAR CORTE';
    }
}

async function deleteCutItem(id) {
    if (!confirm('¿Confirma que desea anular este pesaje de corte?')) return;
    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('nro_orden', currentOrder.NroOrdenDesposte);
    formData.append('id', id.toString());

    try {
        const res = await fetch('/api/desposte/eliminar-corte', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success && json.balance) {
            showAlert(json.message, 'success');
            if (json.balance.especie_cortes && json.balance.especie_cortes.length > 0) {
                currentCutsCatalog = json.balance.especie_cortes;
                currentCutsCatalog.sort((a, b) => (a.NombreCorte || '').localeCompare(b.NombreCorte || '', 'es', { sensitivity: 'base' }));
            }
            renderBalanceAndTable(json.balance);
            if (typeof DeboningSync !== 'undefined' && json.balance.orden && json.balance.orden.Version) {
                DeboningSync.setVersion(json.balance.orden.Version);
            }
        } else {
            showAlert(json.error || 'Error eliminando pesaje', 'danger');
        }
    } catch (e) {
        showAlert('Base de datos o servidor fuera de línea por favor contacte a soporte técnico', 'danger');
    }
}

function renderBalanceAndTable(balance) {
    const ord = balance.orden;
    const cortesPesados = balance.cortes || [];
    currentOrder = ord;

    if (balance.especie_cortes && balance.especie_cortes.length > 0) {
        currentCutsCatalog = balance.especie_cortes;
    }
    currentCutsCatalog.sort((a, b) => (a.NombreCorte || '').localeCompare(b.NombreCorte || '', 'es', { sensitivity: 'base' }));

    window.lastBalanceData = balance;
    const isOperable = (ord.Estado === 'EN_PROCESO');
    const isFinalized = (ord.Estado === 'FINALIZADO');
    const btnClose = document.getElementById('btnPromptCloseStation');
    if (btnClose) btnClose.style.display = isOperable ? 'inline-flex' : 'none';

    const btnAnnul = document.getElementById('btnPromptAnnulStation');
    if (btnAnnul) btnAnnul.style.display = isOperable ? 'inline-flex' : 'none';

    const btnStationErp = document.getElementById('btnStationErpDocs');
    const badgeStationErp = document.getElementById('stationErpBadge');
    if (btnStationErp && badgeStationErp) {
        const hasErp = isFinalized || (ord.DocDescargoEnviado == 1 || ord.DocCargoEnviado == 1);
        btnStationErp.style.display = hasErp ? 'inline-flex' : 'none';
        if (hasErp) {
            const cruce = ord.EstadoCruceERP || 'PENDIENTE_CIERRE';
            badgeStationErp.className = 'badge';
            if (cruce === 'TOTALMENTE_PROCESADO') {
                badgeStationErp.className += ' badge-success';
                badgeStationErp.textContent = 'PROCESADO (OK)';
            } else if (cruce.startsWith('ERROR_')) {
                badgeStationErp.className += ' badge-danger';
                badgeStationErp.textContent = 'ERROR ERP';
            } else if (cruce === 'DESCARGO_OK_CARGO_PEND' || cruce === 'CARGO_OK_DESCARGO_PEND') {
                badgeStationErp.className += ' badge-warning';
                badgeStationErp.textContent = 'PARCIAL';
            } else {
                badgeStationErp.style.background = 'rgba(30, 144, 255, 0.15)';
                badgeStationErp.style.color = '#2196f3';
                badgeStationErp.textContent = 'DOCS EN ESPERA';
            }
        }
    }

    document.getElementById('statPesoCanal').textContent = `${parseFloat(ord.PesoCanalEntrada).toFixed(4)} kg`;
    document.getElementById('statCostoCanal').textContent = `$${parseFloat(ord.CostActCanal).toFixed(2)} /kg`;
    document.getElementById('statPesoCortes').textContent = `${parseFloat(ord.PesoTotalCortes).toFixed(4)} kg`;
    document.getElementById('statCantCortes').textContent = cortesPesados.length.toString();
    document.getElementById('statPesoMerma').textContent = `${parseFloat(ord.PesoMerma).toFixed(4)} kg`;
    document.getElementById('statPerdidaMerma').textContent = `Pérdida: $${parseFloat(ord.PerdidaMermaDinero).toFixed(2)}`;
    document.getElementById('statRendimiento').textContent = `${parseFloat(ord.RendimientoPct).toFixed(2)}%`;
    document.getElementById('statMermaPct').textContent = `${parseFloat(ord.MermaPct).toFixed(2)}%`;
    const descuadreEl = document.getElementById('descuadreAlert');
    if (descuadreEl) descuadreEl.style.display = ord.DescuadreFisico ? 'block' : 'none';

    // Lista combinada de cortes de la especie ordenada alfabéticamente
    const allCuts = [...currentCutsCatalog];
    cortesPesados.forEach(cp => {
        const cpCodeTrim = String(cp.CodProdCorte).trim();
        if (!allCuts.some(c => String(c.CodProdCorte).trim() === cpCodeTrim)) {
            allCuts.push({
                CodProdCorte: cpCodeTrim,
                NombreCorte: cp.NombreCorte,
                DiasVencimiento: 30,
                CostActBase: cp.CostActBase,
                Precio1ERP: cp.PrecioVentaSugerido
            });
        }
    });
    allCuts.sort((a, b) => (a.NombreCorte || '').localeCompare(b.NombreCorte || '', 'es', { sensitivity: 'base' }));
    currentCutsCatalog = allCuts;

    const tbody = document.getElementById('cutsTableBody');
    const cardsContainer = document.getElementById('cutsCardsContainer');
    if (allCuts.length === 0) {
        if (tbody) tbody.innerHTML = `<tr><td colspan="11" style="text-align:center; padding:2rem; color:var(--text-muted);">No hay cortes configurados para esta especie.</td></tr>`;
        if (cardsContainer) cardsContainer.innerHTML = `<div style="text-align:center; padding:2rem 1rem; color:var(--text-muted);">No hay cortes configurados para esta especie.</div>`;
        return;
    }

    if (tbody) tbody.innerHTML = '';
    if (cardsContainer) cardsContainer.innerHTML = '';

    allCuts.forEach(cut => {
        const cutCodeTrim = String(cut.CodProdCorte).trim();
        const weighed = cortesPesados.filter(p => String(p.CodProdCorte).trim() === cutCodeTrim);
        const isWeighed = (weighed.length > 0);
        const isSelected = (selectedCutCode && String(selectedCutCode).trim() === cutCodeTrim);
        const totalPeso = weighed.reduce((s, w) => s + parseFloat(w.PesoNeto || 0), 0);
        const totalRend = weighed.reduce((s, w) => s + parseFloat(w.RendimientoPct || 0), 0);

        const pesoFmt = isWeighed ? totalPeso.toFixed(4) : '0.0000';
        const rendFmt = isWeighed ? totalRend.toFixed(2) : '0.00';
        const costBaseFmt = parseFloat(cut.CostActBase || (isWeighed ? weighed[0].CostActBase : 0)).toFixed(2);
        const costMermaFmt = isWeighed ? parseFloat(weighed[0].CostoMermaKg || 0).toFixed(2) : '-';
        const costRealFmt = isWeighed ? parseFloat(weighed[0].CostoRealUnitario || 0).toFixed(2) : '-';
        const pSugFmt = isWeighed ? parseFloat(weighed[0].PrecioVentaSugerido || 0).toFixed(2) : parseFloat(cut.Precio1ERP || 0).toFixed(2);
        const vencFmt = isWeighed ? weighed[0].FechaVencimiento : (cut.DiasVencimiento ? `${cut.DiasVencimiento} d` : '-');

        let itemNumHtml = '-';
        let loteHtml = '<span class="badge" style="background:rgba(255,255,255,0.06); color:var(--text-muted); font-size:0.75rem;">Pendiente</span>';
        let deleteBtnHtml = '';
        let editBtnHtml = '';

        if (isWeighed) {
            itemNumHtml = weighed.map(w => `#${w.NroItem}`).join(', ');
            loteHtml = weighed.map(w => `<span class="badge" style="background:#55433a; color:var(--primary); font-family:monospace; font-size:0.8rem; margin-right:2px;" title="${parseFloat(w.PesoNeto).toFixed(4)} kg">${w.NroLote}</span>`).join(' ');
            if (isOperable) {
                editBtnHtml = weighed.map(w => `<button type="button" class="btn btn-outline btn-edit-cut" style="padding:2px 6px; font-size:0.75rem; color:#3498db;" data-id="${w.ID}" data-lote="${w.NroLote}" data-peso="${w.PesoNeto}" data-corte="${cut.NombreCorte}" title="Corregir peso con auditoría">✏️</button>`).join(' ');
                deleteBtnHtml = weighed.map(w => `<button type="button" class="btn btn-outline btn-delete-cut" style="padding:2px 6px; font-size:0.75rem; color:var(--danger);" data-id="${w.ID}" title="Anular pesaje ${w.NroLote}">&times;</button>`).join(' ');
            }
        }

        const assignBtnHtml = isOperable ? `<button type="button" class="btn btn-sm ${isWeighed ? 'btn-outline' : 'btn-primary'} btn-assign-cut" data-code="${cut.CodProdCorte}" title="Asignar peso" style="padding:3px 8px; font-size:0.78rem; font-weight:700;">⚖️ ${isWeighed ? '+ Peso' : 'Pesar'}</button>` : '';

        if (tbody) {
            const tr = document.createElement('tr');
            tr.className = `cut-table-row ${isSelected ? 'selected' : ''} ${isWeighed ? 'row-weighed' : 'row-pending'}`;
            tr.dataset.code = cut.CodProdCorte;
            tr.style.borderBottom = '1px solid var(--border-color)';
            tr.innerHTML = `
                <td style="padding:0.6rem 0.5rem; font-weight:700;">${itemNumHtml}</td>
                <td style="padding:0.6rem 0.5rem;">${loteHtml}</td>
                <td style="padding:0.6rem 0.5rem;"><div style="font-weight:700; color:var(--text-primary); font-size:0.9rem;">${cut.NombreCorte}</div><small style="color:var(--text-muted); font-size:0.72rem;">${cut.CodProdCorte}</small></td>
                <td style="padding:0.6rem 0.5rem; text-align:right;"><span style="font-weight:700; font-family:monospace; font-size:0.95rem; color:${isWeighed ? 'var(--primary)' : 'var(--text-muted)'};">${pesoFmt} kg</span></td>
                <td style="padding:0.6rem 0.5rem; text-align:right;"><span style="font-weight:600; color:${isWeighed ? 'var(--success)' : 'var(--text-muted)'};">${rendFmt}%</span></td>
                <td style="padding:0.6rem 0.5rem; text-align:right; color:var(--text-muted);">$${costBaseFmt}</td>
                <td style="padding:0.6rem 0.5rem; text-align:right; color:${isWeighed ? 'var(--danger)' : 'var(--text-muted)'}; font-weight:500;">${isWeighed ? `+$${costMermaFmt}` : '-'}</td>
                <td style="padding:0.6rem 0.5rem; text-align:right; font-weight:700; color:${isWeighed ? 'var(--primary)' : 'var(--text-muted)'};">${isWeighed ? `$${costRealFmt}` : '-'}</td>
                <td style="padding:0.6rem 0.5rem; text-align:right; font-weight:700; color:${isWeighed ? 'var(--success)' : 'var(--text-muted)'};">$${pSugFmt}</td>
                <td style="padding:0.6rem 0.5rem; text-align:center; font-size:0.75rem; color:var(--text-muted);">${vencFmt}</td>
                <td style="padding:0.6rem 0.5rem; text-align:center;"><div style="display:flex; gap:0.25rem; justify-content:center; align-items:center;">${assignBtnHtml} ${editBtnHtml} ${deleteBtnHtml}</div></td>
            `;
            tbody.appendChild(tr);
        }

        if (cardsContainer) {
            const cardEl = document.createElement('div');
            cardEl.className = `cut-item-card ${isSelected ? 'selected' : ''} ${isWeighed ? 'card-weighed' : 'card-pending'}`;
            cardEl.dataset.code = cut.CodProdCorte;
            cardEl.innerHTML = `
                <div class="cut-card-header"><div style="display:flex; align-items:center; gap:0.5rem;"><span class="cut-card-badge">${itemNumHtml}</span><strong class="cut-card-title">${cut.NombreCorte}</strong></div><div>${loteHtml}</div></div>
                <div class="cut-card-grid">
                    <div class="cut-card-stat"><span class="cut-card-stat-lbl">PESO NETO</span><span class="cut-card-stat-val ${isWeighed ? 'val-primary' : ''}">${pesoFmt} kg</span></div>
                    <div class="cut-card-stat"><span class="cut-card-stat-lbl">RENDIMIENTO</span><span class="cut-card-stat-val ${isWeighed ? 'val-success' : ''}">${rendFmt}%</span></div>
                    <div class="cut-card-stat"><span class="cut-card-stat-lbl">COSTO REAL</span><span class="cut-card-stat-val ${isWeighed ? 'val-highlight' : ''}">${isWeighed ? `$${costRealFmt}` : `$${costBaseFmt}`}</span></div>
                    <div class="cut-card-stat"><span class="cut-card-stat-lbl">P. SUGERIDO</span><span class="cut-card-stat-val ${isWeighed ? 'val-accent' : ''}">$${pSugFmt}</span></div>
                </div>
                <div class="cut-card-footer">
                    <span class="cut-card-venc"><svg class="icon" viewBox="0 0 24 24" style="width:13px; height:13px; margin-right:4px; vertical-align:-1px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>Vence: ${vencFmt}</span>
                    <div style="display:flex; gap:0.35rem; align-items:center;">${assignBtnHtml} ${editBtnHtml} ${deleteBtnHtml}</div>
                </div>
            `;
            cardsContainer.appendChild(cardEl);
        }
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
    formData.append('csrf_token', getCsrfToken());
    formData.append('nro_orden', currentOrder.NroOrdenDesposte);

    try {
        const res = await fetch('/api/desposte/cerrar', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            sessionStorage.removeItem('st_active_deboning_order');
            window.history.replaceState({}, '', getBasePath());
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
        showAlert('Base de datos o servidor fuera de línea por favor contacte a soporte técnico', 'danger');
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
                grid.innerHTML = `<div class="card" style="grid-column: 1 / -1; text-align:center; padding:3rem 1.5rem;"><h3 style="font-size:1.1rem; color:var(--text-primary); margin-bottom:0.5rem;">No hay órdenes activas</h3><p style="color:var(--text-secondary); max-width:450px; margin:0 auto 1.5rem auto; font-size:0.9rem;">Inicie una nueva orden asignando una mesa y una canal.</p><a href="/desposte/nueva" class="btn btn-primary">Iniciar Primera Orden</a></div>`;
                return;
            }

            grid.innerHTML = '';
            json.orders.forEach(ord => {
                const card = document.createElement('div');
                card.className = 'card';
                card.style.cssText = 'border-left: 4px solid var(--primary); display:flex; flex-direction:column; justify-content:space-between;';
                const mesaBadge = (ord.LineaMesa && ord.LineaMesa.trim() !== '') ? `<span class="badge" style="background:#55433a; color:var(--primary); font-weight:700; font-size:0.8rem;">${ord.LineaMesa}</span>` : '';
                card.innerHTML = `
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem;">
                            <div>${mesaBadge}<h3 style="font-size:1.05rem; font-weight:700; margin-top:0.35rem; color:var(--text-primary);">${ord.NroOrdenDesposte}</h3></div>
                            <span class="badge badge-success" style="font-size:0.75rem;">EN PROCESO</span>
                        </div>
                        <div style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:1rem; line-height:1.5;">
                            <div><strong>Especie:</strong> ${ord.NombreCanal} (${ord.CodProdCanal})</div>
                            <div><strong>Recepción:</strong> ${ord.NroRecepcion}</div>
                            <div><strong>Inicio:</strong> ${ord.FechaHoraInicio.substring(0, 16)}</div>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; background:rgba(0,0,0,0.15); padding:0.75rem; border-radius:8px; margin-bottom:1rem;">
                            <div><div style="font-size:0.75rem; color:var(--text-muted);">Canal Entrada</div><div style="font-size:1.1rem; font-weight:700; color:var(--text-primary);">${parseFloat(ord.PesoCanalEntrada).toFixed(4)} kg</div></div>
                            <div><div style="font-size:0.75rem; color:var(--text-muted);">Cortes Pesados</div><div style="font-size:1.1rem; font-weight:700; color:var(--primary);">${parseFloat(ord.PesoTotalCortes).toFixed(4)} kg</div></div>
                            <div><div style="font-size:0.75rem; color:var(--text-muted);">Rendimiento</div><div style="font-size:1rem; font-weight:600; color:var(--success);">${parseFloat(ord.RendimientoPct).toFixed(2)}%</div></div>
                            <div><div style="font-size:0.75rem; color:var(--text-muted);">Merma Actual</div><div style="font-size:1rem; font-weight:600; color:var(--danger);">${parseFloat(ord.MermaPct).toFixed(2)}%</div></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-operate-order" style="width:100%; justify-content:center;" data-order="${ord.NroOrdenDesposte}">
                        <svg class="icon" viewBox="0 0 24 24" style="width:16px; height:16px; margin-right:6px;"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                        Operar Mesa / Pesar
                    </button>`;
                grid.appendChild(card);
            });
        }
    } catch (e) {
        console.error('Error refrescando órdenes:', e);
    }
}

// Inicialización de Event Listeners (sin inline handlers, 100% CSP compliant)
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const initialOrderEl = document.getElementById('initialOrderNumber');
    const orderToLoad = urlParams.get('order') || (initialOrderEl ? initialOrderEl.value : null) || sessionStorage.getItem('st_active_deboning_order');
    if (orderToLoad && orderToLoad.trim() !== '') {
        loadOrderInStation(orderToLoad.trim());
    }

    document.addEventListener('click', (e) => {
        const target = e.target.closest('[data-action]');
        if (target) {
            const action = target.dataset.action;
            if (action === 'view-board') switchView('board');
            else if (action === 'prompt-close-order') promptCloseOrder();
            else if (action === 'close-modal-close-order') closeCloseOrderModal();
            else if (action === 'close-modal-weigh') closeWeighModal();
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

        const btnAssign = e.target.closest('.btn-assign-cut');
        if (btnAssign && btnAssign.dataset.code) {
            const cut = currentCutsCatalog.find(c => String(c.CodProdCorte).trim() === String(btnAssign.dataset.code).trim());
            if (cut) openWeighModal(cut);
            return;
        }

        const cutRow = e.target.closest('.cut-table-row');
        if (cutRow && cutRow.dataset.code && !e.target.closest('.btn-delete-cut') && !e.target.closest('.btn-assign-cut')) {
            const cut = currentCutsCatalog.find(c => String(c.CodProdCorte).trim() === String(cutRow.dataset.code).trim());
            if (cut) openWeighModal(cut);
            return;
        }

        const cutCard = e.target.closest('.cut-item-card');
        if (cutCard && cutCard.dataset.code && !e.target.closest('.btn-delete-cut') && !e.target.closest('.btn-assign-cut')) {
            const cut = currentCutsCatalog.find(c => String(c.CodProdCorte).trim() === String(cutCard.dataset.code).trim());
            if (cut) openWeighModal(cut);
            return;
        }
    });

    const btnRegCut = document.getElementById('btnRegisterCut');
    if (btnRegCut) btnRegCut.addEventListener('click', registerCutWeight);

    const weighIn = document.getElementById('weighInput');
    if (weighIn) {
        weighIn.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                registerCutWeight();
            }
        });
        weighIn.addEventListener('input', (e) => {
            let val = e.target.value.replace(/,/g, '.').replace(/[^0-9.]/g, '');
            const parts = val.split('.');
            if (parts.length > 2) val = parts[0] + '.' + parts.slice(1).join('');
            if (parts[1] && parts[1].length > 4) val = parts[0] + '.' + parts[1].slice(0, 4);
            e.target.value = val;
        });
    }

    const btnConfClose = document.getElementById('btnConfirmCloseOrder');
    if (btnConfClose) btnConfClose.addEventListener('click', executeCloseOrder);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeWeighModal();
            closeCloseOrderModal();
        }
    });

    window.addEventListener('beforeunload', (e) => {
        const modal = document.getElementById('modalWeighCalculator');
        const weighInput = document.getElementById('weighInput');
        if (currentOrder && modal && modal.style.display !== 'none') {
            const hasTypedWeight = weighInput && parseFloat(weighInput.value || '0') > 0;
            if (hasTypedWeight) {
                e.preventDefault();
                e.returnValue = '';
            }
        }
    });
});
