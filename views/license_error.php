<?php
declare(strict_types=1);

use Config\Security;
use Config\Assets;

$csrfToken = Security::generateCsrfToken();
$license = $license ?? [];
$status = (string)($license['status'] ?? 'LICENCIA_INVALIDA');
$message = (string)($license['message'] ?? 'Licencia no válida o vencida');
$serial = (string)($license['nrolicencia'] ?? '');
$cliente = (string)($license['cliente'] ?? '');
$distribuidor = (string)($license['canaldedistribucion'] ?? '');
$vencimiento = (string)($license['fechavencimiento'] ?? '');
$diasRestantes = $license['dias_restantes'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= Security::escape($title ?? 'Licencia Requerida - ST Despostadora') ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= Assets::faviconDataUri() ?>">
    <link rel="stylesheet" href="<?= Security::baseUrl('/css/app.css') ?>">
    <script>
        window.APP_BASE_URL = <?= json_encode(rtrim(Security::baseUrl(''), '/')) ?>;
        (function() {
            const base = window.APP_BASE_URL;
            if (base && base !== '') {
                const origFetch = window.fetch;
                window.fetch = function(url, options) {
                    if (typeof url === 'string' && url.startsWith('/') && !url.startsWith(base + '/')) {
                        url = base + url;
                    }
                    return origFetch(url, options);
                };
            }
        })();
    </script>
    <style>
        .license-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            width: 100%;
            max-width: 520px;
            padding: 2.25rem 2rem;
            box-shadow: 0 14px 28px rgba(20, 15, 12, 0.6), 0 10px 10px rgba(20, 15, 12, 0.4);
        }
        .license-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: left;
        }
        .license-detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }
        .license-detail-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            font-weight: 600;
        }
        .license-detail-value {
            font-size: 0.9rem;
            color: var(--text-primary);
            font-weight: 500;
            word-break: break-all;
        }
        .license-actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .feedback-message {
            margin-top: 0.75rem;
            padding: 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
            display: none;
        }
        .feedback-message.error {
            display: block;
            background-color: rgba(238, 103, 55, 0.15);
            border: 1px solid var(--danger);
            color: var(--danger);
        }
        .feedback-message.success {
            display: block;
            background-color: rgba(193, 218, 198, 0.15);
            border: 1px solid var(--success);
            color: var(--success);
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="license-card">
            <div class="auth-header" style="margin-bottom: 1.5rem; text-align: center;">
                <div class="auth-logo-badge">
                    <?= Assets::logo() ?>
                </div>
                <h1 class="auth-title">ST Despostadora</h1>
                <p class="auth-subtitle">Validación de Licencia y Vigencia ERP</p>
            </div>

            <!-- Alerta Intrusiva Principal -->
            <div class="alert alert-error" role="alert" style="margin-bottom: 1.25rem; text-align: left; display: flex; align-items: flex-start; gap: 0.75rem; padding: 1.1rem; border-radius: 8px;">
                <svg class="icon" viewBox="0 0 24 24" style="flex-shrink: 0; width: 1.5rem; height: 1.5rem; stroke: currentColor; margin-top: 0.1rem;" fill="none" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <div style="font-size: 0.95rem; font-weight: 600; line-height: 1.45; color: var(--text-primary);">
                    <?= Security::escape($message) ?>
                </div>
            </div>

            <!-- Ficha Técnica de la Licencia Detectada de Forma Autónoma -->
            <div class="license-details-grid">
                <div class="license-detail-item" style="grid-column: 1 / -1;">
                    <span class="license-detail-label">Serial del Sistema ERP (saconf)</span>
                    <span class="license-detail-value" id="displaySerial"><?= Security::escape($serial !== '' ? $serial : 'No detectado') ?></span>
                </div>
                <div class="license-detail-item">
                    <span class="license-detail-label">Cliente / Razón Social</span>
                    <span class="license-detail-value"><?= Security::escape($cliente !== '' ? $cliente : 'No asignado') ?></span>
                </div>
                <div class="license-detail-item">
                    <span class="license-detail-label">Canal Distribuidor</span>
                    <span class="license-detail-value"><?= Security::escape($distribuidor !== '' ? $distribuidor : 'No asignado') ?></span>
                </div>
                <div class="license-detail-item">
                    <span class="license-detail-label">Fecha Vencimiento</span>
                    <span class="license-detail-value"><?= Security::escape($vencimiento !== '' ? $vencimiento : 'No disponible') ?></span>
                </div>
                <div class="license-detail-item">
                    <span class="license-detail-label">Días Restantes</span>
                    <span class="license-detail-value" style="color: <?= ($diasRestantes !== null && $diasRestantes < 0) ? 'var(--danger)' : 'var(--text-primary)' ?>;">
                        <?= $diasRestantes !== null ? Security::escape((string)$diasRestantes) . ' días' : 'N/A' ?>
                    </span>
                </div>
            </div>

            <!-- Mensaje dinámico de respuesta -->
            <div id="licenseFeedback" class="feedback-message"></div>

            <!-- Acciones Autónomas -->
            <div class="license-actions">
                <button type="button" id="btnVerificarLicencia" class="btn btn-primary" style="width: 100%; padding: 0.85rem;">
                    <svg class="icon" viewBox="0 0 24 24" style="width: 1.1rem; height: 1.1rem; margin-right: 0.5rem;" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <polyline points="1 20 1 14 7 14"></polyline>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                    </svg>
                    <span id="btnVerificarText">Verificar Licencia en Línea</span>
                </button>

                <?php if (!empty($_SESSION['user_codusua'])): ?>
                <a href="/logout" class="btn btn-secondary" style="width: 100%; text-decoration: none; padding: 0.75rem; text-align: center; font-size: 0.9rem;">
                    Cerrar Sesión (<?= Security::escape($_SESSION['user_codusua']) ?>)
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const csrfToken = <?= json_encode($csrfToken) ?>;
        const btnVerificar = document.getElementById('btnVerificarLicencia');
        const btnVerificarText = document.getElementById('btnVerificarText');
        const feedback = document.getElementById('licenseFeedback');

        function showFeedback(type, text) {
            feedback.className = 'feedback-message ' + type;
            feedback.textContent = text;
            feedback.style.display = 'block';
        }

        async function verifyLicense() {
            btnVerificar.disabled = true;
            btnVerificarText.textContent = 'Consultando nube Firebase...';
            feedback.style.display = 'none';

            try {
                const res = await fetch('/api/licencia/verificar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    }
                });

                const data = await res.json();

                if (data.valid === true) {
                    showFeedback('success', '¡Licencia verificada con éxito! Redirigiendo...');
                    setTimeout(() => {
                        window.location.href = (window.APP_BASE_URL || '') + '/';
                    }, 1200);
                } else {
                    showFeedback('error', data.message || 'No se pudo validar la licencia.');
                }
            } catch (err) {
                showFeedback('error', 'Error de red o comunicación con el servidor local.');
            } finally {
                btnVerificar.disabled = false;
                btnVerificarText.textContent = 'Verificar Licencia en Línea';
            }
        }

        btnVerificar.addEventListener('click', () => {
            verifyLicense();
        });
    })();
    </script>
</body>
</html>
