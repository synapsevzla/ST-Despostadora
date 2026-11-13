/**
 * Soluciones Totales Despostadora - Vanilla Client JS
 * Control de menú lateral auto-ocultable y eventos de interfaz
 */
document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initClock();
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
