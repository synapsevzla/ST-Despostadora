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
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
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
                        <a href="/" class="nav-link <?= ($activeNav ?? '') === 'home' ? 'active' : '' ?>">
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
                        <a href="/deboning" class="nav-link <?= ($activeNav ?? '') === 'deboning' ? 'active' : '' ?>">
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
                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="user-profile-badge">
                    <div class="user-meta">
                        <div class="user-name"><?= Security::escape($user['descrip'] ?? $user['codusua'] ?? 'Usuario') ?></div>
                        <div class="user-role">Nivel: <?= Security::escape($user['nivel'] ?? '1') ?></div>
                    </div>
                    <a href="/logout" class="logout-link" title="Cerrar Sesión">
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
                </div>
                <div class="header-right">
                    <div id="liveClock" class="header-datetime" aria-live="polite"></div>
                </div>
            </header>

            <main class="content-body">
                <?= $content ?? '' ?>
            </main>
        </div>
    </div>

    <script src="/js/app.js"></script>
</body>
</html>
