/**
 * ST-Despostadora - Sincronización en Tiempo Real Multi-Usuario (Smart Delta Polling)
 * Permite que varios operadores trabajen simultáneamente en la misma mesa sin desincronización.
 */

const DeboningSync = (() => {
    let timer = null;
    let isFetching = false;
    let consecutiveErrors = 0;
    let localVersion = 0;

    const INTERVAL_NORMAL = 2500; // 2.5 segundos cuando la pestaña está activa
    const INTERVAL_BACKGROUND = 10000; // 10 segundos en segundo plano

    function updateBadge(status, text) {
        const badge = document.getElementById('stationSyncBadge');
        if (!badge) return;

        badge.className = 'badge';
        if (status === 'online') {
            badge.style.background = 'rgba(46, 204, 113, 0.15)';
            badge.style.color = '#2ecc71';
            badge.textContent = text || '🟢 Sincronizado';
        } else if (status === 'syncing') {
            badge.style.background = 'rgba(241, 196, 15, 0.15)';
            badge.style.color = '#f1c40f';
            badge.textContent = text || '🟡 Sincronizando...';
        } else if (status === 'offline') {
            badge.style.background = 'rgba(231, 76, 60, 0.15)';
            badge.style.color = '#e74c3c';
            badge.textContent = text || '🔴 Sin Conexión';
        }
    }

    function showLiveToast(message, type = 'info') {
        const container = document.getElementById('deboningLiveToasts');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `live-toast live-toast-${type}`;
        toast.innerHTML = message;
        container.appendChild(toast);

        // Remover toast tras 4.5 segundos
        setTimeout(() => {
            toast.classList.add('fade-out');
            setTimeout(() => toast.remove(), 400);
        }, 4500);
    }

    function highlightCutRow(cutCode) {
        if (!cutCode) return;
        const codeTrim = String(cutCode).trim();
        document.querySelectorAll(`.cut-table-row[data-code="${codeTrim}"], .cut-item-card[data-code="${codeTrim}"]`).forEach(el => {
            el.classList.add('row-updated-flash');
            setTimeout(() => el.classList.remove('row-updated-flash'), 2500);
        });
    }

    function handleClosedOrderNotice(data) {
        stop();

        // Cerrar modales que pudieran estar abiertos
        if (typeof closeWeighModal === 'function') closeWeighModal();
        if (typeof closeCloseOrderModal === 'function') closeCloseOrderModal();
        const modalEdit = document.getElementById('modalEditCutWeight');
        if (modalEdit) modalEdit.style.display = 'none';
        const modalAudit = document.getElementById('modalDeboningAudit');
        if (modalAudit) modalAudit.style.display = 'none';

        // Deshabilitar botones de pesaje en estación
        const btnReg = document.getElementById('btnRegisterCut');
        if (btnReg) btnReg.disabled = true;
        const btnPrompt = document.getElementById('btnPromptCloseStation');
        if (btnPrompt) btnPrompt.style.display = 'none';

        const modalNotice = document.getElementById('modalOrderClosedNotice');
        if (modalNotice) {
            const isAnnulled = (data.estado === 'ANULADO');
            const titleEl = modalNotice.querySelector('h2');
            const descEl = document.getElementById('orderClosedNoticeText');
            if (titleEl) {
                titleEl.textContent = isAnnulled ? 'Orden de Desposte Anulada' : 'Orden de Desposte Finalizada';
            }
            if (descEl) {
                descEl.textContent = isAnnulled 
                    ? 'Esta orden fue anulada por otro usuario y su existencia fue restituida a la recepción.'
                    : 'Esta orden de desposte ha sido cerrada y finalizada por otro usuario en la mesa.';
            }

            const userEl = document.getElementById('closedNoticeUser');
            const timeEl = document.getElementById('closedNoticeTime');
            if (userEl) userEl.textContent = data.closed_by || 'Otro operador';
            if (timeEl) timeEl.textContent = data.closed_at ? data.closed_at.substring(11, 19) : new Date().toLocaleTimeString();

            modalNotice.style.display = 'flex';
            if (typeof playBeep === 'function') playBeep('error');

            let countdown = 4;
            const cdEl = document.getElementById('closedNoticeCountdown');
            const cdInterval = setInterval(() => {
                countdown--;
                if (cdEl) cdEl.textContent = countdown.toString();
                if (countdown <= 0) {
                    clearInterval(cdInterval);
                    modalNotice.style.display = 'none';
                    if (typeof switchView === 'function') switchView('board');
                }
            }, 1000);

            const btnRedirect = document.getElementById('btnRedirectClosedBoard');
            if (btnRedirect) {
                btnRedirect.onclick = () => {
                    clearInterval(cdInterval);
                    modalNotice.style.display = 'none';
                    if (typeof switchView === 'function') switchView('board');
                };
            }
        }
    }

    async function pollSync() {
        if (isFetching) return;
        if (!window.currentOrder || !window.currentOrder.NroOrdenDesposte) return;
        if (!document.body.classList.contains('station-active')) return;
        if (window.currentOrder.Estado === 'FINALIZADO' || window.currentOrder.Estado === 'ANULADO') return;

        if (!navigator.onLine) {
            updateBadge('offline');
            return;
        }

        const nroOrden = window.currentOrder.NroOrdenDesposte;
        const curVersion = localVersion || parseInt(window.currentOrder.Version || '1', 10);

        isFetching = true;
        try {
            const url = `/api/desposte/sync?nro_orden=${encodeURIComponent(nroOrden)}&version=${encodeURIComponent(curVersion)}`;
            const res = await fetch(url, { cache: 'no-store' });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const json = await res.json();
            if (!json.success) throw new Error(json.error || 'Error en sync');

            consecutiveErrors = 0;
            updateBadge('online');

            // 1. Detectar si la orden fue finalizada o anulada por otro usuario
            if (json.estado === 'FINALIZADO' || json.estado === 'ANULADO') {
                handleClosedOrderNotice(json);
                return;
            }

            // 2. Detectar cambios en cortes o pesajes
            if (json.has_changes && json.balance) {
                localVersion = json.version;
                window.currentOrder = json.balance.orden;

                // Re-renderizar la tabla y balance
                if (typeof renderBalanceAndTable === 'function') {
                    renderBalanceAndTable(json.balance);
                }

                // Generar notificación toast visual del último movimiento
                if (json.last_log) {
                    const l = json.last_log;
                    const autor = l.NombreUsuario || l.CodUsua || 'Operador';
                    const corte = l.NombreCorte || l.CodProdCorte || 'Corte';
                    let msg = '';

                    if (l.TipoAccion === 'PESAJE_INICIAL') {
                        msg = `⚖️ <strong>${autor}</strong> pesó <strong>${corte}</strong>: ${parseFloat(l.PesoNuevo).toFixed(4)} kg`;
                    } else if (l.TipoAccion === 'MODIFICACION_PESO') {
                        const deltaSign = l.DiferenciaPeso >= 0 ? `+${parseFloat(l.DiferenciaPeso).toFixed(4)}` : `${parseFloat(l.DiferenciaPeso).toFixed(4)}`;
                        msg = `✏️ <strong>${autor}</strong> ajustó <strong>${corte}</strong> a ${parseFloat(l.PesoNuevo).toFixed(4)} kg (Δ ${deltaSign} kg)`;
                    } else if (l.TipoAccion === 'ANULACION') {
                        msg = `🗑️ <strong>${autor}</strong> anuló pesaje de <strong>${corte}</strong>`;
                    }

                    if (msg) {
                        showLiveToast(msg, l.TipoAccion === 'ANULACION' ? 'warning' : 'success');
                        highlightCutRow(l.CodProdCorte);
                        if (typeof playBeep === 'function') playBeep('success');
                    }
                }
            } else if (json.version) {
                localVersion = json.version;
            }
        } catch (e) {
            consecutiveErrors++;
            if (consecutiveErrors >= 2) {
                updateBadge('offline', '🔴 Reconectando...');
            }
        } finally {
            isFetching = false;
        }
    }

    function start(initialVersion) {
        stop();
        if (initialVersion) localVersion = parseInt(initialVersion, 10);
        updateBadge('online');

        const getDelay = () => document.hidden ? INTERVAL_BACKGROUND : INTERVAL_NORMAL;

        const scheduleNext = () => {
            timer = setTimeout(async () => {
                await pollSync();
                if (window.currentOrder && window.currentOrder.Estado === 'EN_PROCESO') {
                    scheduleNext();
                }
            }, getDelay());
        };

        scheduleNext();
    }

    function stop() {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }
        isFetching = false;
    }

    function triggerNow() {
        if (timer) clearTimeout(timer);
        pollSync().then(() => {
            if (window.currentOrder && window.currentOrder.Estado === 'EN_PROCESO') {
                start(localVersion);
            }
        });
    }

    // Adaptación a pestaña visible / oculta
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && window.currentOrder && window.currentOrder.Estado === 'EN_PROCESO') {
            triggerNow();
        }
    });

    window.addEventListener('online', () => updateBadge('online'));
    window.addEventListener('offline', () => updateBadge('offline'));

    return {
        start,
        stop,
        triggerNow,
        showLiveToast,
        setVersion: (v) => { localVersion = parseInt(v, 10); },
        getVersion: () => localVersion
    };
})();
