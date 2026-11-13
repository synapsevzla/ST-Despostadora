/**
 * Soluciones Totales Despostadora - Vanilla Client JS
 * Control de menú lateral auto-ocultable y eventos de interfaz
 */
document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initClock();
    initDecimalInputs();
});

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
