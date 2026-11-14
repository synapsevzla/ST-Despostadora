/**
 * Lógica del Asistente de Configuración Inicial (Wizard)
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('setupForm');
    const btnTest = document.getElementById('btnTest');
    const btnTestText = document.getElementById('btnTestText');
    const btnSave = document.getElementById('btnSave');
    const alertBox = document.getElementById('alertBox');
    const btnMin = document.getElementById('btnMin');
    const btnClose = document.getElementById('btnClose');

    // Controles de ventana
    if (btnMin) btnMin.addEventListener('click', () => window.electronAPI.minimize());
    if (btnClose) btnClose.addEventListener('click', () => window.electronAPI.close());

    function showAlert(type, message) {
        alertBox.className = `alert-box ${type}`;
        alertBox.textContent = message;
        alertBox.style.display = 'block';
    }

    function hideAlert() {
        alertBox.style.display = 'none';
    }

    function getFormData() {
        const data = {};
        const inputs = form.querySelectorAll('input, select');
        inputs.forEach(input => {
            data[input.name] = input.value.trim();
        });
        return data;
    }

    // Probar conexión a la base de datos
    btnTest.addEventListener('click', async () => {
        const data = getFormData();
        if (!data.DB_HOST || !data.DB_NAME || !data.DB_USER) {
            showAlert('error', 'Por favor complete el Servidor, Base de Datos y Usuario.');
            return;
        }

        showAlert('loading', 'Probando conexión con Microsoft SQL Server...');
        btnTest.disabled = true;
        btnSave.disabled = true;
        btnTestText.textContent = 'Verificando...';

        try {
            const result = await window.electronAPI.testDb(data);
            if (result && result.success) {
                showAlert('success', '¡Conexión establecida exitosamente con SQL Server!');
            } else {
                showAlert('error', 'Fallo de conexión: ' + (result?.error || 'No se pudo conectar al servidor.'));
            }
        } catch (err) {
            showAlert('error', 'Error al ejecutar prueba: ' + err.message);
        } finally {
            btnTest.disabled = false;
            btnSave.disabled = false;
            btnTestText.textContent = 'Probar Conexión';
        }
    });

    // Guardar configuración y continuar
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = getFormData();

        if (!data.DB_HOST || !data.DB_NAME || !data.DB_USER) {
            showAlert('error', 'Por favor complete todos los campos requeridos.');
            return;
        }

        showAlert('loading', 'Guardando configuración e iniciando sistema...');
        btnTest.disabled = true;
        btnSave.disabled = true;

        try {
            const saved = await window.electronAPI.saveDb(data);
            if (saved) {
                showAlert('success', 'Configuración guardada. Abriendo ST Despostadora...');
                // El proceso principal cerrará esta ventana y abrirá la aplicación principal
            } else {
                showAlert('error', 'No se pudo guardar la configuración.');
                btnTest.disabled = false;
                btnSave.disabled = false;
            }
        } catch (err) {
            showAlert('error', 'Error al guardar: ' + err.message);
            btnTest.disabled = false;
            btnSave.disabled = false;
        }
    });
});
