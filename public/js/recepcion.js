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

    async function mostrarDetallePiezas(nroRecepcion) {
        const modalEl = document.getElementById('modalDetallePiezas');
        const cont = document.getElementById('contenedorPiezasDetalle');
        const titulo = document.getElementById('tituloDetallePiezas');
        if (!modalEl || !cont) return;

        if (titulo) titulo.textContent = 'Detalle de Reses: ' + nroRecepcion;
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
                const badgeColor = p.Estado === 'DISPONIBLE' ? 'rgba(193, 218, 198, 0.15)' : 'rgba(238, 103, 55, 0.15)';
                const textColor = p.Estado === 'DISPONIBLE' ? 'var(--success)' : 'var(--danger)';
                const estadoTxt = p.Estado === 'DISPONIBLE' ? 'DISPONIBLE' : 'DESPOSTADO';

                html += `
                    <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-card); padding:0.6rem 0.85rem; border-radius:6px; border:1px solid var(--border-color);">
                        <div>
                            <span style="font-weight:700; color:var(--text-primary);">Res #${p.NroEspecie}</span>
                            <span style="font-size:0.75rem; color:var(--text-muted); margin-left:0.5rem;">[${p.CodProdCanal}]</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <span style="font-weight:700; font-family:monospace; color:var(--primary); font-size:1.05rem;">
                                ${parseFloat(p.PesoKg).toFixed(4)} kg
                            </span>
                            <span class="badge" style="background:${badgeColor}; color:${textColor}; font-size:0.75rem;">
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
