/**
 * Soluciones Totales Despostadora - Vanilla Client JS
 * Control de menú lateral auto-ocultable, toasts, confirmaciones modales y eventos de interfaz
 */
document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initClock();
    initDecimalInputs();
    initLicenseMonitor();
});

/**
 * Sistema Global de Notificaciones Toast (No bloqueante, sin pérdida de foco)
 */
const AppToast = (() => {
    function getContainer() {
        let container = document.getElementById('st-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'st-toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    function getIcon(type) {
        if (type === 'success') {
            return `<svg class="st-toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>`;
        }
        if (type === 'error' || type === 'danger') {
            return `<svg class="st-toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>`;
        }
        if (type === 'warning') {
            return `<svg class="st-toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>`;
        }
        return `<svg class="st-toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>`;
    }

    function show(message, type = 'info', duration = 3500) {
        if (!message) return;
        const container = getContainer();
        const toast = document.createElement('div');
        toast.className = `st-toast st-toast-${type}`;

        toast.innerHTML = `
            ${getIcon(type)}
            <div class="st-toast-body">${message}</div>
            <button type="button" class="st-toast-close" title="Cerrar">&times;</button>
        `;

        function removeToast() {
            if (toast.classList.contains('st-toast-hiding')) return;
            toast.classList.add('st-toast-hiding');
            setTimeout(() => {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 200);
        }

        const timer = setTimeout(removeToast, duration);

        toast.addEventListener('click', () => {
            clearTimeout(timer);
            removeToast();
        });

        container.appendChild(toast);
        return toast;
    }

    return {
        show,
        success: (msg, dur) => show(msg, 'success', dur || 3500),
        error: (msg, dur) => show(msg, 'error', dur || 4500),
        warning: (msg, dur) => show(msg, 'warning', dur || 4000),
        info: (msg, dur) => show(msg, 'info', dur || 3500)
    };
})();

/**
 * Diálogo Modal de Confirmación Global Asíncrono (Reemplazo de window.confirm)
 */
const AppModal = (() => {
    let activeResolve = null;
    let prevActiveElement = null;

    function ensureModal() {
        let modal = document.getElementById('stGlobalConfirmModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'stGlobalConfirmModal';
            modal.innerHTML = `
                <div id="stGlobalConfirmCard">
                    <div class="st-modal-header">
                        <h4 id="stGlobalConfirmTitle" class="st-modal-title">Confirmar Acción</h4>
                        <button type="button" class="st-toast-close" id="stGlobalConfirmClose">&times;</button>
                    </div>
                    <div id="stGlobalConfirmBody" class="st-modal-body"></div>
                    <div class="st-modal-footer">
                        <button type="button" class="btn btn-outline" id="stGlobalConfirmCancel">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="stGlobalConfirmOk">Confirmar</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            const handleClose = (result) => {
                modal.style.display = 'none';
                if (activeResolve) {
                    const cb = activeResolve;
                    activeResolve = null;
                    cb(result);
                }
                if (prevActiveElement && typeof prevActiveElement.focus === 'function') {
                    try { prevActiveElement.focus(); } catch (e) {}
                }
                // Si estamos en Electron, asegurar reactivación de foco
                if (window.electronAPI && typeof window.electronAPI.restoreFocus === 'function') {
                    window.electronAPI.restoreFocus();
                }
            };

            document.getElementById('stGlobalConfirmOk')?.addEventListener('click', () => handleClose(true));
            document.getElementById('stGlobalConfirmCancel')?.addEventListener('click', () => handleClose(false));
            document.getElementById('stGlobalConfirmClose')?.addEventListener('click', () => handleClose(false));

            modal.addEventListener('click', (e) => {
                if (e.target === modal) handleClose(false);
            });

            document.addEventListener('keydown', (e) => {
                if (modal.style.display === 'flex') {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        handleClose(false);
                    }
                }
            });
        }
        return modal;
    }

    function confirm(message, options = {}) {
        return new Promise((resolve) => {
            prevActiveElement = document.activeElement;
            activeResolve = resolve;
            const modal = ensureModal();
            const titleEl = document.getElementById('stGlobalConfirmTitle');
            const bodyEl = document.getElementById('stGlobalConfirmBody');
            const okBtn = document.getElementById('stGlobalConfirmOk');
            const cancelBtn = document.getElementById('stGlobalConfirmCancel');

            if (titleEl) titleEl.textContent = options.title || 'Confirmar Acción';
            if (bodyEl) bodyEl.textContent = message || '¿Desea continuar con esta operación?';
            if (okBtn) {
                okBtn.textContent = options.confirmText || 'Confirmar';
                okBtn.className = options.danger ? 'btn btn-danger' : 'btn btn-primary';
            }
            if (cancelBtn) {
                cancelBtn.textContent = options.cancelText || 'Cancelar';
            }

            modal.style.display = 'flex';
            setTimeout(() => {
                if (options.danger && cancelBtn) cancelBtn.focus();
                else if (okBtn) okBtn.focus();
            }, 30);
        });
    }

    return { confirm };
})();

// Exponer en window
window.AppToast = AppToast;
window.AppModal = AppModal;

// Reemplazo transparente y seguro de window.alert para evitar bloqueo de foco
window.alert = function(msg) {
    AppToast.info(msg);
};

function initClock() {
    const clockEl = document.getElementById('liveClock');
    if (!clockEl) return;

    function update() {
        const now = new Date();
        const formattedDate = now.toLocaleDateString('es-ES', {
            weekday: 'short',
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
        const formattedTime = now.toLocaleTimeString('es-ES', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        });
        clockEl.textContent = `${formattedDate} • ${formattedTime}`;
    }

    update();
    setInterval(update, 1000);
}

function initSidebar() {
    const sidebar = document.getElementById('appSidebar');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const closeBtn = document.getElementById('sidebarCloseBtn');
    const overlay = document.getElementById('sidebarOverlay');
    const autoHideDuration = 5000; // 5 segundos
    let autoHideTimer = null;

    if (!sidebar) return;

    // Abrir menú lateral
    function openSidebar() {
        sidebar.classList.remove('collapsed');
        if (overlay) overlay.classList.add('active');
        resetAutoHideTimer();
    }

    // Cerrar / Esconder menú lateral
    function closeSidebar() {
        sidebar.classList.add('collapsed');
        if (overlay) overlay.classList.remove('active');
        clearTimeout(autoHideTimer);
    }

    // Temporizador de auto-cierre
    function resetAutoHideTimer() {
        clearTimeout(autoHideTimer);
        // Solo auto-ocultar si el sidebar está visible
        if (!sidebar.classList.contains('collapsed')) {
            autoHideTimer = setTimeout(() => {
                closeSidebar();
            }, autoHideDuration);
        }
    }

    // Toggle al presionar el botón hamburguesa
    if (toggleBtn) {
        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (sidebar.classList.contains('collapsed')) {
                openSidebar();
            } else {
                closeSidebar();
            }
        });
    }

    // Botón cerrar dentro del menú
    if (closeBtn) {
        closeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            closeSidebar();
        });
    }

    // Cerrar al dar click al overlay
    if (overlay) {
        overlay.addEventListener('click', () => {
            closeSidebar();
        });
    }

    // Pausar temporizador si el usuario está interactuando con el menú
    sidebar.addEventListener('mouseenter', () => {
        clearTimeout(autoHideTimer);
    });

    sidebar.addEventListener('mouseleave', () => {
        resetAutoHideTimer();
    });

    sidebar.addEventListener('touchstart', () => {
        clearTimeout(autoHideTimer);
    }, { passive: true });

    sidebar.addEventListener('touchend', () => {
        resetAutoHideTimer();
    }, { passive: true });

    // Cerrar menú automáticamente al cambiar de página o hacer click en cualquier link de navegación
    const navLinks = sidebar.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            closeSidebar();
        });
    });

    // Iniciar con temporizador de auto-ocultado si arranca visible
    if (!sidebar.classList.contains('collapsed')) {
        resetAutoHideTimer();
    }
}

/**
 * Control global para campos decimales:
 * - Restringe la entrada a máximo 4 decimales en tiempo real (teclado, pegado o input).
 * - Aplica a todos los inputs numéricos con step decimal o clase decimal-input.
 */
function initDecimalInputs() {
    const isDecimalInput = (el) => {
        if (!el || el.tagName !== 'INPUT') return false;
        if (el.classList && el.classList.contains('decimal-input')) return true;
        if (el.type === 'number') {
            const step = el.getAttribute('step');
            return step && (step === 'any' || step.includes('.') || parseFloat(step) < 1);
        }
        return false;
    };

    // Control al escribir o pegar
    document.addEventListener('input', (e) => {
        const target = e.target;
        if (!isDecimalInput(target)) return;

        let val = target.value;
        if (val && val.includes('.')) {
            const parts = val.split('.');
            if (parts[1] && parts[1].length > 4) {
                target.value = parts[0] + '.' + parts[1].substring(0, 4);
            }
        }
    });

    // Control preventivo en keydown
    document.addEventListener('keydown', (e) => {
        const target = e.target;
        if (!isDecimalInput(target)) return;

        // Permitir teclas especiales y atajos
        if (['Backspace', 'Tab', 'Enter', 'Escape', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Delete', 'Home', 'End'].includes(e.key) ||
            e.ctrlKey || e.metaKey || e.altKey) {
            return;
        }

        // Si se presiona un número y el cursor está después del punto
        if (/^[0-9]$/.test(e.key)) {
            const val = target.value;
            const dotIndex = val.indexOf('.');
            if (dotIndex !== -1) {
                const selStart = target.selectionStart;
                const selEnd = target.selectionEnd;
                // Si no hay texto seleccionado para sobrescribir y estamos tras el punto decimal
                if (selStart !== null && selStart === selEnd && selStart > dotIndex) {
                    const decimals = val.slice(dotIndex + 1);
                    if (decimals.length >= 4) {
                        e.preventDefault();
                    }
                }
            }
        }
    });
}

/**
 * Monitor periódico de estado de licencia (Chequeo cada 24 horas para instancias activas 24/7)
 */
function initLicenseMonitor() {
    const INTERVAL_24H = 24 * 60 * 60 * 1000;
    async function checkStatus() {
        try {
            const res = await fetch('/api/licencia/status', {
                headers: { 'Accept': 'application/json' }
            });
            if (res.status === 403) {
                window.location.reload();
                return;
            }
            if (res.ok) {
                const data = await res.json();
                if (data && data.valid === false) {
                    window.location.reload();
                }
            }
        } catch (e) {
            // Ignorar errores de red para no interrumpir modo offline
        }
    }
    setInterval(checkStatus, INTERVAL_24H);
}
