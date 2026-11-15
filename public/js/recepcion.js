/**
 * ST-Despostadora - Historial de Recepciones
 * Gestión de consulta de piezas y detalle individual.
 * Cumplimiento estricto de Content Security Policy (cero inline handlers).
 */
document.addEventListener('DOMContentLoaded', () => {
    // Escuchar clics en "Ver Piezas"
    document.querySelectorAll('.btn-ver-piezas').forEach(btn => {
        btn.addEventListener('click', () => {
            const nrorec = btn.getAttribute('data-nrorec');
            if (nrorec) {
                mostrarDetallePiezas(nrorec);
            }
        });
    });

    // Cerrar modal de piezas
    document.querySelector('[data-action="cerrar-modal-detalle-piezas"]')?.addEventListener('click', cerrarModalDetalle);

    const modal = document.getElementById('modalDetallePiezas');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                cerrarModalDetalle();
            }
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    async function mostrarDetallePiezas(nroRecepcion) {
        const modalEl = document.getElementById('modalDetallePiezas');
        const cont = document.getElementById('contenedorPiezasDetalle');
        const titulo = document.getElementById('tituloDetallePiezas');
        if (!modalEl || !cont) return;

        if (titulo) titulo.textContent = 'Detalle de Reses: ' + nroRecepcion;
        const btnPrint = document.getElementById('btnImprimirModalPiezas');
        if (btnPrint) {
            btnPrint.href = '/recepcion/imprimir?nro=' + encodeURIComponent(nroRecepcion) + '&autoprint=1';
        }
        cont.innerHTML = '<div style="text-align:center; padding:1.5rem; color:var(--text-muted);">Cargando piezas recibidas...</div>';
        modalEl.style.display = 'flex';
        modalEl.classList.add('active');

        try {
            const resp = await fetch('/api/recepcion/detalle?nrorec=' + encodeURIComponent(nroRecepcion));
            const data = await resp.json();

            if (!data.success || !data.details || data.details.length === 0) {
                cont.innerHTML = '<div style="text-align:center; padding:1.5rem; color:var(--text-muted);">No se encontraron piezas registradas para esta recepción.</div>';
                return;
            }

            let html = '';
            data.details.forEach(p => {
                const estado = String(p.EstadoDesposte || p.Estado || 'DISPONIBLE').toUpperCase();
                let badgeBg = 'rgba(193, 218, 198, 0.15)';
                let badgeColor = 'var(--success)';
                let estadoTxt = 'DISPONIBLE';
                let ordenInfo = '';

                if (estado === 'EN_DESPOSTE' || estado === 'EN_PROCESO') {
                    badgeBg = 'rgba(216, 134, 94, 0.15)';
                    badgeColor = 'var(--warning, #f59e0b)';
                    estadoTxt = 'EN CORTE';
                    if (p.NroOrdenDesposte) {
                        ordenInfo = `<span style="font-size:0.75rem; color:var(--warning, #f59e0b); display:block; font-family:monospace; margin-top:2px;">Orden: ${escapeHtml(p.NroOrdenDesposte)}</span>`;
                    }
                } else if (estado === 'DESPOSTADO' || estado === 'FINALIZADO') {
                    badgeBg = 'rgba(140, 115, 101, 0.2)';
                    badgeColor = 'var(--text-muted)';
                    estadoTxt = 'DESPOSTADO';
                    if (p.NroOrdenDesposte) {
                        ordenInfo = `<span style="font-size:0.75rem; color:var(--text-muted); display:block; font-family:monospace; margin-top:2px;">Orden: ${escapeHtml(p.NroOrdenDesposte)}</span>`;
                    }
                }

                const pesoVal = (p.PesoNeto !== undefined && p.PesoNeto !== null) ? p.PesoNeto : (p.PesoKg !== undefined ? p.PesoKg : 0);
                const pesoNum = parseFloat(pesoVal);
                const pesoStr = isNaN(pesoNum) ? '0.0000' : pesoNum.toFixed(4);
                const canalLabel = p.CodProdCanal ? `<span style="font-size:0.75rem; color:var(--text-muted); margin-left:0.5rem;">[${escapeHtml(p.CodProdCanal)}]</span>` : '';

                html += `
                    <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-card); padding:0.65rem 0.85rem; border-radius:6px; border:1px solid var(--border-color);">
                        <div>
                            <div style="display:flex; align-items:center;">
                                <span style="font-weight:700; color:var(--text-primary);">Res #${escapeHtml(p.NroEspecie)}</span>
                                ${canalLabel}
                            </div>
                            ${ordenInfo}
                        </div>
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <span style="font-weight:700; font-family:monospace; color:var(--primary); font-size:1.05rem;">
                                ${pesoStr} kg
                            </span>
                            <span class="badge" style="background:${badgeBg}; color:${badgeColor}; font-size:0.75rem; font-weight:700;">
                                ${estadoTxt}
                            </span>
                        </div>
                    </div>
                `;
            });

            cont.innerHTML = html;
        } catch (err) {
            cont.innerHTML = `<div class="alert alert-error" style="text-align:center; padding:1rem; margin:1rem;">Base de datos o servidor fuera de línea por favor contacte a soporte técnico</div>`;
        }
    }

    function cerrarModalDetalle() {
        const modalEl = document.getElementById('modalDetallePiezas');
        if (!modalEl) return;
        modalEl.style.display = 'none';
        modalEl.classList.remove('active');
    }
});
