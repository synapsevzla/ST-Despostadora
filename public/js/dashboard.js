/**
 * Dashboard Operativo - ST Despostadora
 * Actualización dinámica y ergonomía en sala de procesos
 */
(function () {
    'use strict';

    const refreshBtn = document.getElementById('dashboardRefreshBtn');
    const refreshIcon = document.getElementById('dashboardRefreshIcon');
    const lastUpdateSpan = document.getElementById('dashboardLastUpdate');

    let isFetching = false;

    function formatNumber(num, decimals = 2) {
        return Number(num).toLocaleString('es-VE', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
    }

    async function reloadDashboardData() {
        if (isFetching) return;
        isFetching = true;

        if (refreshIcon) {
            refreshIcon.style.transition = 'transform 0.6s ease';
            refreshIcon.style.transform = 'rotate(360deg)';
        }

        try {
            const res = await fetch('/api/dashboard/kpis', {
                headers: { 'Accept': 'application/json' },
            });

            if (!res.ok) {
                throw new Error('Error en el servidor al obtener KPIs');
            }

            const json = await res.json();
            if (!json.success || !json.data) {
                throw new Error(json.error || 'Respuesta inválida');
            }

            const data = json.data;

            // 1. Actualizar Kilos Despostados
            const elKilosDia = document.getElementById('valKilosDia');
            const elCortesDia = document.getElementById('valCortesDia');
            const elKilosSemana = document.getElementById('valKilosSemana');
            const elCortesSemana = document.getElementById('valCortesSemana');
            const elKilosMes = document.getElementById('valKilosMes');
            const elCortesMes = document.getElementById('valCortesMes');

            if (elKilosDia) elKilosDia.textContent = formatNumber(data.kilos_despostados.dia);
            if (elCortesDia) elCortesDia.textContent = data.kilos_despostados.cortes_dia + ' cortes';
            if (elKilosSemana) elKilosSemana.textContent = formatNumber(data.kilos_despostados.semana);
            if (elCortesSemana) elCortesSemana.textContent = data.kilos_despostados.cortes_semana + ' cortes';
            if (elKilosMes) elKilosMes.textContent = formatNumber(data.kilos_despostados.mes);
            if (elCortesMes) elCortesMes.textContent = data.kilos_despostados.cortes_mes + ' cortes';

            // 2. Actualizar Órdenes
            const elOrdAbiertas = document.getElementById('valOrdAbiertas');
            const elOrdProceso = document.getElementById('valOrdProceso');
            const elOrdTerminadas = document.getElementById('valOrdTerminadas');
            const elOrdTotal = document.getElementById('valOrdTotal');

            if (elOrdAbiertas) elOrdAbiertas.textContent = data.ordenes.abiertas;
            if (elOrdProceso) elOrdProceso.textContent = data.ordenes.en_proceso;
            if (elOrdTerminadas) elOrdTerminadas.textContent = data.ordenes.terminadas;
            if (elOrdTotal) elOrdTotal.textContent = data.ordenes.total;

            // 3. Timestamp
            if (lastUpdateSpan) {
                const now = new Date();
                lastUpdateSpan.textContent = now.toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }

        } catch (err) {
            console.error('[Dashboard Error]', err);
        } finally {
            isFetching = false;
            if (refreshIcon) {
                setTimeout(() => {
                    refreshIcon.style.transition = 'none';
                    refreshIcon.style.transform = 'rotate(0deg)';
                }, 600);
            }
        }
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', (e) => {
            e.preventDefault();
            reloadDashboardData();
        });
    }

    // Auto-refresco cada 60 segundos
    setInterval(reloadDashboardData, 60000);
})();
