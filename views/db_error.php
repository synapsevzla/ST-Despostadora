<?php
declare(strict_types=1);

use Config\Security;
use Config\Assets;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= Security::escape($title ?? 'Error de Conexión - ST Despostadora') ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= Assets::faviconDataUri() ?>">
    <link rel="stylesheet" href="<?= Security::baseUrl('/css/app.css') ?>">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card" style="text-align: center;">
            <div class="auth-header" style="margin-bottom: 1.5rem;">
                <div class="auth-logo-badge">
                    <?= Assets::logo() ?>
                </div>
                <h1 class="auth-title">ST Despostadora</h1>
                <p class="auth-subtitle">Estado del Servidor y Base de Datos</p>
            </div>

            <div class="alert alert-error" role="alert" style="margin-bottom: 1.75rem; text-align: left; display: flex; align-items: flex-start; gap: 0.75rem; padding: 1.1rem; border-radius: 8px;">
                <svg class="icon" viewBox="0 0 24 24" style="flex-shrink: 0; width: 1.5rem; height: 1.5rem; stroke: currentColor; margin-top: 0.1rem;" fill="none" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <div style="font-size: 0.95rem; font-weight: 500; line-height: 1.45; color: var(--text-primary);">
                    <?= Security::escape($message ?? 'Base de datos o servidor fuera de línea por favor contacte a soporte técnico') ?>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <button type="button" onclick="window.location.reload();" class="btn btn-primary" style="width: 100%; padding: 0.85rem;">
                    <svg class="icon" viewBox="0 0 24 24" style="width: 1.1rem; height: 1.1rem; margin-right: 0.5rem;" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <polyline points="1 20 1 14 7 14"></polyline>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                    </svg>
                    Reintentar Conexión
                </button>
                <a href="<?= Security::baseUrl('/login') ?>" class="btn btn-secondary" style="width: 100%; text-decoration: none; padding: 0.85rem; text-align: center;">
                    Ir al Inicio de Sesión
                </a>
            </div>
        </div>
    </div>
</body>
</html>
