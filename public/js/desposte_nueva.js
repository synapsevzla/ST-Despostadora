/**
 * ST-Despostadora - Lógica de Apertura de Nueva Orden de Desposte (Página Completa)
 * Vanilla ES6+, 100% compliant con Content-Security-Policy (sin inline handlers).
 */

document.addEventListener('DOMContentLoaded', () => {
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

    let isSubmitting = false;
    const alertBox = document.getElementById('desposteAlert');
    const form = document.getElementById('formNuevaOrdenPagina');
    const inputMesa = document.getElementById('inputLineaMesa');
    const inputObs = document.getElementById('inputObservaciones');
    const selectEspecie = document.getElementById('selectCanalEspecie');
    const selectRecepcion = document.getElementById('selectRecepcion');
    const helpRecepcion = document.getElementById('recAvailableHelp');
    const grpPieza = document.getElementById('grpPiezaIndividual');
    const selectPieza = document.getElementById('selectPiezaIndividual');
    const inputPeso = document.getElementById('inputPesoEntrada');
    const badgePesoDisp = document.getElementById('badgePesoDisp');
    const btnSubmit = document.getElementById('btnSubmitNuevaOrden');

    // Resúmenes visuales
    const resMesa = document.getElementById('resumenMesa');
    const resEspecie = document.getElementById('resumenEspecie');
    const resRecepcion = document.getElementById('resumenRecepcion');

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

    // Persistencia Local del Borrador (Auto-Save para protección contra F5 o recargas accidentales)
    function guardarBorrador() {
        if (isSubmitting) return;
        const draft = {
            linea_mesa: inputMesa?.value || '',
            observaciones: inputObs?.value || '',
            codprod_canal: selectEspecie?.value || '',
            nro_recepcion: selectRecepcion?.value || '',
            nro_especie: selectPieza?.value || '',
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

    async function restaurarBorrador() {
        const raw = sessionStorage.getItem('st_draft_desposte_nueva');
        let draft = null;
        if (raw) {
            try { draft = JSON.parse(raw); } catch (e) { draft = null; }
        }

        const preselectedSpecies = selectEspecie?.value || '';
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

        if (targetSpecies && selectEspecie) {
            selectEspecie.value = targetSpecies;
            await cargarRecepcionesParaCanal(targetSpecies);
            if (draft && draft.nro_recepcion && selectRecepcion) {
                selectRecepcion.value = draft.nro_recepcion;
                await manejarCambioRecepcion();
                if (draft.nro_especie && selectPieza) {
                    selectPieza.value = draft.nro_especie;
                }
                if (draft.peso_entrada && inputPeso) {
                    inputPeso.value = draft.peso_entrada;
                }
            }
            const notice = document.getElementById('desposteDraftNotice');
            if (notice && draft) notice.style.display = 'flex';
        }
    }

    // 1. Manejo de botones de selección rápida de mesa
    document.querySelectorAll('.mesa-select-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.mesa-select-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const mesa = btn.dataset.mesa || '';
            if (inputMesa) {
                inputMesa.value = mesa;
            }
            if (resMesa) {
                resMesa.textContent = mesa;
            }
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

    if (inputObs) {
        inputObs.addEventListener('input', guardarBorrador);
    }

    // Función desacoplada para cargar recepciones de la especie seleccionada
    async function cargarRecepcionesParaCanal(canal) {
        hideAlert();
        const opt = selectEspecie?.options[selectEspecie?.selectedIndex];
        if (resEspecie) resEspecie.textContent = canal ? (opt ? opt.text : canal) : '-';

        selectRecepcion.innerHTML = '<option value="">-- Cargando recepciones disponibles... --</option>';
        grpPieza.style.display = 'none';
        selectPieza.innerHTML = '<option value="">-- Seleccione la res individual --</option>';
        helpRecepcion.textContent = '';
        badgePesoDisp.textContent = '';
        inputPeso.value = '';
        if (resRecepcion) resRecepcion.textContent = '-';

        if (!canal) {
            selectRecepcion.innerHTML = '<option value="">-- Seleccione primero una especie --</option>';
            guardarBorrador();
            return;
        }

        try {
            const res = await fetch(`/api/desposte/recepciones-disponibles?canal=${encodeURIComponent(canal)}`);
            const json = await res.json();
            if (json.success && json.receptions && json.receptions.length > 0) {
                selectRecepcion.innerHTML = '<option value="">-- Seleccione una recepción --</option>';
                json.receptions.forEach(r => {
                    const op = document.createElement('option');
                    op.value = r.NroRecepcion;
                    op.dataset.metodo = r.MetodoEntrada;
                    op.dataset.disp = r.PesoDisponible;
                    op.textContent = `${r.NroRecepcion} (${r.MetodoEntrada}) - Saldo: ${parseFloat(r.PesoDisponible).toFixed(4)} kg - ${r.FechaHora.substring(0, 16)}`;
                    selectRecepcion.appendChild(op);
                });
                helpRecepcion.textContent = `${json.receptions.length} recepción(es) disponible(s) con inventario.`;
            } else {
                selectRecepcion.innerHTML = '<option value="">-- Sin recepciones disponibles con saldo --</option>';
                helpRecepcion.textContent = 'No hay recepciones con peso disponible para esta especie. Registre primero una recepción.';
            }
        } catch (e) {
            selectRecepcion.innerHTML = '<option value="">-- Error al cargar recepciones --</option>';
            showAlert('Base de datos o servidor fuera de línea por favor contacte a soporte técnico', 'danger');
        }
        guardarBorrador();
    }

    // 2. Manejo de cambio de especie / canal
    if (selectEspecie) {
        selectEspecie.addEventListener('change', () => {
            cargarRecepcionesParaCanal(selectEspecie.value);
        });
    }

    // Función desacoplada para manejar cambio de recepción
    async function manejarCambioRecepcion() {
        hideAlert();
        const nroRec = selectRecepcion.value;
        const selectedOpt = selectRecepcion.options[selectRecepcion.selectedIndex];

        grpPieza.style.display = 'none';
        selectPieza.innerHTML = '<option value="">-- Seleccione la res individual --</option>';
        badgePesoDisp.textContent = '';
        inputPeso.value = '';

        if (!nroRec || !selectedOpt || !selectedOpt.dataset.metodo) {
            if (resRecepcion) resRecepcion.textContent = '-';
            guardarBorrador();
            return;
        }

        if (resRecepcion) resRecepcion.textContent = nroRec;

        const metodo = selectedOpt.dataset.metodo;
        const disp = parseFloat(selectedOpt.dataset.disp || '0');
        badgePesoDisp.textContent = `Disp: ${disp.toFixed(4)} kg`;

        if (metodo === 'PESO_INDIVIDUAL') {
            grpPieza.style.display = 'block';
            selectPieza.innerHTML = '<option value="">-- Cargando piezas disponibles... --</option>';
            try {
                const res = await fetch(`/api/recepcion/detalle?nrorec=${encodeURIComponent(nroRec)}`);
                const json = await res.json();
                if (json.success && json.details) {
                    const disponibles = json.details.filter(d => d.EstadoDesposte === 'DISPONIBLE');
                    if (disponibles.length > 0) {
                        selectPieza.innerHTML = '<option value="">-- Seleccione la res individual --</option>';
                        disponibles.forEach(d => {
                            const op = document.createElement('option');
                            op.value = d.NroEspecie;
                            op.dataset.peso = d.PesoNeto;
                            op.textContent = `Res #${d.NroEspecie} - Peso: ${parseFloat(d.PesoNeto).toFixed(4)} kg`;
                            selectPieza.appendChild(op);
                        });
                    } else {
                        selectPieza.innerHTML = '<option value="">-- Todas las piezas de esta recepción ya fueron despostadas --</option>';
                    }
                }
            } catch (e) {
                selectPieza.innerHTML = '<option value="">-- Error cargando detalle de piezas --</option>';
            }
        } else {
            inputPeso.value = disp.toFixed(4);
        }
        guardarBorrador();
    }

    // 3. Manejo de cambio de recepción
    if (selectRecepcion) {
        selectRecepcion.addEventListener('change', manejarCambioRecepcion);
    }

    // 4. Manejo de cambio de pieza individual (Método 3)
    if (selectPieza) {
        selectPieza.addEventListener('change', () => {
            const opt = selectPieza.options[selectPieza.selectedIndex];
            if (opt && opt.dataset.peso) {
                inputPeso.value = parseFloat(opt.dataset.peso).toFixed(4);
            }
            guardarBorrador();
        });
    }

    // 5. Teclado numérico táctil en pantalla y sanitización
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
                    if (decimals.length >= 4) return; // Máximo 4 decimales
                }
                inputPeso.value = (cur === '0') ? key : cur + key;
            }
            inputPeso.focus();
            guardarBorrador();
        });
    });

    // 6. Envío del formulario
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideAlert();

            const pesoVal = parseFloat(inputPeso.value || '0');
            if (pesoVal <= 0 || isNaN(pesoVal)) {
                showAlert('El peso de la canal para desposte debe ser mayor a 0 kg.', 'danger');
                playBeep('error');
                inputPeso.focus();
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
                    // Redirigir a la estación de pesaje en la sala de desposte
                    setTimeout(() => {
                        window.location.href = `/deboning?order=${encodeURIComponent(json.nro_orden)}`;
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
    document.getElementById('btnDescartarBorrador')?.addEventListener('click', async () => {
        const conf = await AppModal.confirm('¿Desea descartar el borrador y restablecer los campos?', {
            title: 'Descartar Borrador',
            danger: true,
            confirmText: 'Sí, descartar'
        });
        if (conf) {
            sessionStorage.removeItem('st_draft_desposte_nueva');
            if (form) form.reset();
            const notice = document.getElementById('desposteDraftNotice');
            if (notice) notice.style.display = 'none';
            if (resMesa) resMesa.textContent = inputMesa ? inputMesa.value : '-';
            if (resEspecie) resEspecie.textContent = '-';
            if (resRecepcion) resRecepcion.textContent = '-';
            grpPieza.style.display = 'none';
            badgePesoDisp.textContent = '';
            hideAlert();
        }
    });

    // Enlaces de regreso con confirmación si hay datos ingresados
    document.querySelectorAll('a[href="/deboning"]').forEach(link => {
        link.addEventListener('click', async (e) => {
            const hasData = (inputPeso && parseFloat(inputPeso.value || '0') > 0) ||
                (selectRecepcion && selectRecepcion.value !== '');
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
                    window.location.href = '/deboning';
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
