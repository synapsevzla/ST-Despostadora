/**
 * Buscador de puerto TCP libre en 127.0.0.1
 * Evita colisiones de puertos con IIS, Apache u otros servicios en la máquina Windows.
 */
const net = require('net');

function getAvailablePort(defaultPort = 0) {
    return new Promise((resolve, reject) => {
        const server = net.createServer();
        server.unref();

        server.on('error', (err) => {
            if (defaultPort !== 0) {
                // Si el puerto por defecto falló, buscar uno efímero cualquiera
                getAvailablePort(0).then(resolve).catch(reject);
            } else {
                reject(err);
            }
        });

        server.listen(defaultPort, '127.0.0.1', () => {
            const address = server.address();
            const port = typeof address === 'object' && address ? address.port : defaultPort;
            server.close((closeErr) => {
                if (closeErr) {
                    reject(closeErr);
                } else {
                    resolve(port);
                }
            });
        });
    });
}

module.exports = { getAvailablePort };
