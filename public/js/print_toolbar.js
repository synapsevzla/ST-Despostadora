/**
 * Script de barra de herramientas de impresión para formatos Carta
 * Soluciones Totales Despostadora - Cumplimiento estricto de Content-Security-Policy (CSP)
 */
document.addEventListener('DOMContentLoaded', () => {
    const btnPrint = document.getElementById('btnPrintDocument');
    if (btnPrint) {
        btnPrint.addEventListener('click', (e) => {
            e.preventDefault();
            window.print();
        });
    }

    const btnClose = document.getElementById('btnCloseWindow');
    if (btnClose) {
        btnClose.addEventListener('click', (e) => {
            e.preventDefault();
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.close();
            }
        });
    }

    // Disparar autoprint si viene ?autoprint=1 en la URL
    try {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('autoprint') === '1') {
            setTimeout(() => {
                window.print();
            }, 350);
        }
    } catch (_) {
        // Ignorar si el navegador no soporta URLSearchParams
    }
});
