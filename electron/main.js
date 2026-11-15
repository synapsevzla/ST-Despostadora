/**
 * Proceso Principal de Electron (main.js)
 * Maneja el ciclo de vida, detección de .env, servidor PHP embebido y ventanas.
 */
const { app, BrowserWindow, ipcMain, dialog } = require('electron');
const path = require('path');
const configManager = require('./configManager');
const phpManager = require('./phpManager');
const { getAvailablePort } = require('./portFinder');

let mainWindow = null;
let setupWindow = null;
let appPort = null;

// Garantizar una única instancia de la aplicación
const gotTheLock = app.requestSingleInstanceLock();
if (!gotTheLock) {
    app.quit();
} else {
    app.on('second-instance', () => {
        if (mainWindow) {
            if (mainWindow.isMinimized()) mainWindow.restore();
            mainWindow.focus();
        } else if (setupWindow) {
            if (setupWindow.isMinimized()) setupWindow.restore();
            setupWindow.focus();
        }
    });
}

function createSetupWindow() {
    if (setupWindow) return;

    setupWindow = new BrowserWindow({
        width: 640,
        height: 640,
        resizable: false,
        maximizable: false,
        frame: false,
        backgroundColor: '#141720',
        center: true,
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            contextIsolation: true,
            nodeIntegration: false
        }
    });

    setupWindow.loadFile(path.join(__dirname, 'wizard', 'setup.html'));

    setupWindow.on('closed', () => {
        setupWindow = null;
        // Si se cierra el asistente sin haber configurado y no hay mainWindow, salir
        if (!mainWindow) {
            app.quit();
        }
    });
}

async function createMainWindow(port) {
    mainWindow = new BrowserWindow({
        width: 1280,
        height: 800,
        minWidth: 1024,
        minHeight: 680,
        frame: false,
        backgroundColor: '#141720',
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            contextIsolation: true,
            nodeIntegration: false
        }
    });

    const targetUrl = `http://127.0.0.1:${port}/login`;
    console.log(`[MAIN] Cargando aplicación web en: ${targetUrl}`);
    mainWindow.loadURL(targetUrl);

    // Restauración y garantía de foco de teclado en ventanas sin marco (frameless)
    mainWindow.webContents.on('did-finish-load', () => {
        if (mainWindow) {
            mainWindow.focus();
            mainWindow.webContents.focus();
        }
    });

    mainWindow.on('focus', () => {
        if (mainWindow && mainWindow.webContents) {
            mainWindow.webContents.focus();
        }
    });

    mainWindow.on('closed', () => {
        mainWindow = null;
    });
}

async function startMainApp() {
    try {
        appPort = await getAvailablePort(8088);
        console.log(`[MAIN] Puerto TCP libre asignado: ${appPort}`);

        const envConfig = configManager.getConfig();
        const envPath = configManager.findEnvPath();
        phpManager.start(appPort, { ...envConfig, ENV_PATH: envPath || '' });

        const isReady = await phpManager.waitForReady();
        if (!isReady) {
            console.warn('[MAIN] Servidor PHP tardó en responder, intentando abrir ventana...');
        }

        await createMainWindow(appPort);
    } catch (err) {
        console.error('[MAIN ERROR] Error al iniciar la aplicación:', err);
        dialog.showErrorBox(
            'Error al Iniciar ST Despostadora',
            `No se pudo iniciar el servidor interno:\n${err.message}`
        );
        app.quit();
    }
}

// Inicialización de la aplicación
app.whenReady().then(async () => {
    // 1. Manejadores IPC de Controles de Ventana
    ipcMain.on('window-minimize', (event) => {
        const win = BrowserWindow.fromWebContents(event.sender);
        if (win) win.minimize();
    });

    ipcMain.on('window-maximize', (event) => {
        const win = BrowserWindow.fromWebContents(event.sender);
        if (win) {
            if (win.isMaximized()) {
                win.unmaximize();
            } else {
                win.maximize();
            }
        }
    });

    ipcMain.on('window-close', (event) => {
        const win = BrowserWindow.fromWebContents(event.sender);
        if (win) win.close();
    });

    // Manejador IPC para restaurar foco de teclado en ventanas sin marco (frameless)
    ipcMain.on('restore-focus', (event) => {
        const win = BrowserWindow.fromWebContents(event.sender);
        if (win) {
            if (process.platform === 'win32') {
                win.blur();
                win.focus();
            }
            if (win.webContents) {
                win.webContents.focus();
            }
        }
    });

    // 2. Manejadores IPC para Configuración de Base de Datos
    ipcMain.handle('test-db-connection', async (_, data) => {
        return await phpManager.testDbConnection(data);
    });

    ipcMain.handle('save-db-config', async (_, data) => {
        try {
            configManager.saveConfig(data);

            // Iniciar aplicación principal
            if (setupWindow) {
                setupWindow.destroy();
                setupWindow = null;
            }

            await startMainApp();
            return true;
        } catch (err) {
            console.error('[MAIN] Error al guardar config:', err);
            return false;
        }
    });

    // 3. Comprobar si existe configuración válida (.env)
    if (configManager.hasValidConfig()) {
        console.log('[MAIN] Configuración .env válida encontrada. Iniciando aplicación...');
        await startMainApp();
    } else {
        console.log('[MAIN] No se encontró .env válido. Abriendo Asistente de Configuración...');
        createSetupWindow();
    }
});

// Limpieza absoluta de procesos
function cleanup() {
    phpManager.stop();
}

app.on('before-quit', cleanup);
app.on('will-quit', cleanup);
process.on('exit', cleanup);
process.on('SIGINT', () => { cleanup(); process.exit(0); });
process.on('SIGTERM', () => { cleanup(); process.exit(0); });

app.on('window-all-closed', () => {
    cleanup();
    if (process.platform !== 'darwin') {
        app.quit();
    }
});
