/**
 * Preload Script de Electron
 * 1. Expone electronAPI de forma segura con contextBridge
 * 2. Inyecta la Barra de Título personalizada en la aplicación web sin alterar el código fuente PHP
 */
const { contextBridge, ipcRenderer } = require('electron');

// 1. Exponer API para el Asistente (Wizard) y la Barra de Título
contextBridge.exposeInMainWorld('electronAPI', {
    minimize: () => ipcRenderer.send('window-minimize'),
    maximize: () => ipcRenderer.send('window-maximize'),
    close: () => ipcRenderer.send('window-close'),
    testDb: (data) => ipcRenderer.invoke('test-db-connection', data),
    saveDb: (data) => ipcRenderer.invoke('save-db-config', data)
});

// 2. Inyección de Barra de Título personalizada en la aplicación web
window.addEventListener('DOMContentLoaded', () => {
    // Si estamos en la ventana de configuración del Wizard, no inyectar la barra de la app
    if (document.querySelector('.wizard-titlebar') || document.getElementById('setupForm')) {
        return;
    }

    if (document.getElementById('st-custom-titlebar')) {
        return;
    }

    // Inyectar estilos de la barra de título
    const style = document.createElement('style');
    style.id = 'st-titlebar-styles';
    style.textContent = `
        body {
            padding-top: 34px !important;
        }
        #st-custom-titlebar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            height: 34px !important;
            background-color: #1a1e29 !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            z-index: 9999999 !important;
            user-select: none !important;
            -webkit-app-region: drag !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
        }
        .st-tb-brand {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            padding-left: 12px !important;
        }
        .st-tb-logo {
            width: 18px !important;
            height: 18px !important;
            display: flex !important;
            align-items: center !important;
        }
        .st-tb-logo svg {
            width: 100% !important;
            height: 100% !important;
        }
        .st-tb-title {
            font-size: 12px !important;
            font-weight: 600 !important;
            color: #f3f4f6 !important;
            letter-spacing: 0.3px !important;
        }
        .st-tb-controls {
            display: flex !important;
            -webkit-app-region: no-drag !important;
        }
        .st-tb-btn {
            width: 44px !important;
            height: 34px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            background: transparent !important;
            border: none !important;
            color: #9ca3af !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: background 0.15s, color 0.15s !important;
        }
        .st-tb-btn:hover {
            background: rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
        }
        .st-tb-btn.st-tb-close:hover {
            background: #dc2626 !important;
            color: #ffffff !important;
        }
    `;
    document.head.appendChild(style);

    // Crear elemento de la barra de título
    const titlebar = document.createElement('div');
    titlebar.id = 'st-custom-titlebar';
    titlebar.innerHTML = `
        <div class="st-tb-brand">
            <div class="st-tb-logo">
                <svg viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                    <polyline points="2 17 12 22 22 17"></polyline>
                    <polyline points="2 12 12 17 22 12"></polyline>
                </svg>
            </div>
            <span class="st-tb-title">ST Despostadora - Soluciones Totales</span>
        </div>
        <div class="st-tb-controls">
            <button id="stMinBtn" class="st-tb-btn" title="Minimizar">─</button>
            <button id="stMaxBtn" class="st-tb-btn" title="Maximizar / Restaurar">□</button>
            <button id="stCloseBtn" class="st-tb-btn st-tb-close" title="Cerrar">✕</button>
        </div>
    `;

    document.body.prepend(titlebar);

    // Conectar eventos IPC
    document.getElementById('stMinBtn')?.addEventListener('click', () => ipcRenderer.send('window-minimize'));
    document.getElementById('stMaxBtn')?.addEventListener('click', () => ipcRenderer.send('window-maximize'));
    document.getElementById('stCloseBtn')?.addEventListener('click', () => ipcRenderer.send('window-close'));
});
