/**
 * Gestor de Descubrimiento, Validación y Persistencia de .env
 * Permite que Electron reconozca la configuración en Windows sin problemas de permisos UAC.
 */
const fs = require('fs');
const path = require('path');
const { app } = require('electron');

class ConfigManager {
    constructor() {
        this.userDataEnvPath = path.join(app.getPath('userData'), '.env');
        this.portableEnvPath = app.isPackaged
            ? path.join(path.dirname(app.getPath('exe')), '.env')
            : path.join(__dirname, '..', '.env');
    }

    /**
     * Localiza el archivo .env existente con mayor prioridad
     */
    findEnvPath() {
        // 1. Prioridad: Archivo junto al ejecutable (Modo Portable)
        if (fs.existsSync(this.portableEnvPath)) {
            return this.portableEnvPath;
        }

        // 2. Prioridad: Directorio de datos del usuario (Modo Instalado NSIS)
        if (fs.existsSync(this.userDataEnvPath)) {
            return this.userDataEnvPath;
        }

        // 3. Fallback en desarrollo
        const devEnv = path.join(__dirname, '..', '.env');
        if (fs.existsSync(devEnv)) {
            return devEnv;
        }

        return null;
    }

    /**
     * Parsea un archivo .env a un objeto clave-valor
     */
    parseEnvFile(filePath) {
        if (!filePath || !fs.existsSync(filePath)) {
            return {};
        }

        try {
            const content = fs.readFileSync(filePath, 'utf8');
            const lines = content.split(/\r?\n/);
            const env = {};

            for (const line of lines) {
                const trimmed = line.trim();
                if (!trimmed || trimmed.startsWith('#')) continue;

                const match = trimmed.match(/^([^=]+)=(.*)$/);
                if (match) {
                    const key = match[1].trim();
                    let val = match[2].trim();
                    if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith("'") && val.endsWith("'"))) {
                        val = val.slice(1, -1);
                    }
                    env[key] = val;
                }
            }
            return env;
        } catch (err) {
            console.error('[CONFIG] Error al leer archivo .env:', err);
            return {};
        }
    }

    /**
     * Comprueba si la configuración existe y contiene los campos esenciales
     */
    hasValidConfig() {
        const envPath = this.findEnvPath();
        if (!envPath) return false;

        const config = this.parseEnvFile(envPath);
        if (!config.DB_HOST || !config.DB_NAME || !config.DB_USER) {
            return false;
        }

        return true;
    }

    /**
     * Obtiene la configuración actual completa
     */
    getConfig() {
        const envPath = this.findEnvPath();
        if (!envPath) return {};
        return this.parseEnvFile(envPath);
    }

    /**
     * Guarda la nueva configuración .env de forma segura y persistente
     */
    saveConfig(data) {
        const lines = [
            '# Configuración generada por Asistente de ST Despostadora',
            'APP_ENV=production',
            'APP_DEBUG=false',
            `DB_DRIVER=${data.DB_DRIVER || 'sqlsrv'}`,
            `DB_HOST=${data.DB_HOST || '127.0.0.1'}`,
            `DB_PORT=${data.DB_PORT || '1433'}`,
            `DB_NAME=${data.DB_NAME || ''}`,
            `DB_USER=${data.DB_USER || 'sa'}`,
            `DB_PASS=${data.DB_PASS || ''}`,
            `DB_TIMEOUT=${data.DB_TIMEOUT || '5'}`,
            ''
        ];

        const content = lines.join('\r\n');

        // Guardar siempre en userData para modo instalado
        const targetDir = path.dirname(this.userDataEnvPath);
        if (!fs.existsSync(targetDir)) {
            fs.mkdirSync(targetDir, { recursive: true });
        }
        fs.writeFileSync(this.userDataEnvPath, content, 'utf8');

        // Si es modo portable y la carpeta es escribible, guardar también junto al .exe
        try {
            const portableDir = path.dirname(this.portableEnvPath);
            fs.accessSync(portableDir, fs.constants.W_OK);
            fs.writeFileSync(this.portableEnvPath, content, 'utf8');
        } catch (_) {
            // Es de solo lectura (ej. C:\Program Files\), se mantiene en userData
        }

        return this.userDataEnvPath;
    }
}

module.exports = new ConfigManager();
