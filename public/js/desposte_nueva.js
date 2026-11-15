/**
 * ST-Despostadora - Lógica de Apertura de Nueva Orden de Desposte
 * Vanilla ES6+, 100% compliant con Content-Security-Policy (sin inline handlers).
 * Controlador de Formulario, Teclado Táctil, Borrador Local y Persistencia.
 */

document.addEventListener('DOMContentLoaded', () => {
    'use strict';

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

    const byId = id => document.getElementById(id);
    let isSubmitting = false;

    // Elementos del formulario y contenedores
    const alertBox = byId('desposteAlert');
    const form = byId('formNuevaOrdenPagina');
    const inputMesa = byId('inputLineaMesa');
    const inputObs = byId('inputObservaciones');
    const inputPeso = byId('inputPesoEntrada');
    const badgePesoDisp = byId('badgePesoDisp');
    const btnSubmit = byId('btnSubmitNuevaOrden');

    // Inputs ocultos y contenedores
    const hiddenEspecie = byId('selectCanalEspecie');
    const hiddenRecepcion = byId('selectRecepcion');
    const hiddenPieza = byId('selectPiezaIndividual');
    const grpPieza = byId('grpPiezaIndividual');
    const helpRecepcion = byId('recAvailableHelp');
    const textPlaceholderRecepcion = byId('textPlaceholderRecepcion');
    const triggerRecepcion = byId('triggerRecepcion');
    const textPlaceholderPieza = byId('textPlaceholderPieza');

    // Resúmenes visuales en la columna derecha
    const resMesa = byId('resumenMesa');
    const resEspecie = byId('resumenEspecie');
    const resRecepcion = byId('resumenRecepcion');

    function showAlert(msg, type = 'danger') {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type}`;
        alertBox.textContent = msg;
        alertBox.style.display = 'block';
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideAlert() {
        if (alertBox) alertBox.style.display = 'none';
    }

    // Persistencia Local del Borrador (Auto-Save para protección contra F5)
    function guardarBorrador() {
        if (isSubmitting) return;
        const draft = {
            linea_mesa: inputMesa?.value || '',
            observaciones: inputObs?.value || '',
            codprod_canal: hiddenEspecie?.value || '',
            nombre_canal: byId('nameEspecie')?.textContent || '',
            nro_recepcion: hiddenRecepcion?.value || '',
            nro_especie: hiddenPieza?.value || '',
            peso_entrada: inputPeso?.value || ''
        };
        const hasData = (draft.codprod_canal !== '') || (draft.nro_recepcion !== '') ||
            (parseFloat(draft.peso_entrada || '0') > 0) || (draft.observaciones.trim() !== '');
        if (hasData) {
            sessionStorage.setItem('st_draft_desposte_nueva', JSON.stringify(draft));
        } else {
            sessionStorage.removeItem('st_draft_desposte_nueva');
        }
    }

    // Inicializar el gestor de selectores modales
    if (window.DespostePicker) {
        window.DespostePicker.init({
            playBeep,
            onEspecieSelected: (codprod, nombre) => {
                if (resEspecie) resEspecie.textContent = nombre ? `${nombre} (${codprod})` : codprod;
                if (resRecepcion) resRecepcion.textContent = '-';
                if (badgePesoDisp) badgePesoDisp.textContent = '';
                if (inputPeso) inputPeso.value = '';
                cargarRecepcionesParaCanal(codprod);
                guardarBorrador();
            },
            onRecepcionSelected: async (rec) => {
                if (resRecepcion) resRecepcion.textContent = rec.NroRecepcion;
                const dispKg = parseFloat(rec.PesoDisponible || '0');
                if (badgePesoDisp) badgePesoDisp.textContent = `Disp: ${dispKg.toFixed(4)} kg`;
                await procesarCambioRecepcion(rec);
                guardarBorrador();
            },
            onPiezaSelected: (pieza) => {
                const peso = parseFloat(pieza.PesoNeto || '0');
                if (inputPeso) inputPeso.value = peso.toFixed(4);
                guardarBorrador();
            }
        });
    }

    // Carga asíncrona de recepciones para la especie seleccionada
    async function cargarRecepcionesParaCanal(canal, targetRecToRestore = null) {
        hideAlert();
        window.DespostePicker.setRecepciones([]);
        if (helpRecepcion) helpRecepcion.textContent = 'Consultando recepciones disponibles...';
        if (textPlaceholderRecepcion) textPlaceholderRecepcion.textContent = '-- Consultando inventario... --';
        if (triggerRecepcion) triggerRecepcion.classList.add('disabled');

        if (!canal) {
            if (textPlaceholderRecepcion) textPlaceholderRecepcion.textContent = '-- Seleccione primero una especie --';
            if (helpRecepcion) helpRecepcion.textContent = '';
            guardarBorrador();
            return;
        }

        try {
            const res = await fetch(`/api/desposte/recepciones-disponibles?canal=${encodeURIComponent(canal)}`);
            const json = await res.json();

            if (json.success && Array.isArray(json.receptions)) {
                window.DespostePicker.setRecepciones(json.receptions);
                const recs = json.receptions;
                if (recs.length > 0) {
                    if (triggerRecepcion) triggerRecepcion.classList.remove('disabled');
                    if (textPlaceholderRecepcion) {
                        textPlaceholderRecepcion.textContent = `-- Toque para seleccionar (${recs.length} disponibles) --`;
                    }
                    if (helpRecepcion) {
                        helpRecepcion.textContent = `${recs.length} recepción(es) con saldo disponible en inventario.`;
                    }

                    if (targetRecToRestore) {
                        const encontrada = recs.find(r => r.NroRecepcion === targetRecToRestore);
                        if (encontrada) {
                            window.DespostePicker.setRecepcionVisual(encontrada);
                            if (resRecepcion) resRecepcion.textContent = encontrada.NroRecepcion;
                            const dispKg = parseFloat(encontrada.PesoDisponible || '0');
                            if (badgePesoDisp) badgePesoDisp.textContent = `Disp: ${dispKg.toFixed(4)} kg`;
                            await procesarCambioRecepcion(encontrada);
                        }
                    }
                } else {
                    if (textPlaceholderRecepcion) textPlaceholderRecepcion.textContent = '-- Sin recepciones disponibles con saldo --';
                    if (helpRecepcion) {
                        helpRecepcion.textContent = 'No hay recepciones con saldo disponible para esta especie.';
                    }
                }
            } else {
                if (textPlaceholderRecepcion) textPlaceholderRecepcion.textContent = '-- Error al cargar recepciones --';
                showAlert('No se pudieron obtener las recepciones disponibles.', 'danger');
            }
        } catch (e) {
            if (textPlaceholderRecepcion) textPlaceholderRecepcion.textContent = '-- Servidor fuera de línea --';
            showAlert('Base de datos o servidor fuera de línea por favor contacte a soporte técnico', 'danger');
        }
        guardarBorrador();
    }

    async function procesarCambioRecepcion(rec) {
        hideAlert();
        window.DespostePicker.setPiezas([]);
        window.DespostePicker.setPiezaVisual(null);
        if (inputPeso) inputPeso.value = '';

        if (!rec) {
            if (grpPieza) grpPieza.style.display = 'none';
            return;
        }

        const metodo = rec.MetodoEntrada;
        const disp = parseFloat(rec.PesoDisponible || '0');

        if (metodo === 'PESO_INDIVIDUAL') {
            if (grpPieza) grpPieza.style.display = 'block';
            if (textPlaceholderPieza) textPlaceholderPieza.textContent = '-- Cargando piezas individuales... --';

            try {
                const res = await fetch(`/api/recepcion/detalle?nrorec=${encodeURIComponent(rec.NroRecepcion)}`);
                const json = await res.json();
                if (json.success && Array.isArray(json.details)) {
                    const disponibles = json.details.filter(d => d.EstadoDesposte === 'DISPONIBLE');
                    window.DespostePicker.setPiezas(disponibles);
                    if (disponibles.length > 0) {
                        if (textPlaceholderPieza) {
                            textPlaceholderPieza.textContent = `-- Toque para seleccionar res (${disponibles.length} disponibles) --`;
                        }
                    } else {
                        if (textPlaceholderPieza) {
                            textPlaceholderPieza.textContent = '-- Todas las piezas de esta recepción ya fueron despostadas --';
                        }
                    }
                }
            } catch (e) {
                if (textPlaceholderPieza) textPlaceholderPieza.textContent = '-- Error al cargar piezas --';
            }
        } else {
            if (grpPieza) grpPieza.style.display = 'none';
            if (inputPeso) inputPeso.value = disp.toFixed(4);
        }
    }

    // Restauración del borrador o preselección inicial
    async function restaurarBorrador() {
        const raw = sessionStorage.getItem('st_draft_desposte_nueva');
        let draft = null;
        if (raw) {
            try { draft = JSON.parse(raw); } catch (e) { draft = null; }
        }

        const preselectedSpecies = hiddenEspecie?.value || '';
        const targetSpecies = (draft && draft.codprod_canal) ? draft.codprod_canal : preselectedSpecies;

        if (draft) {
            if (draft.linea_mesa && inputMesa) {
                inputMesa.value = draft.linea_mesa;
                if (resMesa) resMesa.textContent = draft.linea_mesa;
                document.querySelectorAll('.mesa-select-btn').forEach(b => {
                    if (b.dataset.mesa === draft.linea_mesa) b.classList.add('active');
                    else b.classList.remove('active');
                });
            }
            if (draft.observaciones && inputObs) inputObs.value = draft.observaciones;
        }

        if (targetSpecies) {
            let nombreEspecieEncontrada = draft?.nombre_canal || '';
            const listaEspecie = byId('modalEspecieLista');
            if (!nombreEspecieEncontrada && listaEspecie) {
                const item = listaEspecie.querySelector(`.especie-modal-item[data-codprod="${targetSpecies}"]`);
                if (item) nombreEspecieEncontrada = item.dataset.nombre || '';
            }
            window.DespostePicker.setEspecieVisual(targetSpecies, nombreEspecieEncontrada);
            if (resEspecie) resEspecie.textContent = nombreEspecieEncontrada ? `${nombreEspecieEncontrada} (${targetSpecies})` : targetSpecies;

            const targetRec = (draft && draft.nro_recepcion) ? draft.nro_recepcion : null;
            await cargarRecepcionesParaCanal(targetSpecies, targetRec);

            if (draft && draft.nro_recepcion) {
                const recActual = window.DespostePicker.getSelectedRecepcion();
                if (recActual && recActual.MetodoEntrada === 'PESO_INDIVIDUAL' && draft.nro_especie) {
                    const piezas = window.DespostePicker.getPiezas();
                    const p = piezas.find(pz => String(pz.NroEspecie) === String(draft.nro_especie));
                    if (p) window.DespostePicker.setPiezaVisual(p);
                }
                if (draft.peso_entrada && inputPeso) {
                    inputPeso.value = draft.peso_entrada;
                }
            }
            const notice = byId('desposteDraftNotice');
            if (notice && draft) notice.style.display = 'flex';
        }
    }

    // Selección rápida de mesa
    document.querySelectorAll('.mesa-select-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.mesa-select-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const mesa = btn.dataset.mesa || '';
            if (inputMesa) inputMesa.value = mesa;
            if (resMesa) resMesa.textContent = mesa;
            guardarBorrador();
        });
    });

    if (inputMesa) {
        inputMesa.addEventListener('input', () => {
            const val = inputMesa.value.trim();
            if (resMesa) resMesa.textContent = val !== '' ? val : '-';
            document.querySelectorAll('.mesa-select-btn').forEach(b => {
                if (b.dataset.mesa === val) b.classList.add('active');
                else b.classList.remove('active');
            });
            guardarBorrador();
        });
    }

    if (inputObs) inputObs.addEventListener('input', guardarBorrador);

    // Teclado táctil en pantalla y sanitización de peso
    if (inputPeso) {
        inputPeso.addEventListener('input', () => {
            let v = inputPeso.value.replace(',', '.').replace(/[^0-9.]/g, '');
            const parts = v.split('.');
            if (parts.length > 2) v = parts[0] + '.' + parts.slice(1).join('');
            if (parts[1] && parts[1].length > 4) v = parts[0] + '.' + parts[1].slice(0, 4);
            inputPeso.value = v;
            guardarBorrador();
        });
    }

    document.querySelectorAll('.keypad-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const key = btn.dataset.key;
            if (!key || !inputPeso) return;

            let cur = (inputPeso.value || '').trim();
            if (key === 'C') {
                inputPeso.value = '';
            } else if (key === '.') {
                if (!cur.includes('.')) {
                    inputPeso.value = (cur === '') ? '0.' : cur + '.';
                }
            } else {
                if (cur.includes('.')) {
                    const decimals = cur.split('.')[1] || '';
                    if (decimals.length >= 4) return;
                }
                inputPeso.value = (cur === '0') ? key : cur + key;
            }
            inputPeso.focus();
            guardarBorrador();
        });
    });

    // Envío del Formulario
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideAlert();

            if (!hiddenEspecie?.value) {
                showAlert('Por favor seleccione una especie cárnica de origen.', 'danger');
                playBeep('error');
                window.DespostePicker.abrirModalEspecie();
                return;
            }

            if (!hiddenRecepcion?.value) {
                showAlert('Por favor seleccione una recepción con saldo disponible.', 'danger');
                playBeep('error');
                window.DespostePicker.abrirModalRecepcion();
                return;
            }

            if (grpPieza && grpPieza.style.display !== 'none' && !hiddenPieza?.value) {
                showAlert('Esta recepción requiere seleccionar la res individual a despostar.', 'danger');
                playBeep('error');
                window.DespostePicker.abrirModalPieza();
                return;
            }

            const pesoVal = parseFloat(inputPeso?.value || '0');
            if (pesoVal <= 0 || isNaN(pesoVal)) {
                showAlert('El peso de la canal para desposte debe ser mayor a 0 kg.', 'danger');
                playBeep('error');
                inputPeso?.focus();
                return;
            }

            btnSubmit.disabled = true;
            const originalHtml = btnSubmit.innerHTML;
            btnSubmit.innerHTML = 'Abriendo orden...';

            const formData = new FormData(form);

            try {
                const res = await fetch('/api/desposte/abrir', {
                    method: 'POST',
                    body: formData
                });
                const json = await res.json();

                if (json.success) {
                    isSubmitting = true;
                    sessionStorage.removeItem('st_draft_desposte_nueva');
                    playBeep('success');
                    showAlert(json.message || 'Orden abierta exitosamente. Redirigiendo...', 'success');
                    setTimeout(() => {
                        window.location.href = (window.APP_BASE_URL || '') + `/deboning?order=${encodeURIComponent(json.nro_orden)}`;
                    }, 400);
                } else {
                    playBeep('error');
                    showAlert(json.error || 'Ocurrió un error al abrir la orden de desposte.', 'danger');
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalHtml;
                }
            } catch (e) {
                playBeep('error');
                showAlert('Base de datos o servidor fuera de línea por favor contacte a soporte técnico', 'danger');
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = originalHtml;
            }
        });
    }

    // Descartar borrador manualmente
    byId('btnDescartarBorrador')?.addEventListener('click', async () => {
        const conf = await AppModal.confirm('¿Desea descartar el borrador y restablecer los campos?', {
            title: 'Descartar Borrador',
            danger: true,
            confirmText: 'Sí, descartar'
        });
        if (conf) {
            sessionStorage.removeItem('st_draft_desposte_nueva');
            if (form) form.reset();
            const notice = byId('desposteDraftNotice');
            if (notice) notice.style.display = 'none';
            if (resMesa) resMesa.textContent = inputMesa ? inputMesa.value : '-';
            if (resEspecie) resEspecie.textContent = '-';
            if (resRecepcion) resRecepcion.textContent = '-';
            if (badgePesoDisp) badgePesoDisp.textContent = '';
            if (inputPeso) inputPeso.value = '';
            window.DespostePicker.resetAll();
            hideAlert();
        }
    });

    // Enlaces de regreso con confirmación
    document.querySelectorAll('a[href="/deboning"], a[href$="/deboning"]').forEach(link => {
        link.addEventListener('click', async (e) => {
            const hasData = (inputPeso && parseFloat(inputPeso.value || '0') > 0) ||
                (hiddenRecepcion && hiddenRecepcion.value !== '');
            if (hasData) {
                e.preventDefault();
                const conf = await AppModal.confirm('Tiene datos seleccionados para esta nueva orden. ¿Desea salir y descartarlos?', {
                    title: 'Salir de Nueva Orden',
                    danger: true,
                    confirmText: 'Salir y descartar'
                });
                if (conf) {
                    isSubmitting = true;
                    sessionStorage.removeItem('st_draft_desposte_nueva');
                    window.location.href = (window.APP_BASE_URL || '') + '/deboning';
                }
            } else {
                isSubmitting = true;
                sessionStorage.removeItem('st_draft_desposte_nueva');
            }
        });
    });

    // Restaurar borrador o precarga inicial
    restaurarBorrador();
});
