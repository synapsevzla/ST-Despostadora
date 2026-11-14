/**
 * ST-Despostadora - Modal de Consulta y Trazabilidad de Documentos ERP Saint
 * Vanilla ES6+, 100% CSP compliant.
 */
(() => {
    let currentModalOrder = null;

    const modal = document.getElementById('modalErpDocsDetails');
    const titleEl = document.getElementById('erpModalOrderTitle');
    const globalBadgeEl = document.getElementById('erpModalGlobalBadge');
    const alertEl = document.getElementById('erpModalAlert');

    const descNumeroDEl = document.getElementById('erpModalDescargoNumeroD');
    const descTipoOpIEl = document.getElementById('erpModalDescargoTipoOpI');
    const descStatusBadge = document.getElementById('erpModalDescargoStatusBadge');
    const descNumeroDOficialEl = document.getElementById('erpModalDescargoNumeroDOficial');
    const descTipoFinalEl = document.getElementById('erpModalDescargoTipoFinal');
    const descFechaEl = document.getElementById('erpModalDescargoFecha');

    const cargNumeroDEl = document.getElementById('erpModalCargoNumeroD');
    const cargTipoOpIEl = document.getElementById('erpModalCargoTipoOpI');
    const cargStatusBadge = document.getElementById('erpModalCargoStatusBadge');
    const cargNumeroDOficialEl = document.getElementById('erpModalCargoNumeroDOficial');
    const cargTipoFinalEl = document.getElementById('erpModalCargoTipoFinal');
    const cargFechaEl = document.getElementById('erpModalCargoFecha');

    const btnRefresh = document.getElementById('btnRefreshErpModal');

    function closeModal() {
        if (modal) modal.style.display = 'none';
        currentModalOrder = null;
    }

    function renderModalData(data) {
        if (!data) return;
        const nro = data.NroOrdenDesposte || currentModalOrder;
        if (titleEl) titleEl.textContent = nro;

        const cruce = data.EstadoCruceERP || 'PENDIENTE_CIERRE';
        if (globalBadgeEl) {
            globalBadgeEl.className = 'badge';
            if (cruce === 'TOTALMENTE_PROCESADO') {
                globalBadgeEl.className += ' badge-success';
                globalBadgeEl.textContent = 'PROCESADO ERP (OK)';
            } else if (cruce === 'DESCARGO_OK_CARGO_PEND') {
                globalBadgeEl.className += ' badge-warning';
                globalBadgeEl.textContent = 'DESCARGO OK - CARGO EN ESPERA';
            } else if (cruce === 'CARGO_OK_DESCARGO_PEND') {
                globalBadgeEl.className += ' badge-warning';
                globalBadgeEl.textContent = 'CARGO OK - DESCARGO EN ESPERA';
            } else if (cruce.startsWith('ERROR_')) {
                globalBadgeEl.style.background = 'rgba(238, 103, 55, 0.2)';
                globalBadgeEl.style.color = '#ff5252';
                globalBadgeEl.textContent = 'ERROR EN OPERACIÓN ERP';
            } else if (data.DocDescargoEnviado == 1 || data.DocCargoEnviado == 1 || cruce === 'AMBOS_EN_ESPERA') {
                globalBadgeEl.style.background = 'rgba(30, 144, 255, 0.15)';
                globalBadgeEl.style.color = '#2196f3';
                globalBadgeEl.textContent = 'DOCS EN ESPERA';
            } else {
                globalBadgeEl.style.background = 'rgba(140, 115, 101, 0.2)';
                globalBadgeEl.style.color = 'var(--text-muted)';
                globalBadgeEl.textContent = 'PENDIENTE';
            }
        }

        // 1. Descargo de Canal
        const descEnviado = data.DocDescargoEnviado == 1;
        const descProc = data.DocDescargoProcesado == 1;
        const descNumD = data.DocDescargoNumeroD || '-';
        const descNumOfic = data.DocDescargoNumeroDOficial || '';
        const descTipoFinal = data.DocDescargoTipoOpIFinal || '';
        const descFecha = data.FechaDocDescargoProcesado ? String(data.FechaDocDescargoProcesado).substring(0, 16) : '-';

        if (descNumeroDEl) descNumeroDEl.textContent = descNumD;
        if (descTipoOpIEl) descTipoOpIEl.textContent = descEnviado ? 'R (Documento en Espera)' : '-';

        if (descStatusBadge) {
            descStatusBadge.className = 'badge';
            if (descProc) {
                descStatusBadge.className += ' badge-success';
                descStatusBadge.textContent = 'PROCESADO (P)';
            } else if (descTipoFinal && descTipoFinal !== 'R' && descTipoFinal !== 'P') {
                descStatusBadge.style.background = 'rgba(238, 103, 55, 0.2)';
                descStatusBadge.style.color = '#ff5252';
                descStatusBadge.textContent = `ERROR (Tipo '${descTipoFinal}')`;
            } else if (descEnviado) {
                descStatusBadge.style.background = 'rgba(30, 144, 255, 0.15)';
                descStatusBadge.style.color = '#2196f3';
                descStatusBadge.textContent = 'EN ESPERA (R)';
            } else {
                descStatusBadge.textContent = 'NO EMITIDO';
            }
        }

        if (descNumeroDOficialEl) {
            if (descProc && descNumOfic) {
                descNumeroDOficialEl.textContent = `${descNumOfic} (Oficial ERP)`;
                descNumeroDOficialEl.style.color = 'var(--success)';
            } else {
                descNumeroDOficialEl.textContent = descEnviado ? 'Pendiente por procesar en Saint' : 'No generado';
                descNumeroDOficialEl.style.color = 'var(--text-muted)';
            }
        }

        if (descTipoFinalEl) {
            descTipoFinalEl.textContent = descProc ? `${descTipoFinal} (Descargo de Inventario)` : (descTipoFinal ? `${descTipoFinal} (Errado)` : '-');
        }
        if (descFechaEl) descFechaEl.textContent = descFecha;

        // 2. Cargo de Cortes
        const cargEnviado = data.DocCargoEnviado == 1;
        const cargProc = data.DocCargoProcesado == 1;
        const cargNumD = data.DocCargoNumeroD || '-';
        const cargNumOfic = data.DocCargoNumeroDOficial || '';
        const cargTipoFinal = data.DocCargoTipoOpIFinal || '';
        const cargFecha = data.FechaDocCargoProcesado ? String(data.FechaDocCargoProcesado).substring(0, 16) : '-';

        if (cargNumeroDEl) cargNumeroDEl.textContent = cargNumD;
        if (cargTipoOpIEl) cargTipoOpIEl.textContent = cargEnviado ? 'R (Documento en Espera)' : '-';

        if (cargStatusBadge) {
            cargStatusBadge.className = 'badge';
            if (cargProc) {
                cargStatusBadge.className += ' badge-success';
                cargStatusBadge.textContent = 'PROCESADO (O)';
            } else if (cargTipoFinal && cargTipoFinal !== 'R' && cargTipoFinal !== 'O') {
                cargStatusBadge.style.background = 'rgba(238, 103, 55, 0.2)';
                cargStatusBadge.style.color = '#ff5252';
                cargStatusBadge.textContent = `ERROR (Tipo '${cargTipoFinal}')`;
            } else if (cargEnviado) {
                cargStatusBadge.style.background = 'rgba(30, 144, 255, 0.15)';
                cargStatusBadge.style.color = '#2196f3';
                cargStatusBadge.textContent = 'EN ESPERA (R)';
            } else {
                cargStatusBadge.textContent = 'NO EMITIDO';
            }
        }

        if (cargNumeroDOficialEl) {
            if (cargProc && cargNumOfic) {
                cargNumeroDOficialEl.textContent = `${cargNumOfic} (Oficial ERP)`;
                cargNumeroDOficialEl.style.color = 'var(--success)';
            } else {
                cargNumeroDOficialEl.textContent = cargEnviado ? 'Pendiente por procesar en Saint' : 'No generado';
                cargNumeroDOficialEl.style.color = 'var(--text-muted)';
            }
        }

        if (cargTipoFinalEl) {
            cargTipoFinalEl.textContent = cargProc ? `${cargTipoFinal} (Cargo de Inventario)` : (cargTipoFinal ? `${cargTipoFinal} (Errado)` : '-');
        }
        if (cargFechaEl) cargFechaEl.textContent = cargFecha;
    }

    async function fetchAndShowErpDocs(nroOrden) {
        if (!nroOrden) return;
        currentModalOrder = nroOrden;

        if (titleEl) titleEl.textContent = nroOrden;
        if (globalBadgeEl) {
            globalBadgeEl.className = 'badge';
            globalBadgeEl.textContent = 'SINCRONIZANDO CON ERP...';
        }
        if (alertEl) alertEl.style.display = 'none';

        if (modal) modal.style.display = 'flex';

        try {
            if (btnRefresh) {
                btnRefresh.disabled = true;
                btnRefresh.querySelector('span').textContent = 'Consultando...';
            }
            const res = await fetch(`/api/desposte/erp-status?nro_orden=${encodeURIComponent(nroOrden)}`);
            const json = await res.json();
            if (json.success && json.data) {
                renderModalData(json.data);
            } else {
                if (alertEl) {
                    alertEl.className = 'alert alert-danger';
                    alertEl.textContent = json.error || 'No se pudieron consultar los datos del cruce ERP.';
                    alertEl.style.display = 'block';
                }
            }
        } catch (e) {
            if (alertEl) {
                alertEl.className = 'alert alert-danger';
                alertEl.textContent = 'Error de conexión con el servidor.';
                alertEl.style.display = 'block';
            }
        } finally {
            if (btnRefresh) {
                btnRefresh.disabled = false;
                btnRefresh.querySelector('span').textContent = 'Verificar Estado ERP en Vivo';
            }
        }
    }

    // Exponer globalmente para la estación de balance
    window.openErpDocsModal = (nroOrden, preloadedData = null) => {
        if (preloadedData) {
            currentModalOrder = nroOrden;
            if (modal) modal.style.display = 'flex';
            renderModalData(preloadedData);
        }
        fetchAndShowErpDocs(nroOrden);
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-action="show-erp-docs"]');
            if (trigger) {
                e.preventDefault();
                const nro = trigger.dataset.order || (trigger.closest('[data-order]')?.dataset.order);
                if (nro) fetchAndShowErpDocs(nro);
                return;
            }

            const triggerStation = e.target.closest('[data-action="show-erp-docs-station"]');
            if (triggerStation) {
                e.preventDefault();
                if (window.currentOrder && window.currentOrder.NroOrdenDesposte) {
                    window.openErpDocsModal(window.currentOrder.NroOrdenDesposte, window.currentOrder);
                }
                return;
            }

            const closeBtn = e.target.closest('[data-action="close-modal-erp-docs"]');
            if (closeBtn) {
                e.preventDefault();
                closeModal();
                return;
            }
        });

        if (btnRefresh) {
            btnRefresh.addEventListener('click', () => {
                if (currentModalOrder) fetchAndShowErpDocs(currentModalOrder);
            });
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal && modal.style.display !== 'none') {
                closeModal();
            }
        });
    });
})();
