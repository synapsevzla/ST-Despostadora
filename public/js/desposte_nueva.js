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

    const alertBox = document.getElementById('desposteAlert');
    const form = document.getElementById('formNuevaOrdenPagina');
    const inputMesa = document.getElementById('inputLineaMesa');
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
        });
    }

    // 2. Manejo de cambio de especie / canal
    if (selectEspecie) {
        selectEspecie.addEventListener('change', async () => {
            hideAlert();
            const canal = selectEspecie.value;
            const opt = selectEspecie.options[selectEspecie.selectedIndex];
            if (resEspecie) resEspecie.textContent = canal ? opt.text : '-';

            selectRecepcion.innerHTML = '<option value="">-- Cargando recepciones disponibles... --</option>';
            grpPieza.style.display = 'none';
            selectPieza.innerHTML = '<option value="">-- Seleccione la res individual --</option>';
            helpRecepcion.textContent = '';
            badgePesoDisp.textContent = '';
            inputPeso.value = '';
            if (resRecepcion) resRecepcion.textContent = '-';

            if (!canal) {
                selectRecepcion.innerHTML = '<option value="">-- Seleccione primero una especie --</option>';
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
                showAlert('Error comunicando con el servidor para consultar recepciones.', 'danger');
            }
        });
    }

    // 3. Manejo de cambio de recepción
    if (selectRecepcion) {
        selectRecepcion.addEventListener('change', async () => {
            hideAlert();
            const nroRec = selectRecepcion.value;
            const selectedOpt = selectRecepcion.options[selectRecepcion.selectedIndex];

            grpPieza.style.display = 'none';
            selectPieza.innerHTML = '<option value="">-- Seleccione la res individual --</option>';
            badgePesoDisp.textContent = '';
            inputPeso.value = '';

            if (!nroRec || !selectedOpt || !selectedOpt.dataset.metodo) {
                if (resRecepcion) resRecepcion.textContent = '-';
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
        });
    }

    // 4. Manejo de cambio de pieza individual (Método 3)
    if (selectPieza) {
        selectPieza.addEventListener('change', () => {
            const opt = selectPieza.options[selectPieza.selectedIndex];
            if (opt && opt.dataset.peso) {
                inputPeso.value = parseFloat(opt.dataset.peso).toFixed(4);
            }
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
                showAlert('Error de conexión con el servidor al registrar la orden.', 'danger');
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = originalHtml;
            }
        });
    }
});
