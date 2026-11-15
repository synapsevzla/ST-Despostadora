<?php
declare(strict_types=1);

use Config\Security;
use Config\Assets;
use Src\Auth\LicenseService;

$activeLicense = $licenseInfo ?? LicenseService::getCurrent() ?? LicenseService::check();
$clienteDesc = !empty($activeLicense['cliente']) ? (string)$activeLicense['cliente'] : LicenseService::getClientName();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= Security::escape(($title ?? 'Soluciones Totales Despostadora') . ($clienteDesc !== '' && $clienteDesc !== 'Cliente Autorizado' ? " - {$clienteDesc}" : '')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= Assets::faviconDataUri() ?>">
    <link rel="stylesheet" href="<?= Security::baseUrl('/css/app.css') ?>">
    <link rel="stylesheet" href="<?= Security::baseUrl('/css/responsive-cards.css') ?>">
    <link rel="stylesheet" href="<?= Security::baseUrl('/css/toasts.css') ?>">
    <script>
        window.APP_BASE_URL = <?= json_encode(rtrim(Security::baseUrl(''), '/')) ?>;
        (function() {
            const base = window.APP_BASE_URL;
            if (base && base !== '') {
                // Interceptar peticiones fetch relativas a la raíz
                const origFetch = window.fetch;
                window.fetch = function(url, options) {
                    if (typeof url === 'string' && url.startsWith('/') && !url.startsWith(base + '/')) {
                        url = base + url;
                    }
                    return origFetch(url, options);
                };

                // Ajustar enlaces al hacer clic
                document.addEventListener('click', function(e) {
                    const link = e.target.closest('a');
                    if (link && link.getAttribute('href')) {
                        const href = link.getAttribute('href');
                        if (href.startsWith('/') && !href.startsWith('//') && !href.startsWith(base + '/')) {
                            link.setAttribute('href', base + (href === '/' ? '' : href));
                        }
                    }
                }, true);

                // Ajustar enlaces y formularios estáticos del DOM inicial
                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('a[href^="/"]:not([href^="//"])').forEach(function(el) {
                        const h = el.getAttribute('href');
                        if (!h.startsWith(base + '/') && h !== base) {
                            el.setAttribute('href', base + (h === '/' ? '' : h));
                        }
                    });
                    document.querySelectorAll('form[action^="/"]:not([action^="//"])').forEach(function(el) {
                        const a = el.getAttribute('action');
                        if (!a.startsWith(base + '/') && a !== base) {
                            el.setAttribute('action', base + (a === '/' ? '' : a));
                        }
                    });
                });
            }
        })();
    </script>
</head>
<body class="<?= Security::escape($bodyClass ?? '') ?>">
    <div class="app-shell">
        <!-- Overlay para mobile y fondo difuminado -->
        <div id="sidebarOverlay" class="sidebar-overlay"></div>

        <!-- Sidebar Navigation (Auto-ocultable) -->
        <aside id="appSidebar" class="app-sidebar collapsed">
            <div class="sidebar-brand">
                <div class="brand-info">
                    <div class="brand-logo">
                        <?= Assets::logo() ?>
                    </div>
                    <span class="brand-text">ST Despostadora</span>
                </div>
                <button type="button" id="sidebarCloseBtn" class="sidebar-close-btn" aria-label="Cerrar Menú">
                    <svg class="icon" viewBox="0 0 24 24">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section-title">Operaciones</div>
                <ul class="nav-list">
                    <li>
                        <a href="<?= Security::baseUrl('/') ?>" class="nav-link <?= ($activeNav ?? '') === 'home' ? 'active' : '' ?>">
                            <div class="nav-icon">
                                <svg class="icon" viewBox="0 0 24 24">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                </svg>
                            </div>
                            <span>Inicio</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= Security::baseUrl('/recepcion') ?>" class="nav-link <?= ($activeNav ?? '') === 'recepcion' ? 'active' : '' ?>">
                            <div class="nav-icon">
                                <svg class="icon" viewBox="0 0 24 24">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                                </svg>
                            </div>
                            <span>Recepción de Canales</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= Security::baseUrl('/deboning') ?>" class="nav-link <?= ($activeNav ?? '') === 'deboning' ? 'active' : '' ?>">
                            <div class="nav-icon">
                                <svg class="icon" viewBox="0 0 24 24">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10 9 9 9 8 9"></polyline>
                                </svg>
                            </div>
                            <span>Sala de Desposte</span>
                        </a>
                    </li>
                    <?php 
                    $userNivel = trim((string)($user['nivel'] ?? ''));
                    if ($userNivel === '01' || $userNivel === '1' || (int)$userNivel === 1): 
                    ?>
                    <li>
                        <a href="<?= Security::baseUrl('/configuracion') ?>" class="nav-link <?= ($activeNav ?? '') === 'configuracion' ? 'active' : '' ?>">
                            <div class="nav-icon">
                                <svg class="icon" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                </svg>
                            </div>
                            <span>Configuración</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= Security::baseUrl('/auditoria') ?>" class="nav-link <?= ($activeNav ?? '') === 'auditoria' ? 'active' : '' ?>">
                            <div class="nav-icon">
                                <svg class="icon" viewBox="0 0 24 24">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                    <path d="M9 12l2 2 4-4"></path>
                                </svg>
                            </div>
                            <span>Auditoría</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="user-profile-badge">
                    <div class="user-meta">
                        <div class="user-name"><?= Security::escape($user['descrip'] ?? $user['codusua'] ?? 'Usuario') ?></div>
                        <div class="user-role">Nivel: <?= Security::escape($user['nivel'] ?? '1') ?></div>
                    </div>
                    <a href="<?= Security::baseUrl('/logout') ?>" class="logout-link" title="Cerrar Sesión">
                        <svg class="icon" viewBox="0 0 24 24">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Wrapper -->
        <div class="main-wrapper">
            <header class="main-header">
                <div class="header-left">
                    <button type="button" id="sidebarToggleBtn" class="sidebar-toggle-btn" aria-label="Abrir Menú">
                        <svg class="icon" viewBox="0 0 24 24">
                            <line x1="3" y1="12" x2="21" y2="12"></line>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <line x1="3" y1="18" x2="21" y2="18"></line>
                        </svg>
                    </button>
                    <h2 class="header-title"><?= Security::escape($pageTitle ?? 'Inicio') ?></h2>
                    <div class="license-status-badge" title="Serial: <?= Security::escape($activeLicense['nrolicencia'] ?? '') ?> | Vence: <?= Security::escape($activeLicense['fechavencimiento'] ?? 'N/A') ?>">
                        <svg class="icon" viewBox="0 0 24 24" style="width: 0.95rem; height: 0.95rem; stroke: var(--primary); margin-right: 0.35rem; flex-shrink: 0;" fill="none" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                        <span>Licenciado a: <?= Security::escape($clienteDesc !== '' ? $clienteDesc : 'Cliente Autorizado') ?></span>
                    </div>
                </div>
                <div class="header-right">
                    <div id="liveClock" class="header-datetime" aria-live="polite"></div>
                </div>
            </header>

            <?php if (!empty($activeLicense) && ($activeLicense['status'] ?? '') === LicenseService::STATUS_VIGENTE_POR_VENCER): ?>
            <div class="license-warning-bar" role="alert">
                <div class="license-warning-inner">
                    <svg class="icon" viewBox="0 0 24 24" style="width: 1.25rem; height: 1.25rem; flex-shrink: 0; stroke: currentColor;" fill="none" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?= Security::escape($activeLicense['message'] ?? 'Su licencia está próxima a vencer. Contacte a su distribuidor') ?></span>
                </div>
            </div>
            <?php endif; ?>

            <main class="content-body">
                <?= $content ?? '' ?>
            </main>
        </div>
    </div>

    <script src="<?= Security::baseUrl('/js/app.js') ?>"></script>
</body>
</html>
