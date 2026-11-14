<?php
declare(strict_types=1);

use Config\Security;

ob_start();
?>
<link rel="stylesheet" href="/css/dashboard.css">

<div class="dashboard-wrapper">
    <!-- Banner de Bienvenida y Acciones Rápidas -->
    <div class="dashboard-banner">
        <div class="dashboard-banner-info">
            <h3>¡Hola, <?= Security::escape($user['descrip'] ?? $user['codusua'] ?? 'Usuario') ?>!</h3>
            <p>Panel operativo en vivo de control de pesajes, rendimiento cárnico y desglose de canales.</p>
        </div>
        <div class="dashboard-banner-actions">
            <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; color: var(--text-muted); margin-right: 0.5rem;">
                <span class="live-pulse" title="Datos en vivo"></span>
                <span>Actualizado: <strong id="dashboardLastUpdate" style="color: var(--text-secondary);"><?= date('H:i:s') ?></strong></span>
                <button type="button" id="dashboardRefreshBtn" class="btn btn-secondary" style="padding: 0.35rem 0.6rem; min-width: auto;" title="Recargar datos">
                    <svg id="dashboardRefreshIcon" class="icon" viewBox="0 0 24 24" style="width: 14px; height: 14px;">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <polyline points="1 20 1 14 7 14"></polyline>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                    </svg>
                </button>
            </div>
            <a href="/recepcion/nueva" class="btn btn-secondary" style="text-decoration: none; padding: 0.6rem 1rem;">
                <svg class="icon" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                + Nueva Recepción
            </a>
            <a href="/desposte/nueva" class="btn btn-primary" style="text-decoration: none; padding: 0.6rem 1rem;">
                <svg class="icon" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                + Iniciar Desposte
            </a>
        </div>
    </div>

    <?php if (!empty($dashboardError)): ?>
        <div class="alert alert-danger" style="background: rgba(238, 103, 55, 0.15); border: 1px solid var(--danger); padding: 0.85rem 1rem; border-radius: 8px; color: var(--text-primary); font-size: 0.9rem;">
            ⚠️ <strong>Aviso del sistema:</strong> <?= Security::escape($dashboardError) ?>
        </div>
    <?php endif; ?>

    <!-- SECCIÓN 1: Total de Kilos Despostados (Día, Semana, Mes) -->
    <section class="kpi-section">
        <div class="kpi-section-header">
            <h4 class="kpi-section-title">
                <svg class="icon" viewBox="0 0 24 24" style="color: var(--primary);"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                Total de Kilos Despostados
            </h4>
            <span class="kpi-section-subtitle">Pesaje neto acumulado de cortes procesados</span>
        </div>

        <div class="kpi-grid kpi-grid-3">
            <!-- Kilos del Día -->
            <div class="kpi-card kpi-primary">
                <div class="kpi-top">
                    <span class="kpi-label">Despostado Hoy</span>
                    <div class="kpi-icon-wrap">
                        <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </div>
                </div>
                <div class="kpi-value-row">
                    <span id="valKilosDia" class="kpi-value"><?= number_format($kilos['dia'], 2, ',', '.') ?></span>
                    <span class="kpi-unit">kg</span>
                </div>
                <div class="kpi-meta">
                    <span id="valCortesDia" class="kpi-meta-badge" style="background: rgba(244, 107, 58, 0.15); color: var(--primary);">
                        <?= $kilos['cortes_dia'] ?> cortes
                    </span>
                    <span>registrados hoy</span>
                </div>
            </div>

            <!-- Kilos de la Semana -->
            <div class="kpi-card kpi-accent">
                <div class="kpi-top">
                    <span class="kpi-label">Esta Semana</span>
                    <div class="kpi-icon-wrap icon-accent">
                        <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </div>
                </div>
                <div class="kpi-value-row">
                    <span id="valKilosSemana" class="kpi-value"><?= number_format($kilos['semana'], 2, ',', '.') ?></span>
                    <span class="kpi-unit">kg</span>
                </div>
                <div class="kpi-meta">
                    <span id="valCortesSemana" class="kpi-meta-badge" style="background: rgba(216, 134, 94, 0.15); color: var(--accent);">
                        <?= $kilos['cortes_semana'] ?> cortes
                    </span>
                    <span>semana corriente</span>
                </div>
            </div>

            <!-- Kilos del Mes -->
            <div class="kpi-card kpi-info">
                <div class="kpi-top">
                    <span class="kpi-label">Este Mes</span>
                    <div class="kpi-icon-wrap icon-info">
                        <svg class="icon" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                    </div>
                </div>
                <div class="kpi-value-row">
                    <span id="valKilosMes" class="kpi-value"><?= number_format($kilos['mes'], 2, ',', '.') ?></span>
                    <span class="kpi-unit">kg</span>
                </div>
                <div class="kpi-meta">
                    <span id="valCortesMes" class="kpi-meta-badge" style="background: rgba(75, 163, 227, 0.15); color: #4ba3e3;">
                        <?= $kilos['cortes_mes'] ?> cortes
                    </span>
                    <span>mes en curso</span>
                </div>
            </div>
        </div>
    </section>

    <!-- SECCIÓN 2: Órdenes de Desposte (Abiertas, En Proceso, Terminadas) -->
    <section class="kpi-section">
        <div class="kpi-section-header">
            <h4 class="kpi-section-title">
                <svg class="icon" viewBox="0 0 24 24" style="color: var(--accent);"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                Órdenes de Desposte
            </h4>
            <span class="kpi-section-subtitle">
                Total órdenes: <strong id="valOrdTotal"><?= $ordenes['total'] ?></strong>
            </span>
        </div>

        <div class="kpi-grid kpi-grid-3">
            <!-- Órdenes Abiertas -->
            <div class="kpi-card kpi-warning">
                <div class="kpi-top">
                    <span class="kpi-label">Órdenes Abiertas</span>
                    <div class="kpi-icon-wrap icon-warning">
                        <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    </div>
                </div>
                <div class="kpi-value-row">
                    <span id="valOrdAbiertas" class="kpi-value" style="color: #e59d3b;"><?= $ordenes['abiertas'] ?></span>
                    <span class="kpi-unit">órdenes</span>
                </div>
                <div class="kpi-meta">
                    <span class="kpi-meta-badge" style="background: rgba(229, 157, 59, 0.15); color: #e59d3b;">Listas para iniciar</span>
                    <span>En espera de pesaje</span>
                </div>
            </div>

            <!-- Órdenes En Proceso -->
            <div class="kpi-card kpi-primary">
                <div class="kpi-top">
                    <span class="kpi-label">En Proceso</span>
                    <div class="kpi-icon-wrap">
                        <svg class="icon" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                    </div>
                </div>
                <div class="kpi-value-row">
                    <span id="valOrdProceso" class="kpi-value" style="color: var(--primary);"><?= $ordenes['en_proceso'] ?></span>
                    <span class="kpi-unit">activas</span>
                </div>
                <div class="kpi-meta">
                    <span class="kpi-meta-badge" style="background: rgba(244, 107, 58, 0.15); color: var(--primary);">Desposte activo</span>
                    <span>Cortes pesándose en mesa</span>
                </div>
            </div>

            <!-- Órdenes Terminadas -->
            <div class="kpi-card kpi-success">
                <div class="kpi-top">
                    <span class="kpi-label">Terminadas</span>
                    <div class="kpi-icon-wrap icon-success">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    </div>
                </div>
                <div class="kpi-value-row">
                    <span id="valOrdTerminadas" class="kpi-value" style="color: #68b078;"><?= $ordenes['terminadas'] ?></span>
                    <span class="kpi-unit">finalizadas</span>
                </div>
                <div class="kpi-meta">
                    <span class="kpi-meta-badge" style="background: rgba(104, 176, 120, 0.15); color: #68b078;">Cerradas</span>
                    <span>Balanceadas con éxito</span>
                </div>
            </div>
        </div>
    </section>

    <!-- SECCIÓN 3: Especies Disponibles en Stock (Dinámico según especies recepcionadas y modos de recepción) -->
    <section class="kpi-section">
        <div class="kpi-section-header">
            <h4 class="kpi-section-title">
                <svg class="icon" viewBox="0 0 24 24" style="color: #68b078;"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                Especies Disponibles en Stock
            </h4>
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <span class="badge" style="background: rgba(104, 176, 120, 0.15); color: #68b078; border: 1px solid rgba(104, 176, 120, 0.3);">
                    Total Kilos: <strong><?= number_format($especiesData['resumen']['total_kilos'], 2, ',', '.') ?> kg</strong>
                </span>
                <span class="badge" style="background: rgba(244, 107, 58, 0.15); color: var(--primary); border: 1px solid rgba(244, 107, 58, 0.3);">
                    Total Piezas/Animales: <strong><?= $especiesData['resumen']['total_piezas'] ?></strong>
                </span>
            </div>
        </div>

        <?php if (empty($especiesData['especies'])): ?>
            <div class="dashboard-empty-card">
                <svg class="icon" viewBox="0 0 24 24" style="width: 48px; height: 48px; stroke: var(--border-color); margin-bottom: 0.75rem;">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                </svg>
                <p style="font-size: 1rem; color: var(--text-primary); margin-bottom: 0.35rem;">No hay especies con stock disponible en cámara.</p>
                <p style="font-size: 0.85rem; margin-bottom: 1rem;">Registre nuevas recepciones de canales o verifique la configuración de especies.</p>
                <a href="/recepcion/nueva" class="btn btn-primary" style="display: inline-flex; width: auto; text-decoration: none;">+ Registrar Recepción</a>
            </div>
        <?php else: ?>
            <div class="species-grid">
                <?php foreach ($especiesData['especies'] as $esp): ?>
                    <div class="species-card">
                        <!-- Cabecera de la Especie -->
                        <div class="species-card-header">
                            <div>
                                <div class="species-card-title"><?= Security::escape($esp['nombre']) ?></div>
                                <span class="species-card-code"><?= Security::escape($esp['codprod']) ?></span>
                            </div>
                            <div>
                                <?php if ($esp['esta_configurado']): ?>
                                    <span class="badge badge-success" title="Especie activa en catálogo de desposte">Configurada</span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(140, 115, 101, 0.2); color: var(--text-muted); border: 1px solid var(--border-color);">Recepción</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Métricas Principales: Kilos y Cantidad Disponibles -->
                        <div class="species-stats-duo">
                            <div class="species-stat-box">
                                <span class="species-stat-label">Kilos Disponibles</span>
                                <div class="species-stat-val">
                                    <?= number_format($esp['peso_total'], 2, ',', '.') ?>
                                    <span class="species-stat-unit"><?= Security::escape($esp['unidad']) ?></span>
                                </div>
                            </div>
                            <div class="species-stat-box">
                                <span class="species-stat-label">Cantidad Disponible</span>
                                <div class="species-stat-val">
                                    <?= $esp['cantidad_total'] > 0 ? (int)$esp['cantidad_total'] : '—' ?>
                                    <span class="species-stat-unit"><?= $esp['cantidad_total'] > 0 ? 'piezas/cab' : 's/cant' ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Desglose por Modos de Recepción -->
                        <div class="species-modes-container">
                            <div class="species-modes-title">
                                <svg class="icon" viewBox="0 0 24 24" style="width: 13px; height: 13px;"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                Desglose por Modo de Recepción:
                            </div>

                            <?php if (empty($esp['modos'])): ?>
                                <div style="font-size: 0.8rem; color: var(--text-muted); font-style: italic; padding: 0.35rem 0.5rem;">
                                    Sin stock disponible actualmente en cámara.
                                </div>
                            <?php else: ?>
                                <?php foreach ($esp['modos'] as $modo): ?>
                                    <div class="species-mode-item">
                                        <div class="species-mode-name">
                                            <?php if ($modo['metodo'] === 'PESO_INDIVIDUAL'): ?>
                                                <span title="Pieza a pieza">🏷️</span>
                                            <?php elseif ($modo['metodo'] === 'PESO_CANTIDAD'): ?>
                                                <span title="Lote con conteo">📦</span>
                                            <?php else: ?>
                                                <span title="Peso global">⚖️</span>
                                            <?php endif; ?>
                                            <span><?= Security::escape($modo['label']) ?></span>
                                        </div>
                                        <div class="species-mode-values">
                                            <?php if ($modo['metodo'] === 'PESO_INDIVIDUAL' || $modo['metodo'] === 'PESO_CANTIDAD'): ?>
                                                <span style="color: var(--primary);"><?= $modo['cantidad'] ?> pzs</span> / 
                                            <?php endif; ?>
                                            <span><?= number_format($modo['peso'], 2, ',', '.') ?> kg</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Botones de Acción Rápida -->
                        <div class="species-card-actions">
                            <a href="/desposte/nueva?especie=<?= urlencode((string)$esp['codprod']) ?>" class="btn btn-primary" style="flex: 1; text-decoration: none; padding: 0.5rem 0.75rem; font-size: 0.82rem; text-align: center;">
                                Despostar Especie
                            </a>
                            <a href="/recepcion/nueva?especie=<?= urlencode((string)$esp['codprod']) ?>" class="btn btn-secondary" style="text-decoration: none; padding: 0.5rem 0.75rem; font-size: 0.82rem;" title="Nueva recepción de esta especie">
                                + Recepción
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- SECCIÓN 4: Mesas y Órdenes Activas en Proceso (Acceso Directo) -->
    <section class="kpi-section">
        <div class="kpi-section-header">
            <h4 class="kpi-section-title">
                <svg class="icon" viewBox="0 0 24 24" style="color: var(--primary);"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                Órdenes en Sala de Desposte
            </h4>
            <a href="/deboning" style="font-size: 0.85rem; color: var(--accent); text-decoration: none; font-weight: 500;">
                Ver Sala de Desposte Completa &rarr;
            </a>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <?php if (empty($recientes)): ?>
                <div style="padding: 2rem; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
                    No hay órdenes de desposte registradas recientemente.
                </div>
            <?php else: ?>
                <!-- Vista de Tabla para Escritorio -->
                <div class="responsive-table-desktop" style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.88rem;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-secondary); text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.05em; background: rgba(55, 42, 37, 0.5);">
                                <th style="padding: 0.75rem 1rem;">N° Orden</th>
                                <th style="padding: 0.75rem 0.5rem;">Mesa / Línea</th>
                                <th style="padding: 0.75rem 0.5rem;">Especie</th>
                                <th style="padding: 0.75rem 0.5rem; text-align: right;">Canal Entrada</th>
                                <th style="padding: 0.75rem 0.5rem; text-align: right;">Cortes Pesados</th>
                                <th style="padding: 0.75rem 0.5rem; text-align: right;">Rendimiento</th>
                                <th style="padding: 0.75rem 0.5rem; text-align: center;">Estado</th>
                                <th style="padding: 0.75rem 1rem; text-align: center;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recientes as $ord): ?>
                                <tr style="border-bottom: 1px solid var(--border-color); transition: background-color 0.2s;">
                                    <td style="padding: 0.75rem 1rem; font-weight: 600; font-family: monospace; color: var(--primary);">
                                        <?= Security::escape($ord['NroOrdenDesposte']) ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; color: var(--text-primary);">
                                        <?= Security::escape($ord['LineaMesa']) ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; color: var(--text-secondary);">
                                        <?= Security::escape($ord['NombreCanal']) ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 600; font-variant-numeric: tabular-nums;">
                                        <?= number_format((float)$ord['PesoCanalEntrada'], 2, ',', '.') ?> kg
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: right; font-variant-numeric: tabular-nums;">
                                        <strong><?= number_format((float)$ord['PesoTotalCortes'], 2, ',', '.') ?> kg</strong>
                                        <span style="font-size: 0.75rem; color: var(--text-muted);">(<?= (int)$ord['TotalCortesPesados'] ?>)</span>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 600; font-variant-numeric: tabular-nums;">
                                        <?php if ((float)$ord['RendimientoPct'] > 0): ?>
                                            <span style="color: #68b078;"><?= number_format((float)$ord['RendimientoPct'], 2, ',', '.') ?>%</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">0.00%</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: center;">
                                        <?php if ($ord['Estado'] === 'FINALIZADO' || $ord['Estado'] === 'TERMINADA'): ?>
                                            <span class="badge badge-success">Terminada</span>
                                        <?php elseif ((float)$ord['PesoTotalCortes'] > 0): ?>
                                            <span class="badge badge-warning">En Proceso</span>
                                        <?php else: ?>
                                            <span class="badge badge-info">Abierta</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; text-align: center;">
                                        <a href="/deboning" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.78rem; text-decoration: none;" title="Abrir mesa de desposte">
                                            Ir a Desposte &rarr;
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Vista de Tarjetas Táctiles para Móviles / Tablets / POS 800x600 -->
                <div class="responsive-cards-mobile" style="padding: 0.75rem;">
                    <?php foreach ($recientes as $ord): ?>
                        <?php 
                            $isTerm = ($ord['Estado'] === 'FINALIZADO' || $ord['Estado'] === 'TERMINADA');
                            $isProc = ((float)$ord['PesoTotalCortes'] > 0 && !$isTerm);
                            $stClass = $isTerm ? 'status-finalizado' : ($isProc ? 'status-en-proceso' : 'status-abierta');
                        ?>
                        <div class="data-card <?= $stClass ?>">
                            <div class="data-card-header">
                                <span class="data-card-code"><?= Security::escape($ord['NroOrdenDesposte']) ?></span>
                                <div class="data-card-badges">
                                    <?php if (!empty(trim((string)$ord['LineaMesa']))): ?>
                                        <span class="badge" style="background: #55433a; color: var(--text-primary); font-size: 0.7rem;">
                                            <?= Security::escape($ord['LineaMesa']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($isTerm): ?>
                                        <span class="badge badge-success">Terminada</span>
                                    <?php elseif ($isProc): ?>
                                        <span class="badge badge-warning">En Proceso</span>
                                    <?php else: ?>
                                        <span class="badge badge-info">Abierta</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="data-card-title"><?= Security::escape($ord['NombreCanal']) ?></div>

                            <div class="data-card-grid">
                                <div class="data-metric">
                                    <span class="data-metric-label">Canal Entrada</span>
                                    <span class="data-metric-val"><?= number_format((float)$ord['PesoCanalEntrada'], 2, ',', '.') ?> <small>kg</small></span>
                                </div>
                                <div class="data-metric">
                                    <span class="data-metric-label">Cortes Pesados</span>
                                    <span class="data-metric-val val-primary">
                                        <?= number_format((float)$ord['PesoTotalCortes'], 2, ',', '.') ?> <small>kg</small>
                                        <span style="font-size:0.75rem; color:var(--text-muted); font-weight:normal;">(<?= (int)$ord['TotalCortesPesados'] ?>)</span>
                                    </span>
                                </div>
                                <div class="data-metric" style="grid-column: span 2;">
                                    <span class="data-metric-label">Rendimiento Cárnico</span>
                                    <span class="data-metric-val <?= (float)$ord['RendimientoPct'] > 0 ? 'val-success' : '' ?>">
                                        <?= number_format((float)$ord['RendimientoPct'], 2, ',', '.') ?>%
                                    </span>
                                </div>
                            </div>

                            <div class="data-card-footer">
                                <a href="/deboning" class="btn btn-primary data-card-btn" style="text-decoration: none;">
                                    <svg class="icon" viewBox="0 0 24 24" style="width: 16px; height: 16px;"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                                    Ir a Desposte
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script src="/js/dashboard.js"></script>
<?php
$content = ob_get_clean();
$title = 'Dashboard Operativo - ST Despostadora';
$pageTitle = 'Dashboard Operativo';
$activeNav = 'home';

require __DIR__ . '/layout.php';
