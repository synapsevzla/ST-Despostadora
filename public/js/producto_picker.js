/**
 * ST-Despostadora - Modal de Búsqueda Avanzada y Selector de Productos con Lazy Load
 * Vanilla ES6+, 100% compliant con Content-Security-Policy.
 */

(() => {
    'use strict';

    const byId = id => document.getElementById(id);
    const escapeHtml = str => (!str ? '' : String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])));

    let currentPage = 1;
    let isLoading = false;
    let hasMore = true;
    let searchQuery = '';
    let selectedInstancia = '';
    let currentExclude = '';
    let currentOptions = null;
    let debounceTimer = null;
    let currentAbortCtrl = null;

    function abrirModalBusqueda(options = {}) {
        currentOptions = options;
        currentPage = 1;
        hasMore = true;
        searchQuery = '';
        selectedInstancia = '';
        currentExclude = options.excludeCodProd || '';

        const modal = byId('modalBusquedaProducto');
        const input = byId('modalBusquedaInput');
        const selInst = byId('modalBusquedaInstancia');
        const titulo = byId('modalBusquedaTitulo');
        const subtitulo = byId('modalBusquedaSubtitulo');

        if (titulo) titulo.textContent = options.title || 'Búsqueda Avanzada de Productos';
        if (subtitulo) subtitulo.textContent = options.subtitle || 'Catálogo general ERP (SAPROD) - Desplace para cargar más';
        if (input) input.value = '';
        if (selInst) selInst.value = '';

        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('active');
        }

        setTimeout(() => input?.focus(), 50);
        fetchProductos(1, true);
    }

    function cerrarModalBusqueda() {
        if (currentAbortCtrl) {
            currentAbortCtrl.abort();
            currentAbortCtrl = null;
        }
        const modal = byId('modalBusquedaProducto');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
        }
        currentOptions = null;
        isLoading = false;
    }

    async function fetchProductos(page = 1, reset = false) {
        if (isLoading) return;
        if (!hasMore && !reset) return;

        isLoading = true;
        const spinner = byId('modalBusquedaSpinner');
        const fin = byId('modalBusquedaFin');
        const vacio = byId('modalBusquedaVacio');
        const lista = byId('modalBusquedaLista');
        const totalEl = byId('modalBusquedaTotal');
        const contScroll = byId('modalBusquedaResultados');

        if (spinner) spinner.style.display = 'block';
        if (fin) fin.style.display = 'none';
        if (vacio) vacio.style.display = 'none';

        if (reset) {
            if (lista) lista.innerHTML = '';
            if (contScroll) contScroll.scrollTop = 0;
            if (totalEl) totalEl.textContent = 'Buscando productos...';
        }

        if (currentAbortCtrl) currentAbortCtrl.abort();
        currentAbortCtrl = new AbortController();

        try {
            const params = new URLSearchParams({
                page: String(page),
                limit: '50',
                q: searchQuery,
                codinst: selectedInstancia,
                exclude: currentExclude
            });

            const res = await fetch(`/api/config/productos?${params.toString()}`, {
                signal: currentAbortCtrl.signal
            });
            const data = await res.json();

            isLoading = false;
            if (spinner) spinner.style.display = 'none';

            if (!data.success) {
                if (vacio) {
                    vacio.style.display = 'block';
                    vacio.textContent = data.error || 'Base de datos o servidor fuera de línea por favor contacte a soporte técnico';
                }
                if (totalEl) totalEl.textContent = 'Servicio fuera de línea';
                return;
            }

            const products = data.products || [];
            hasMore = !!data.has_more;

            if (reset && products.length === 0) {
                if (vacio) vacio.style.display = 'block';
                if (totalEl) totalEl.textContent = '0 productos encontrados';
                actualizarPaginacionInfo(0, 0);
                return;
            }

            if (totalEl) {
                totalEl.textContent = `${data.total.toLocaleString()} producto(s) encontrado(s)`;
            }

            renderProductos(products, reset);

            const totalMostrados = lista ? lista.querySelectorAll('.producto-item-card').length : 0;
            actualizarPaginacionInfo(totalMostrados, data.total);

            if (!hasMore && totalMostrados > 0 && fin) {
                fin.style.display = 'block';
                fin.textContent = `✓ Mostrando los ${totalMostrados} productos disponibles.`;
            }
        } catch (err) {
            if (err.name === 'AbortError') return;
            isLoading = false;
            if (spinner) spinner.style.display = 'none';
            if (vacio) {
                vacio.style.display = 'block';
                vacio.textContent = 'Base de datos o servidor fuera de línea por favor contacte a soporte técnico';
            }
            if (totalEl) totalEl.textContent = 'Servicio fuera de línea';
            console.error('Error fetching productos:', err);
        }
    }

    function renderProductos(products, reset) {
        const lista = byId('modalBusquedaLista');
        if (!lista) return;

        let fragment = document.createDocumentFragment();
        products.forEach(p => {
            const div = document.createElement('div');
            div.className = 'producto-item-card';
            div.dataset.codprod = p.CodProd || '';
            div.dataset.nombre = p.DescripcionCompleta || p.Descrip || '';
            div.dataset.unidad = p.Unidad || '';
            div.dataset.costo = String(p.CostAct || 0);
            div.dataset.precio = String(p.Precio1 || 0);
            div.dataset.instancia = p.Instancia || '';

            div.innerHTML = `
                <div class="producto-item-top">
                    <span class="producto-item-cod">[${escapeHtml(p.CodProd)}]</span>
                    ${p.Instancia ? `<span class="producto-item-instancia">${escapeHtml(p.Instancia)}</span>` : ''}
                </div>
                <div class="producto-item-title">${escapeHtml(p.DescripcionCompleta || p.Descrip)}</div>
                <div class="producto-item-bottom">
                    <span>Unidad: <strong>${escapeHtml(p.Unidad || 'UNID')}</strong></span>
                    <span>Costo: <strong>$${Number(p.CostAct || 0).toFixed(2)}</strong></span>
                    <span>Precio: <strong>$${Number(p.Precio1 || 0).toFixed(2)}</strong></span>
                    <span class="producto-item-sel-action">Seleccionar &rarr;</span>
                </div>
            `;
            fragment.appendChild(div);
        });

        if (reset) lista.innerHTML = '';
        lista.appendChild(fragment);
    }

    function actualizarPaginacionInfo(mostrados, total) {
        const info = byId('modalBusquedaPaginacionInfo');
        if (info) {
            info.textContent = `Mostrando ${mostrados} de ${total} productos`;
        }
    }

    // Funciones utilitarias para actualizar el estado del selector visual
    function setProductoSeleccionado(tipo, prod) {
        const hiddenInput = byId(
            tipo === 'canal' ? 'sel_codprod' :
            tipo === 'merma' ? 'sel_codprod_merma' : 'modal_sel_corte'
        );
        if (hiddenInput) hiddenInput.value = prod.codprod || '';

        const ph = byId(`display_${tipo}_placeholder`);
        const sel = byId(`display_${tipo}_selected`);
        const cod = byId(`display_${tipo}_cod`);
        const nom = byId(`display_${tipo}_nombre`);
        const ext = byId(`display_${tipo}_extra`);

        if (ph) ph.style.display = 'none';
        if (sel) sel.style.display = 'flex';
        if (cod) cod.textContent = `[${prod.codprod}]`;
        if (nom) nom.textContent = prod.nombre || prod.codprod;
        if (ext) {
            const partes = [];
            if (prod.unidad) partes.push(prod.unidad);
            if (prod.costo && (tipo === 'canal' || tipo === 'corte')) {
                partes.push(`Costo: $${Number(prod.costo).toFixed(2)}`);
            }
            ext.textContent = partes.length ? `(${partes.join(' - ')})` : '';
        }
    }

    function limpiarProductoSeleccionado(tipo) {
        const hiddenInput = byId(
            tipo === 'canal' ? 'sel_codprod' :
            tipo === 'merma' ? 'sel_codprod_merma' : 'modal_sel_corte'
        );
        if (hiddenInput) hiddenInput.value = '';

        const ph = byId(`display_${tipo}_placeholder`);
        const sel = byId(`display_${tipo}_selected`);
        const cod = byId(`display_${tipo}_cod`);
        const nom = byId(`display_${tipo}_nombre`);
        const ext = byId(`display_${tipo}_extra`);

        if (ph) ph.style.display = 'flex';
        if (sel) sel.style.display = 'none';
        if (cod) cod.textContent = '';
        if (nom) nom.textContent = '';
        if (ext) ext.textContent = '';
    }

    function bloquearProductoPicker(tipo, bloquear = true) {
        const picker = byId(`picker_${tipo}`);
        const clearBtn = byId(`btn_clear_${tipo}`);
        const cambiarBtn = byId(`btn_cambiar_${tipo}`);

        if (picker) {
            if (bloquear) picker.classList.add('disabled');
            else picker.classList.remove('disabled');
        }
        if (clearBtn) clearBtn.style.display = bloquear ? 'none' : 'inline-block';
        if (cambiarBtn) cambiarBtn.style.display = bloquear ? 'none' : 'inline-block';
    }

    // Inicialización de Eventos del Modal
    document.addEventListener('DOMContentLoaded', () => {
        const modal = byId('modalBusquedaProducto');
        const contScroll = byId('modalBusquedaResultados');
        const input = byId('modalBusquedaInput');
        const selInst = byId('modalBusquedaInstancia');

        // Scroll infinito con Lazy Load al llegar al fondo
        if (contScroll) {
            contScroll.addEventListener('scroll', () => {
                if (isLoading || !hasMore) return;
                if (contScroll.scrollTop + contScroll.clientHeight >= contScroll.scrollHeight - 80) {
                    currentPage++;
                    fetchProductos(currentPage, false);
                }
            });
        }

        // Búsqueda en tiempo real con debounce
        if (input) {
            input.addEventListener('input', (e) => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    searchQuery = e.target.value.trim();
                    currentPage = 1;
                    hasMore = true;
                    fetchProductos(1, true);
                }, 280);
            });
        }

        // Cambio de categoría / instancia
        if (selInst) {
            selInst.addEventListener('change', (e) => {
                selectedInstancia = e.target.value;
                currentPage = 1;
                hasMore = true;
                fetchProductos(1, true);
            });
        }

        // Selección de producto en la lista
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target.closest('[data-action="cerrar-modal-busqueda"]') || e.target === modal) {
                    cerrarModalBusqueda();
                    return;
                }

                const card = e.target.closest('.producto-item-card');
                if (card && currentOptions && typeof currentOptions.onSelect === 'function') {
                    const prod = {
                        codprod: card.dataset.codprod,
                        nombre: card.dataset.nombre,
                        unidad: card.dataset.unidad,
                        costo: parseFloat(card.dataset.costo || '0'),
                        precio: parseFloat(card.dataset.precio || '0'),
                        instancia: card.dataset.instancia
                    };
                    currentOptions.onSelect(prod);
                    cerrarModalBusqueda();
                }
            });
        }

        // Tecla ESC para cerrar modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal && modal.classList.contains('active')) {
                cerrarModalBusqueda();
            }
        });
    });

    // Exponer API globalmente para ser usada por configuracion.js
    window.ProductoPicker = {
        abrir: abrirModalBusqueda,
        cerrar: cerrarModalBusqueda,
        setSeleccionado: setProductoSeleccionado,
        limpiar: limpiarProductoSeleccionado,
        bloquear: bloquearProductoPicker
    };
})();
