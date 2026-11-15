/**
 * ST-Despostadora - Lógica de Registro de Nueva Recepción (Página Completa)
 * Cumplimiento estricto de Content Security Policy (cero inline handlers).
 */
document.addEventListener('DOMContentLoaded', () => {
    let metodoActual = 'SOLO_PESO';
    const piezasIndividuales = [];
    let isSubmitting = false;

    // Elementos del DOM
    const form = document.getElementById('formNuevaRecepcionPagina');
    const selectEspecie = document.getElementById('rec_codprod');
    const inputUbic = document.getElementById('rec_codubic');
    const inputObs = document.getElementById('rec_observaciones');
    const panelEstandar = document.getElementById('panel_pesos_estandar');
    const panelIndividual = document.getElementById('panel_pesos_individual');
    const grpCantidad = document.getElementById('grp_cantidad_especies');
    const boxPromedio = document.getElementById('box_promedio_cabeza');
    const alertBox = document.getElementById('recepcionAlert');

    // Inputs estándar
    const inputCantidad = document.getElementById('rec_cantidad');
    const inputPesoBruto = document.getElementById('rec_peso_bruto');
    const inputTara = document.getElementById('rec_tara');
    const inputPesoNeto = document.getElementById('rec_peso_neto');
    const lblPesoPromedio = document.getElementById('lbl_peso_promedio');
    const lblCostoPromedio = document.getElementById('lbl_costo_promedio');
    const lblCostoTotalEstandar = document.getElementById('lbl_costo_total_estandar');

    // Inputs individual
    const inputPesoInd = document.getElementById('input_peso_individual');
    const btnAgregarPieza = document.getElementById('btnAgregarPieza');
    const listaPiezas = document.getElementById('lista_piezas_individuales');
    const msgSinPiezas = document.getElementById('msgSinPiezas');
    const lblIndTotalPiezas = document.getElementById('lbl_ind_total_piezas');
    const lblIndTotalPeso = document.getElementById('lbl_ind_total_peso');
    const lblIndPesoPromedio = document.getElementById('lbl_ind_peso_promedio');
    const lblIndCostoTotal = document.getElementById('lbl_ind_costo_total');
    const badgeTotalPiezasTop = document.getElementById('badgeTotalPiezasTop');

    function showAlert(msg, type = 'error') {
        if (!alertBox) return;
        alertBox.textContent = msg;
        alertBox.className = type === 'error' ? 'alert alert-error' : 'alert alert-success';
        alertBox.style.display = 'block';
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideAlert() {
        if (alertBox) alertBox.style.display = 'none';
    }

    // Persistencia y Respaldo Local (Auto-Save para evitar pérdidas por F5 o recargas involuntarias)
    function guardarBorrador() {
        if (isSubmitting) return;
        const draft = {
            codprod: selectEspecie?.value || '',
            codubic: inputUbic?.value || '',
            observaciones: inputObs?.value || '',
            metodo: metodoActual,
            cantidad: inputCantidad?.value || '',
            peso_bruto: inputPesoBruto?.value || '',
            tara: inputTara?.value || '',
            piezas: piezasIndividuales
        };
        const hasContent = draft.piezas.length > 0 || draft.codprod !== '' ||
            parseFloat(draft.peso_bruto || '0') > 0 || draft.observaciones.trim() !== '';
        if (hasContent) {
            sessionStorage.setItem('st_draft_recepcion_nueva', JSON.stringify(draft));
        } else {
            sessionStorage.removeItem('st_draft_recepcion_nueva');
        }
    }

    function restaurarBorrador() {
        const raw = sessionStorage.getItem('st_draft_recepcion_nueva');
        if (!raw) return;
        try {
            const draft = JSON.parse(raw);
            if (!draft) return;

            if (draft.codprod && selectEspecie) selectEspecie.value = draft.codprod;
            if (draft.codubic && inputUbic) inputUbic.value = draft.codubic;
            if (draft.observaciones && inputObs) inputObs.value = draft.observaciones;
            if (draft.cantidad && inputCantidad) inputCantidad.value = draft.cantidad;
            if (draft.peso_bruto && inputPesoBruto) inputPesoBruto.value = draft.peso_bruto;
            if (draft.tara && inputTara) inputTara.value = draft.tara;

            if (Array.isArray(draft.piezas) && draft.piezas.length > 0) {
                piezasIndividuales.length = 0;
                piezasIndividuales.push(...draft.piezas);
                renderizarListaPiezas();
                actualizarResumenIndividual();
            }

            if (draft.metodo) {
                const card = document.querySelector(`.metodo-card-btn[data-method="${draft.metodo}"]`);
                if (card) {
                    card.click();
                } else {
                    actualizarVistasMetodo();
                }
            } else {
                recalcularNetoEstandar();
            }

            const notice = document.getElementById('recepcionDraftNotice');
            if (notice) notice.style.display = 'flex';
        } catch (e) {
            console.error('Error restaurando borrador de recepción:', e);
        }
    }

    // 1. Selector de Método de Entrada
    document.querySelectorAll('.metodo-card-btn').forEach(card => {
        card.addEventListener('click', () => {
            document.querySelectorAll('.metodo-card-btn').forEach(c => c.classList.remove('active'));
            card.classList.add('active');
            const radio = card.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                metodoActual = radio.value;
                actualizarVistasMetodo();
            }
        });
    });

    function actualizarVistasMetodo() {
        hideAlert();
        if (metodoActual === 'SOLO_PESO') {
            panelEstandar.style.display = 'block';
            panelIndividual.style.display = 'none';
            grpCantidad.style.display = 'none';
            boxPromedio.style.display = 'none';
            recalcularNetoEstandar();
        } else if (metodoActual === 'PESO_CANTIDAD') {
            panelEstandar.style.display = 'block';
            panelIndividual.style.display = 'none';
            grpCantidad.style.display = 'block';
            boxPromedio.style.display = 'block';
            recalcularNetoEstandar();
        } else if (metodoActual === 'PESO_INDIVIDUAL') {
            panelEstandar.style.display = 'none';
            panelIndividual.style.display = 'block';
            actualizarResumenIndividual();
            if (inputPesoInd) inputPesoInd.focus();
        }
        guardarBorrador();
    }

    // 2. Cálculos para Método 1 y 2
    function recalcularNetoEstandar() {
        const bruto = parseFloat(inputPesoBruto?.value || '0') || 0;
        const tara = parseFloat(inputTara?.value || '0') || 0;
        const neto = Math.max(0, bruto - tara);
        if (inputPesoNeto) inputPesoNeto.value = neto.toFixed(4);

        const cant = parseInt(inputCantidad?.value || '1', 10) || 1;
        const opt = selectEspecie?.options[selectEspecie.selectedIndex];
        const costoUnit = parseFloat(opt?.getAttribute('data-costo') || '0') || 0;

        const costoTotal = neto * costoUnit;
        if (lblCostoTotalEstandar) lblCostoTotalEstandar.textContent = '$' + costoTotal.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 4 });

        if (metodoActual === 'PESO_CANTIDAD' && cant > 0) {
            const promPeso = neto / cant;
            const promCosto = costoTotal / cant;
            if (lblPesoPromedio) lblPesoPromedio.textContent = promPeso.toFixed(4) + ' kg';
            if (lblCostoPromedio) lblCostoPromedio.textContent = '$' + promCosto.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
        }
        guardarBorrador();
    }

    [inputPesoBruto, inputTara, inputCantidad, inputObs, inputUbic].forEach(inp => {
        inp?.addEventListener('input', () => {
            recalcularNetoEstandar();
            guardarBorrador();
        });
        inp?.addEventListener('change', guardarBorrador);
    });

    selectEspecie?.addEventListener('change', () => {
        recalcularNetoEstandar();
        actualizarResumenIndividual();
        guardarBorrador();
    });

    // 3. Lógica para Método 3 (Pesaje Individual)
    function agregarPieza() {
        const val = parseFloat(inputPesoInd?.value || '0');
        if (isNaN(val) || val <= 0) {
            showAlert('Por favor ingrese un peso válido mayor a 0 para la res/canal.');
            inputPesoInd?.focus();
            return;
        }

        piezasIndividuales.push(val);
        inputPesoInd.value = '';
        hideAlert();
        renderizarListaPiezas();
        actualizarResumenIndividual();
        guardarBorrador();
        inputPesoInd.focus();
    }

    btnAgregarPieza?.addEventListener('click', agregarPieza);

    inputPesoInd?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            agregarPieza();
        }
    });

    // Teclado Numérico Táctil (Keypad)
    inputPesoInd?.addEventListener('input', () => {
        if (!inputPesoInd) return;
        let v = inputPesoInd.value.replace(',', '.').replace(/[^0-9.]/g, '');
        const parts = v.split('.');
        if (parts.length > 2) v = parts[0] + '.' + parts.slice(1).join('');
        if (parts[1] && parts[1].length > 4) v = parts[0] + '.' + parts[1].slice(0, 4);
        inputPesoInd.value = v;
    });

    document.querySelectorAll('.keypad-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const key = btn.getAttribute('data-key');
            if (!inputPesoInd) return;

            let cur = (inputPesoInd.value || '').trim();
            if (key === 'clear') {
                inputPesoInd.value = '';
            } else if (key === '.') {
                if (!cur.includes('.')) {
                    inputPesoInd.value = (cur === '') ? '0.' : cur + '.';
                }
            } else {
                if (cur.includes('.')) {
                    const decimals = cur.split('.')[1] || '';
                    if (decimals.length >= 4) return; // Máximo 4 decimales
                }
                inputPesoInd.value = (cur === '0') ? key : cur + key;
            }
            inputPesoInd.focus();
        });
    });

    // Renderizar lista de piezas
    function renderizarListaPiezas() {
        if (!listaPiezas) return;
        listaPiezas.innerHTML = '';

        if (piezasIndividuales.length === 0) {
            if (msgSinPiezas) {
                listaPiezas.appendChild(msgSinPiezas);
                msgSinPiezas.style.display = 'block';
            }
            return;
        }

        piezasIndividuales.forEach((peso, idx) => {
            const item = document.createElement('div');
            item.className = 'pieza-item';

            const info = document.createElement('div');
            info.innerHTML = `<strong style="color:var(--text-primary);">Res #${idx + 1}</strong>: <span style="font-family:monospace; font-size:1.1rem; color:var(--primary); font-weight:700;">${peso.toFixed(4)} kg</span>`;

            const btnEliminar = document.createElement('button');
            btnEliminar.type = 'button';
            btnEliminar.className = 'pieza-btn-eliminar';
            btnEliminar.setAttribute('title', 'Eliminar Res');
            btnEliminar.innerHTML = `<svg class="icon" viewBox="0 0 24 24" style="width:16px; height:16px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>`;
            btnEliminar.addEventListener('click', () => {
                piezasIndividuales.splice(idx, 1);
                renderizarListaPiezas();
                actualizarResumenIndividual();
                guardarBorrador();
            });

            item.appendChild(info);
            item.appendChild(btnEliminar);
            listaPiezas.appendChild(item);
        });

        // Auto-scroll al último elemento agregado
        listaPiezas.scrollTop = listaPiezas.scrollHeight;
    }

    function actualizarResumenIndividual() {
        const totalPiezas = piezasIndividuales.length;
        const totalPeso = piezasIndividuales.reduce((acc, p) => acc + p, 0);
        const promPeso = totalPiezas > 0 ? (totalPeso / totalPiezas) : 0;

        const opt = selectEspecie?.options[selectEspecie.selectedIndex];
        const costoUnit = parseFloat(opt?.getAttribute('data-costo') || '0') || 0;
        const costoTotal = totalPeso * costoUnit;

        if (lblIndTotalPiezas) lblIndTotalPiezas.textContent = totalPiezas;
        if (lblIndTotalPeso) lblIndTotalPeso.textContent = totalPeso.toFixed(4) + ' kg';
        if (lblIndPesoPromedio) lblIndPesoPromedio.textContent = promPeso.toFixed(4) + ' kg';
        if (lblIndCostoTotal) lblIndCostoTotal.textContent = '$' + costoTotal.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
        if (badgeTotalPiezasTop) badgeTotalPiezasTop.textContent = `${totalPiezas} Reses Capturadas`;
    }

    document.getElementById('btnLimpiarPiezas')?.addEventListener('click', async () => {
        if (piezasIndividuales.length === 0) return;
        const conf = await AppModal.confirm('¿Está seguro de limpiar todas las piezas ingresadas?', {
            title: 'Limpiar Piezas',
            danger: true,
            confirmText: 'Sí, limpiar'
        });
        if (conf) {
            piezasIndividuales.length = 0;
            renderizarListaPiezas();
            actualizarResumenIndividual();
            guardarBorrador();
        }
    });

    // 4. Envío del Formulario
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();

        const codprod = selectEspecie?.value || '';
        if (!codprod) {
            showAlert('Debe seleccionar una especie o producto de canal.');
            selectEspecie?.focus();
            return;
        }

        const codubic = document.getElementById('rec_codubic')?.value || '';
        if (!codubic) {
            showAlert('Debe seleccionar el depósito receptor de la carga.');
            return;
        }

        const btnSubmit = document.getElementById('btnProcesarRecepcion');
        const formData = new FormData(form);

        if (metodoActual === 'PESO_INDIVIDUAL') {
            if (piezasIndividuales.length === 0) {
                showAlert('Para el pesaje individual debe registrar al menos una res / canal.');
                inputPesoInd?.focus();
                return;
            }
            formData.set('cantidad', piezasIndividuales.length.toString());
            const totalPeso = piezasIndividuales.reduce((acc, p) => acc + p, 0);
            formData.set('peso_bruto', totalPeso.toFixed(4));
            formData.set('tara', '0.0000');
            formData.set('peso_neto', totalPeso.toFixed(4));
            formData.set('pesos_individuales', JSON.stringify(piezasIndividuales));
        } else if (metodoActual === 'SOLO_PESO') {
            const neto = parseFloat(inputPesoNeto?.value || '0');
            if (neto <= 0) {
                showAlert('El peso neto debe ser mayor a 0 kg.');
                inputPesoBruto?.focus();
                return;
            }
            formData.set('cantidad', '0');
        } else if (metodoActual === 'PESO_CANTIDAD') {
            const neto = parseFloat(inputPesoNeto?.value || '0');
            const cant = parseInt(inputCantidad?.value || '0', 10);
            if (neto <= 0) {
                showAlert('El peso neto debe ser mayor a 0 kg.');
                inputPesoBruto?.focus();
                return;
            }
            if (cant <= 0) {
                showAlert('La cantidad de especies / reses debe ser al menos 1.');
                inputCantidad?.focus();
                return;
            }
        }

        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Guardando Recepción...';
        }

        try {
            const resp = await fetch('/api/recepcion/crear', {
                method: 'POST',
                body: formData
            });

            const data = await resp.json();
            if (data.success) {
                isSubmitting = true;
                sessionStorage.removeItem('st_draft_recepcion_nueva');
                showAlert(data.message || 'Recepción registrada con éxito. Redirigiendo...', 'success');
                setTimeout(() => {
                    window.location.href = '/recepcion';
                }, 1000);
            } else {
                showAlert(data.error || 'Ocurrió un error al registrar la recepción.');
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = 'Guardar y Registrar Recepción';
                }
            }
        } catch (err) {
            showAlert('Base de datos o servidor fuera de línea por favor contacte a soporte técnico');
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Guardar y Registrar Recepción';
            }
        }
    });

    // Descartar borrador manualmente
    document.getElementById('btnDescartarBorrador')?.addEventListener('click', async () => {
        const conf = await AppModal.confirm('¿Está seguro de descartar el borrador y limpiar todos los datos?', {
            title: 'Descartar Borrador',
            danger: true,
            confirmText: 'Sí, descartar'
        });
        if (conf) {
            sessionStorage.removeItem('st_draft_recepcion_nueva');
            piezasIndividuales.length = 0;
            renderizarListaPiezas();
            actualizarResumenIndividual();
            if (form) form.reset();
            const card1 = document.getElementById('card_metodo_1');
            if (card1) card1.click();
            const notice = document.getElementById('recepcionDraftNotice');
            if (notice) notice.style.display = 'none';
            recalcularNetoEstandar();
            hideAlert();
        }
    });

    // Enlaces de regreso con confirmación si hay pesajes cargados
    document.querySelectorAll('a[href="/recepcion"]').forEach(link => {
        link.addEventListener('click', async (e) => {
            if (piezasIndividuales.length > 0) {
                e.preventDefault();
                const conf = await AppModal.confirm('Tiene reses pesadas sin guardar en esta recepción. ¿Desea salir y descartar los datos?', {
                    title: 'Salir de Nueva Recepción',
                    danger: true,
                    confirmText: 'Salir y descartar'
                });
                if (conf) {
                    isSubmitting = true;
                    sessionStorage.removeItem('st_draft_recepcion_nueva');
                    window.location.href = '/recepcion';
                }
            } else {
                isSubmitting = true;
                sessionStorage.removeItem('st_draft_recepcion_nueva');
            }
        });
    });

    // Restaurar borrador previo si existe en la sesión
    restaurarBorrador();
});
