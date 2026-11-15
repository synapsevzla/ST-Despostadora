/**
 * ST-Despostadora - Gestor de Selectores Modales Táctiles para Nueva Orden de Desposte
 * Vanilla ES6+, 100% compliant con Content-Security-Policy.
 */

window.DespostePicker = (() => {
    'use strict';

    const byId = id => document.getElementById(id);

    // Triggers
    const triggerEspecie = byId('triggerEspecie');
    const placeholderEspecie = byId('placeholderEspecie');
    const selectedEspecieWrap = byId('selectedEspecieWrap');
    const badgeEspecieCod = byId('badgeEspecieCod');
    const nameEspecie = byId('nameEspecie');
    const btnCambiarEspecie = byId('btnCambiarEspecie');

    const triggerRecepcion = byId('triggerRecepcion');
    const placeholderRecepcion = byId('placeholderRecepcion');
    const textPlaceholderRecepcion = byId('textPlaceholderRecepcion');
    const selectedRecepcionWrap = byId('selectedRecepcionWrap');
    const badgeRecepcionNro = byId('badgeRecepcionNro');
    const badgeRecepcionMetodo = byId('badgeRecepcionMetodo');
    const badgeRecepcionSaldo = byId('badgeRecepcionSaldo');
    const infoRecepcionFecha = byId('infoRecepcionFecha');
    const btnCambiarRecepcion = byId('btnCambiarRecepcion');

    const triggerPieza = byId('triggerPieza');
    const placeholderPieza = byId('placeholderPieza');
    const textPlaceholderPieza = byId('textPlaceholderPieza');
    const selectedPiezaWrap = byId('selectedPiezaWrap');
    const badgePiezaNro = byId('badgePiezaNro');
    const namePiezaPeso = byId('namePiezaPeso');
    const btnCambiarPieza = byId('btnCambiarPieza');

    // Inputs ocultos
    const hiddenEspecie = byId('selectCanalEspecie');
    const hiddenRecepcion = byId('selectRecepcion');
    const hiddenPieza = byId('selectPiezaIndividual');
    const grpPieza = byId('grpPiezaIndividual');
    const helpRecepcion = byId('recAvailableHelp');

    // Modales
    const modalEspecie = byId('modalSelectorEspecie');
    const searchEspecie = byId('modalEspecieSearchInput');
    const listaEspecie = byId('modalEspecieLista');
    const vacioEspecie = byId('modalEspecieVacio');

    const modalRecepcion = byId('modalSelectorRecepcion');
    const searchRecepcion = byId('modalRecepcionSearchInput');
    const listaRecepcion = byId('modalRecepcionLista');
    const spinnerRecepcion = byId('modalRecepcionSpinner');
    const vacioRecepcion = byId('modalRecepcionVacio');
    const infoBarRecepcion = byId('modalRecepcionInfoBar');

    const modalPieza = byId('modalSelectorPieza');
    const searchPieza = byId('modalPiezaSearchInput');
    const listaPieza = byId('modalPiezaLista');
    const spinnerPieza = byId('modalPiezaSpinner');
    const vacioPieza = byId('modalPiezaVacio');
    const infoBarPieza = byId('modalPiezaInfoBar');

    let cacheRecepciones = [];
    let cachePiezas = [];
    let currentSelectedRecepcion = null;
    let callbacks = {
        onEspecieSelected: null,
        onRecepcionSelected: null,
        onPiezaSelected: null,
        playBeep: null
    };

    // Actualizadores visuales de Triggers
    function setEspecieVisual(codprod, nombre) {
        if (codprod) {
            if (hiddenEspecie) hiddenEspecie.value = codprod;
            if (badgeEspecieCod) badgeEspecieCod.textContent = `[${codprod}]`;
            if (nameEspecie) nameEspecie.textContent = nombre || codprod;
            if (placeholderEspecie) placeholderEspecie.style.display = 'none';
            if (selectedEspecieWrap) selectedEspecieWrap.style.display = 'flex';
        } else {
            if (hiddenEspecie) hiddenEspecie.value = '';
            if (placeholderEspecie) placeholderEspecie.style.display = 'flex';
            if (selectedEspecieWrap) selectedEspecieWrap.style.display = 'none';
        }
    }

    function setRecepcionVisual(rec) {
        if (rec) {
            currentSelectedRecepcion = rec;
            if (hiddenRecepcion) hiddenRecepcion.value = rec.NroRecepcion;
            if (badgeRecepcionNro) badgeRecepcionNro.textContent = rec.NroRecepcion;
            if (badgeRecepcionMetodo) {
                badgeRecepcionMetodo.textContent = rec.MetodoEntrada;
                if (rec.MetodoEntrada === 'PESO_INDIVIDUAL') {
                    badgeRecepcionMetodo.className = 'badge badge-warning';
                } else if (rec.MetodoEntrada === 'PESO_CANTIDAD') {
                    badgeRecepcionMetodo.className = 'badge badge-info';
                } else {
                    badgeRecepcionMetodo.className = 'badge badge-secondary';
                }
            }
            const dispKg = parseFloat(rec.PesoDisponible || '0');
            if (badgeRecepcionSaldo) badgeRecepcionSaldo.textContent = `Saldo: ${dispKg.toFixed(4)} kg`;
            if (infoRecepcionFecha) infoRecepcionFecha.textContent = `Ingreso: ${rec.FechaHora ? rec.FechaHora.substring(0, 16) : ''}`;
            if (placeholderRecepcion) placeholderRecepcion.style.display = 'none';
            if (selectedRecepcionWrap) selectedRecepcionWrap.style.display = 'flex';
            if (triggerRecepcion) triggerRecepcion.classList.remove('disabled');
        } else {
            currentSelectedRecepcion = null;
            if (hiddenRecepcion) hiddenRecepcion.value = '';
            if (placeholderRecepcion) placeholderRecepcion.style.display = 'flex';
            if (selectedRecepcionWrap) selectedRecepcionWrap.style.display = 'none';
        }
    }

    function setPiezaVisual(pieza) {
        if (pieza) {
            if (hiddenPieza) hiddenPieza.value = String(pieza.NroEspecie);
            if (badgePiezaNro) badgePiezaNro.textContent = `Res #${pieza.NroEspecie}`;
            const pKg = parseFloat(pieza.PesoNeto || '0');
            if (namePiezaPeso) namePiezaPeso.textContent = `Peso: ${pKg.toFixed(4)} kg`;
            if (placeholderPieza) placeholderPieza.style.display = 'none';
            if (selectedPiezaWrap) selectedPiezaWrap.style.display = 'flex';
        } else {
            if (hiddenPieza) hiddenPieza.value = '';
            if (placeholderPieza) placeholderPieza.style.display = 'flex';
            if (selectedPiezaWrap) selectedPiezaWrap.style.display = 'none';
        }
    }

    // Modal Especie
    function abrirModalEspecie() {
        if (!modalEspecie) return;
        if (searchEspecie) searchEspecie.value = '';
        filtrarEspecies('');
        modalEspecie.style.display = 'flex';
        modalEspecie.classList.add('active');
        setTimeout(() => searchEspecie?.focus(), 50);
    }

    function cerrarModalEspecie() {
        if (modalEspecie) {
            modalEspecie.style.display = 'none';
            modalEspecie.classList.remove('active');
        }
    }

    function filtrarEspecies(q) {
        const query = (q || '').toLowerCase().trim();
        const items = listaEspecie?.querySelectorAll('.especie-modal-item') || [];
        let visibles = 0;
        items.forEach(item => {
            const cod = (item.dataset.codprod || '').toLowerCase();
            const nom = (item.dataset.nombre || '').toLowerCase();
            const match = !query || cod.includes(query) || nom.includes(query);
            item.style.display = match ? 'flex' : 'none';
            if (match) visibles++;
        });
        if (vacioEspecie) vacioEspecie.style.display = visibles === 0 ? 'block' : 'none';
    }

    // Modal Recepciones
    function abrirModalRecepcion() {
        if (!hiddenEspecie?.value) {
            abrirModalEspecie();
            return;
        }
        if (cacheRecepciones.length === 0) return;
        if (!modalRecepcion) return;
        if (searchRecepcion) searchRecepcion.value = '';
        renderRecepciones(cacheRecepciones);
        modalRecepcion.style.display = 'flex';
        modalRecepcion.classList.add('active');
        setTimeout(() => searchRecepcion?.focus(), 50);
    }

    function cerrarModalRecepcion() {
        if (modalRecepcion) {
            modalRecepcion.style.display = 'none';
            modalRecepcion.classList.remove('active');
        }
    }

    function renderRecepciones(items) {
        if (!listaRecepcion) return;
        listaRecepcion.innerHTML = '';
        if (infoBarRecepcion) infoBarRecepcion.textContent = `${items.length} recepción(es) con saldo disponible`;
        if (items.length === 0) {
            if (vacioRecepcion) vacioRecepcion.style.display = 'block';
            return;
        }
        if (vacioRecepcion) vacioRecepcion.style.display = 'none';

        items.forEach(r => {
            const card = document.createElement('div');
            card.className = 'recepcion-item-card';
            card.dataset.nrorec = r.NroRecepcion;
            card.tabIndex = 0;
            card.setAttribute('role', 'button');

            let badgeClass = 'badge badge-secondary';
            if (r.MetodoEntrada === 'PESO_INDIVIDUAL') badgeClass = 'badge badge-warning';
            else if (r.MetodoEntrada === 'PESO_CANTIDAD') badgeClass = 'badge badge-info';

            const saldoKg = parseFloat(r.PesoDisponible || '0').toFixed(4);
            const totalKg = parseFloat(r.PesoTotalNeto || '0').toFixed(4);
            const fechaTxt = r.FechaHora ? r.FechaHora.substring(0, 16) : '';

            card.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                    <span style="font-family:monospace; font-weight:700; color:var(--text-primary); font-size:1rem;">${r.NroRecepcion}</span>
                    <span class="${badgeClass}" style="font-size:0.75rem;">${r.MetodoEntrada}</span>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin:0.35rem 0;">
                    <div>
                        <span style="font-size:0.75rem; color:var(--text-muted); display:block;">Saldo Disponible:</span>
                        <strong style="color:var(--success); font-size:1.25rem;">${saldoKg} kg</strong>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:0.75rem; color:var(--text-muted); display:block;">Total Inicial:</span>
                        <span style="font-size:0.9rem; color:var(--text-secondary);">${totalKg} kg</span>
                    </div>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.78rem; color:var(--text-muted); border-top:1px dashed var(--border-color); padding-top:0.35rem;">
                    <span>📅 Ingreso: ${fechaTxt}</span>
                    <span style="color:var(--primary); font-weight:600;">Seleccionar Lote →</span>
                </div>
            `;
            listaRecepcion.appendChild(card);
        });
    }

    // Modal Piezas Individuales
    function abrirModalPieza() {
        if (!currentSelectedRecepcion || currentSelectedRecepcion.MetodoEntrada !== 'PESO_INDIVIDUAL') return;
        if (cachePiezas.length === 0) return;
        if (!modalPieza) return;
        if (searchPieza) searchPieza.value = '';
        renderPiezas(cachePiezas);
        modalPieza.style.display = 'flex';
        modalPieza.classList.add('active');
        setTimeout(() => searchPieza?.focus(), 50);
    }

    function cerrarModalPieza() {
        if (modalPieza) {
            modalPieza.style.display = 'none';
            modalPieza.classList.remove('active');
        }
    }

    function renderPiezas(items) {
        if (!listaPieza) return;
        listaPieza.innerHTML = '';
        if (infoBarPieza) infoBarPieza.textContent = `${items.length} res(es) disponible(s) para desposte`;
        if (items.length === 0) {
            if (vacioPieza) vacioPieza.style.display = 'block';
            return;
        }
        if (vacioPieza) vacioPieza.style.display = 'none';

        items.forEach(p => {
            const card = document.createElement('div');
            card.className = 'pieza-item-card';
            card.dataset.nro = String(p.NroEspecie);
            card.dataset.peso = String(p.PesoNeto);
            card.tabIndex = 0;
            card.setAttribute('role', 'button');

            const pesoKg = parseFloat(p.PesoNeto || '0').toFixed(4);
            card.innerHTML = `
                <div style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">
                    Res #${p.NroEspecie}
                </div>
                <div style="font-size:1.15rem; font-weight:800; color:var(--success); margin:0.15rem 0;">
                    ${pesoKg} kg
                </div>
                <span class="badge badge-success" style="font-size:0.7rem; font-weight:700;">DISPONIBLE</span>
                <span style="font-size:0.75rem; color:var(--primary); margin-top:0.25rem; font-weight:600;">Seleccionar →</span>
            `;
            listaPieza.appendChild(card);
        });
    }

    // Inicialización de Listeners Internos
    function init(cbs = {}) {
        callbacks = Object.assign(callbacks, cbs);

        // Búsquedas reactivas
        searchEspecie?.addEventListener('input', () => filtrarEspecies(searchEspecie.value));
        searchRecepcion?.addEventListener('input', () => {
            const q = searchRecepcion.value.toLowerCase().trim();
            const filtered = cacheRecepciones.filter(r => !q ||
                r.NroRecepcion.toLowerCase().includes(q) ||
                (r.MetodoEntrada && r.MetodoEntrada.toLowerCase().includes(q)) ||
                (r.FechaHora && r.FechaHora.toLowerCase().includes(q))
            );
            renderRecepciones(filtered);
        });
        searchPieza?.addEventListener('input', () => {
            const q = searchPieza.value.toLowerCase().trim();
            const filtered = cachePiezas.filter(p => !q ||
                String(p.NroEspecie).toLowerCase().includes(q) ||
                String(p.PesoNeto).includes(q)
            );
            renderPiezas(filtered);
        });

        // Selección en listas
        listaEspecie?.addEventListener('click', (e) => {
            const item = e.target.closest('.especie-modal-item');
            if (!item) return;
            const codprod = item.dataset.codprod;
            const nombre = item.dataset.nombre;
            setEspecieVisual(codprod, nombre);
            cerrarModalEspecie();
            if (callbacks.playBeep) callbacks.playBeep('success');
            if (callbacks.onEspecieSelected) callbacks.onEspecieSelected(codprod, nombre);
        });

        listaRecepcion?.addEventListener('click', (e) => {
            const card = e.target.closest('.recepcion-item-card');
            if (!card) return;
            const nrorec = card.dataset.nrorec;
            const rec = cacheRecepciones.find(r => r.NroRecepcion === nrorec);
            if (!rec) return;
            setRecepcionVisual(rec);
            cerrarModalRecepcion();
            if (callbacks.playBeep) callbacks.playBeep('success');
            if (callbacks.onRecepcionSelected) callbacks.onRecepcionSelected(rec);
        });

        listaPieza?.addEventListener('click', (e) => {
            const card = e.target.closest('.pieza-item-card');
            if (!card) return;
            const nro = card.dataset.nro;
            const pieza = cachePiezas.find(p => String(p.NroEspecie) === nro);
            if (!pieza) return;
            setPiezaVisual(pieza);
            cerrarModalPieza();
            if (callbacks.playBeep) callbacks.playBeep('success');
            if (callbacks.onPiezaSelected) callbacks.onPiezaSelected(pieza);
        });

        // Apertura desde triggers
        triggerEspecie?.addEventListener('click', abrirModalEspecie);
        btnCambiarEspecie?.addEventListener('click', (e) => {
            e.stopPropagation();
            abrirModalEspecie();
        });

        triggerRecepcion?.addEventListener('click', () => {
            if (!triggerRecepcion.classList.contains('disabled')) abrirModalRecepcion();
        });
        btnCambiarRecepcion?.addEventListener('click', (e) => {
            e.stopPropagation();
            abrirModalRecepcion();
        });

        triggerPieza?.addEventListener('click', abrirModalPieza);
        btnCambiarPieza?.addEventListener('click', (e) => {
            e.stopPropagation();
            abrirModalPieza();
        });

        // Botones de cierre data-action
        document.querySelectorAll('[data-action="cerrar-modal-especie"]').forEach(b => b.addEventListener('click', cerrarModalEspecie));
        document.querySelectorAll('[data-action="cerrar-modal-recepcion"]').forEach(b => b.addEventListener('click', cerrarModalRecepcion));
        document.querySelectorAll('[data-action="cerrar-modal-pieza"]').forEach(b => b.addEventListener('click', cerrarModalPieza));

        // Cierre al pulsar backdrop
        [modalEspecie, modalRecepcion, modalPieza].forEach(m => {
            if (!m) return;
            m.addEventListener('click', (e) => {
                if (e.target === m) {
                    m.style.display = 'none';
                    m.classList.remove('active');
                }
            });
        });

        // Tecla Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (modalPieza?.classList.contains('active')) cerrarModalPieza();
                else if (modalRecepcion?.classList.contains('active')) cerrarModalRecepcion();
                else if (modalEspecie?.classList.contains('active')) cerrarModalEspecie();
            }
        });
    }

    return {
        init,
        setEspecieVisual,
        setRecepcionVisual,
        setPiezaVisual,
        abrirModalEspecie,
        abrirModalRecepcion,
        abrirModalPieza,
        cerrarModalEspecie,
        cerrarModalRecepcion,
        cerrarModalPieza,
        setRecepciones: (arr) => { cacheRecepciones = arr || []; },
        getRecepciones: () => cacheRecepciones,
        setPiezas: (arr) => { cachePiezas = arr || []; },
        getPiezas: () => cachePiezas,
        getSelectedRecepcion: () => currentSelectedRecepcion,
        resetAll: () => {
            setEspecieVisual('', '');
            setRecepcionVisual(null);
            setPiezaVisual(null);
            cacheRecepciones = [];
            cachePiezas = [];
            if (triggerRecepcion) triggerRecepcion.classList.add('disabled');
            if (textPlaceholderRecepcion) textPlaceholderRecepcion.textContent = '-- Seleccione primero una especie --';
            if (helpRecepcion) helpRecepcion.textContent = '';
            if (grpPieza) grpPieza.style.display = 'none';
        }
    };
})();
