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
    <title><?= Security::escape($title ?? 'Soluciones Totales Despostadora') ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= Assets::faviconDataUri() ?>">
    <link rel="stylesheet" href="<?= Security::baseUrl('/css/app.css') ?>">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo-badge">
                    <?= Assets::logo() ?>
                </div>
                <h1 class="auth-title">ST Despostadora</h1>
                <p class="auth-subtitle">Gestión y Rendimiento Cárnico</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <svg class="icon" viewBox="0 0 24 24" style="flex-shrink: 0; width: 1.2rem; height: 1.2rem;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?= Security::escape($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="<?= Security::baseUrl('/login') ?>" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= Security::escape($csrf_token ?? '') ?>">

                <div class="form-group">
                    <label class="form-label" for="codusua">Código de Usuario</label>
                    <input type="text" id="codusua" name="codusua" class="form-input" required autofocus placeholder="Ej. ADMIN">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Correo Electrónico</label>
                    <input type="email" id="email" name="email" class="form-input" required placeholder="correo@empresa.com">
                </div>

                <div class="form-group">
                    <label class="form-label" for="clave">Contraseña</label>
                    <input type="password" id="clave" name="clave" class="form-input" required placeholder="••••••••">
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">
                    Iniciar Sesión
                </button>
            </form>
        </div>
    </div>
</body>
</html>
