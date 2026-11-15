/**
 * Deboning Annulment Module - ST Despostadora
 * Maneja la confirmación, captura de motivo y anulación atómica de órdenes de desposte en sala.
 */

document.addEventListener('DOMContentLoaded', () => {
    const modalAnnul = document.getElementById('modalAnnulOrder');
    if (!modalAnnul) return;

    const btnPromptAnnul = document.getElementById('btnPromptAnnulStation');
    const btnCancelAnnul = document.getElementById('btnCancelAnnulOrder');
    const btnConfirmAnnul = document.getElementById('btnConfirmAnnulOrder');
    const inputMotivo = document.getElementById('inputMotivoAnulacion');
    const alertError = document.getElementById('annulAlertError');

    const txtOrderNumber = document.getElementById('annulOrderNumber');
    const txtPesoCanal = document.getElementById('annulPesoCanal');
    const txtRecepcion = document.getElementById('annulRecepcion');
    const rowPieza = document.getElementById('annulPiezaRow');
    const rowCortes = document.getElementById('annulCortesRow');
    const txtCortesCount = document.getElementById('annulCortesCount');

    // Botones de motivo rápido
    document.querySelectorAll('.btn-quick-reason').forEach(btn => {
        btn.addEventListener('click', () => {
            const reason = btn.getAttribute('data-reason') || '';
            if (inputMotivo) {
                inputMotivo.value = reason;
                inputMotivo.focus();
            }
        });
    });

    // Abrir modal de anulación
    if (btnPromptAnnul) {
        btnPromptAnnul.addEventListener('click', () => {
            if (typeof currentOrder === 'undefined' || !currentOrder) {
                if (typeof showDeboningAlert === 'function') {
                    showDeboningAlert('No hay una orden activa seleccionada para anular.', 'danger');
                }
                return;
            }

            if (currentOrder.Estado !== 'EN_PROCESO') {
                if (typeof showDeboningAlert === 'function') {
                    showDeboningAlert(`La orden está en estado '${currentOrder.Estado}' y no puede anularse.`, 'warning');
                }
                return;
            }

            // Llenar datos informativos
            if (txtOrderNumber) txtOrderNumber.textContent = currentOrder.NroOrdenDesposte || '-';
            if (txtPesoCanal) txtPesoCanal.textContent = (parseFloat(currentOrder.PesoCanalEntrada) || 0).toFixed(2) + ' kg';
            if (txtRecepcion) txtRecepcion.textContent = currentOrder.NroRecepcion || '-';

            // Detectar si aplica pieza individual
            if (rowPieza) {
                const esIndividual = (currentOrder.MetodoEntrada === 'PESO_INDIVIDUAL');
                rowPieza.style.display = esIndividual ? 'list-item' : 'none';
            }

            // Detectar cortes pesados parciales
            if (rowCortes && txtCortesCount) {
                const cortesArr = (typeof lastBalanceData !== 'undefined' && lastBalanceData && lastBalanceData.cortes) 
                    ? lastBalanceData.cortes 
                    : [];
                if (cortesArr.length > 0) {
                    txtCortesCount.textContent = cortesArr.length.toString();
                    rowCortes.style.display = 'list-item';
                } else {
                    rowCortes.style.display = 'none';
                }
            }

            if (inputMotivo) inputMotivo.value = '';
            if (alertError) {
                alertError.style.display = 'none';
                alertError.textContent = '';
            }

            modalAnnul.style.display = 'flex';
            if (inputMotivo) inputMotivo.focus();
        });
    }

    // Cerrar modal
    const closeAnnulModal = () => {
        modalAnnul.style.display = 'none';
    };

    if (btnCancelAnnul) btnCancelAnnul.addEventListener('click', closeAnnulModal);
    document.querySelectorAll('[data-action="close-modal-annul-order"]').forEach(el => {
        el.addEventListener('click', closeAnnulModal);
    });

    // Cerrar al hacer clic en el backdrop
    modalAnnul.addEventListener('click', (e) => {
        if (e.target === modalAnnul) closeAnnulModal();
    });

    // Confirmar anulación
    if (btnConfirmAnnul) {
        btnConfirmAnnul.addEventListener('click', async () => {
            const motivo = inputMotivo ? inputMotivo.value.trim() : '';

            if (motivo.length < 5) {
                if (alertError) {
                    alertError.textContent = 'Debe indicar un motivo de anulación válido de al menos 5 caracteres.';
                    alertError.style.display = 'block';
                }
                if (inputMotivo) inputMotivo.focus();
                return;
            }

            const csrfEl = document.getElementById('csrfToken');
            const csrfToken = csrfEl ? csrfEl.value : '';
            const nroOrden = currentOrder ? currentOrder.NroOrdenDesposte : '';

            if (!nroOrden) {
                if (alertError) {
                    alertError.textContent = 'Identificador de orden no disponible.';
                    alertError.style.display = 'block';
                }
                return;
            }

            btnConfirmAnnul.disabled = true;
            btnConfirmAnnul.innerHTML = '<span class="spinner-sm"></span> Anulando orden...';

            try {
                const formData = new FormData();
                formData.append('csrf_token', csrfToken);
                formData.append('nro_orden', nroOrden);
                formData.append('motivo', motivo);

                const res = await fetch('/api/desposte/anular', {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json();

                if (data.success) {
                    closeAnnulModal();
                    if (typeof showDeboningAlert === 'function') {
                        showDeboningAlert(data.message || `Orden ${nroOrden} anulada exitosamente.`, 'success');
                    }
                    // Redirigir al tablero y refrescar
                    if (typeof showBoardView === 'function') {
                        showBoardView();
                    } else if (typeof showBoard === 'function') {
                        showBoard();
                    } else {
                        window.location.href = (window.APP_BASE_URL || '') + '/deboning';
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 1200);
                } else {
                    if (alertError) {
                        alertError.textContent = data.error || 'Ocurrió un error al anular la orden.';
                        alertError.style.display = 'block';
                    }
                }
            } catch (err) {
                if (alertError) {
                    alertError.textContent = 'Error de comunicación con el servidor: ' + err.message;
                    alertError.style.display = 'block';
                }
            } finally {
                btnConfirmAnnul.disabled = false;
                btnConfirmAnnul.innerHTML = `
                    <svg class="icon" viewBox="0 0 24 24" style="width:16px; height:16px; margin-right:4px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    Confirmar Anulación y Regresar Canal
                `;
            }
        });
    }
});
