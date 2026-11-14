/**
 * Gestor del Servidor Embebido PHP y Pruebas de Conexión a SQL Server
 */
const { spawn, execSync } = require('child_process');
const path = require('path');
const fs = require('fs');
const { app } = require('electron');

class PhpManager {
    constructor() {
        this.process = null;
        this.port = null;
    }

    getPhpPaths() {
        let phpExe;
        let phpIni;
        let publicPath;

        if (app && app.isPackaged) {
            const resPath = process.resourcesPath;
            if (process.platform === 'win32') {
                phpExe = path.join(resPath, 'php-win', 'php.exe');
                phpIni = path.join(resPath, 'php-win', 'php.ini');
            } else {
                // Plataforma Linux / Unix
                const embeddedPhp = path.join(resPath, 'php-linux', 'bin', 'php');
                if (fs.existsSync(embeddedPhp)) {
                    phpExe = embeddedPhp;
                    phpIni = path.join(resPath, 'php-linux', 'etc', 'php.ini');
                } else {
                    phpExe = 'php';
                    phpIni = null;
                }
            }
            publicPath = path.join(resPath, 'dist_web', 'public');
        } else {
            // Entorno de desarrollo
            if (process.platform === 'win32') {
                const winPhp = path.join(__dirname, 'php-win', 'php.exe');
                if (fs.existsSync(winPhp)) {
                    phpExe = winPhp;
                    phpIni = path.join(__dirname, 'php-win', 'php.ini');
                } else {
                    phpExe = 'php';
                    phpIni = null;
                }
            } else {
                phpExe = 'php';
                phpIni = null;
            }
            publicPath = path.join(__dirname, '..', 'dist_web', 'public');
        }

        return { phpExe, phpIni, publicPath };
    }

    /**
     * Prueba la conexión a Microsoft SQL Server utilizando el runtime PHP embebido
     */
    async testDbConnection(params) {
        const { phpExe, phpIni } = this.getPhpPaths();
        
        const host = params.DB_HOST || '127.0.0.1';
        const port = params.DB_PORT || '1433';
        const dbname = params.DB_NAME || '';
        const user = params.DB_USER || '';
        const pass = params.DB_PASS || '';
        const driver = params.DB_DRIVER || 'sqlsrv';
        const timeout = Number(params.DB_TIMEOUT) || 4;

        // Script PHP inline seguro para probar conexión PDO
        const phpCode = `
            error_reporting(0);
            $driver = ${JSON.stringify(driver)};
            $host = ${JSON.stringify(host)};
            $port = ${JSON.stringify(port)};
            $dbname = ${JSON.stringify(dbname)};
            $user = ${JSON.stringify(user)};
            $pass = ${JSON.stringify(pass)};
            $timeout = (int)${timeout};

            if ($driver === 'sqlsrv') {
                $dsn = 'sqlsrv:Server=' . $host . ',' . $port . ';Database=' . $dbname . ';TrustServerCertificate=true;LoginTimeout=' . $timeout;
            } else {
                $dsn = 'dblib:host=' . $host . ':' . $port . ';dbname=' . $dbname . ';timeout=' . $timeout;
            }

            try {
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => $timeout
                ]);
                echo json_encode(['success' => true]);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        `;

        return new Promise((resolve) => {
            const args = [];
            if (phpIni && fs.existsSync(phpIni)) {
                args.push('-c', phpIni);
            }
            args.push('-r', phpCode);

            let stdout = '';
            let stderr = '';

            try {
                const child = spawn(phpExe, args);

                child.stdout.on('data', (d) => { stdout += d.toString(); });
                child.stderr.on('data', (d) => { stderr += d.toString(); });

                child.on('close', (code) => {
                    try {
                        const parsed = JSON.parse(stdout.trim());
                        resolve(parsed);
                    } catch (_) {
                        resolve({
                            success: false,
                            error: stderr.trim() || stdout.trim() || 'No se pudo conectar a la base de datos.'
                        });
                    }
                });

                child.on('error', (err) => {
                    resolve({ success: false, error: 'Error al ejecutar PHP: ' + err.message });
                });
            } catch (err) {
                resolve({ success: false, error: err.message });
            }
        });
    }

    /**
     * Inicia el servidor PHP embebido en un puerto libre
     */
    start(port, envConfig = {}) {
        this.port = port;
        const { phpExe, phpIni, publicPath } = this.getPhpPaths();

        if (!fs.existsSync(publicPath)) {
            throw new Error(`Directorio público web no encontrado en: ${publicPath}`);
        }

        const args = ['-S', `127.0.0.1:${port}`, '-t', publicPath];
        if (phpIni && fs.existsSync(phpIni)) {
            args.push('-c', phpIni);
        }

        // Combinar variables de entorno para que PHP herede las credenciales DB
        const spawnEnv = {
            ...process.env,
            ...envConfig,
            PHP_CLI_SERVER_WORKERS: '4'
        };

        console.log(`[PHP] Iniciando servidor embebido en http://127.0.0.1:${port}...`);
        this.process = spawn(phpExe, args, {
            env: spawnEnv,
            cwd: publicPath
        });

        this.process.stdout.on('data', (data) => {
            console.log(`[PHP STDOUT] ${data.toString().trim()}`);
        });

        this.process.stderr.on('data', (data) => {
            console.log(`[PHP SERVER] ${data.toString().trim()}`);
        });

        this.process.on('close', (code) => {
            console.log(`[PHP] Proceso terminado con código: ${code}`);
            this.process = null;
        });

        return this.process;
    }

    /**
     * Espera a que el servidor PHP responda en el puerto
     */
    async waitForReady(maxAttempts = 30, intervalMs = 200) {
        const http = require('http');
        const url = `http://127.0.0.1:${this.port}/login`;

        for (let i = 0; i < maxAttempts; i++) {
            const isReady = await new Promise((resolve) => {
                const req = http.get(url, (res) => {
                    resolve(res.statusCode >= 200 && res.statusCode < 500);
                });
                req.on('error', () => resolve(false));
                req.setTimeout(500, () => {
                    req.destroy();
                    resolve(false);
                });
            });

            if (isReady) return true;
            await new Promise((r) => setTimeout(r, intervalMs));
        }
        return false;
    }

    /**
     * Mata limpiamente el subproceso PHP para evitar procesos huérfanos en Windows
     */
    stop() {
        if (!this.process) return;

        try {
            if (process.platform === 'win32') {
                execSync(`taskkill /pid ${this.process.pid} /f /t`);
            } else {
                this.process.kill('SIGTERM');
            }
        } catch (_) {
            try { this.process.kill('SIGKILL'); } catch (__) {}
        } finally {
            this.process = null;
        }
    }
}

module.exports = new PhpManager();
