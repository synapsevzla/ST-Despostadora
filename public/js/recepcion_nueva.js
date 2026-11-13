/**
 * ST-Despostadora - Lógica de Registro de Nueva Recepción (Página Completa)
 * Cumplimiento estricto de Content Security Policy (cero inline handlers).
 */
document.addEventListener('DOMContentLoaded', () => {
    let metodoActual = 'SOLO_PESO';
    const piezasIndividuales = [];

    // Elementos del DOM
    const form = document.getElementById('formNuevaRecepcionPagina');
    const selectEspecie = document.getElementById('rec_codprod');
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
    }

    [inputPesoBruto, inputTara, inputCantidad].forEach(inp => {
        inp?.addEventListener('input', recalcularNetoEstandar);
    });

    selectEspecie?.addEventListener('change', () => {
        recalcularNetoEstandar();
        actualizarResumenIndividual();
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
    document.querySelectorAll('.keypad-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const key = btn.getAttribute('data-key');
            if (!inputPesoInd) return;

            if (key === 'clear') {
                inputPesoInd.value = '';
            } else if (key === '.') {
                if (!inputPesoInd.value.includes('.')) {
                    inputPesoInd.value = (inputPesoInd.value || '0') + '.';
                }
            } else {
                if (inputPesoInd.value.includes('.')) {
                    const decimals = inputPesoInd.value.split('.')[1] || '';
                    if (decimals.length >= 4) return; // Máximo 4 decimales
                }
                inputPesoInd.value += key;
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

    document.getElementById('btnLimpiarPiezas')?.addEventListener('click', () => {
        if (piezasIndividuales.length === 0) return;
        if (confirm('¿Está seguro de limpiar todas las piezas ingresadas?')) {
            piezasIndividuales.length = 0;
            renderizarListaPiezas();
            actualizarResumenIndividual();
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
            showAlert('Error de comunicación con el servidor: ' + err.message);
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Guardar y Registrar Recepción';
            }
        }
    });
});
