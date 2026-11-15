<?php
declare(strict_types=1);

use Config\Security;

ob_start();
?>
<link rel="stylesheet" href="/css/auditoria.css">

<div class="audit-container">
    <!-- Header del Módulo -->
    <div class="card" style="margin-bottom: 0;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 class="card-title" style="margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem;">
                    <svg class="icon" viewBox="0 0 24 24" style="stroke: var(--primary); width: 22px; height: 22px;">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        <path d="M9 12l2 2 4-4"></path>
                    </svg>
                    Auditoría, Trazabilidad y Control Forense
                </h3>
                <p class="card-desc">Supervisión inmutable de pesajes, rendimiento cárnico, modificaciones en mesa, correlativos y seguridad.</p>
            </div>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(244, 107, 58, 0.15); color: var(--primary); padding: 0.35rem 0.85rem; border-radius: 9999px; font-size: 0.82rem; font-weight: 700; border: 1px solid rgba(244, 107, 58, 0.3);">
                    Acceso Nivel 01 Exclusivo
                </span>
                <button type="button" id="btnExportCsv" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.85rem; font-size: 0.85rem;">
                    <svg class="icon" viewBox="0 0 24 24" style="width: 16px; height: 16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Exportar CSV
                </button>
            </div>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="audit-kpi-grid">
        <div class="audit-kpi-card kpi-info">
            <div class="audit-kpi-header">
                <span>Eventos Totales</span>
                <svg class="icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path></svg>
            </div>
            <div class="audit-kpi-value" id="kpiTotalEventos"><?= number_format((float)($kpis['total_eventos'] ?? 0), 0) ?></div>
            <div class="audit-kpi-subtitle">Registros auditados en el período</div>
        </div>

        <div class="audit-kpi-card kpi-warn">
            <div class="audit-kpi-header">
                <span>Pesajes Modificados</span>
                <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            </div>
            <div class="audit-kpi-value" id="kpiPesajesModificados"><?= number_format((float)($kpis['pesajes_modificados'] ?? 0), 0) ?></div>
            <div class="audit-kpi-subtitle">Delta total: <strong id="kpiDeltaKilos"><?= number_format((float)($kpis['delta_kilos_modificados'] ?? 0), 2) ?> kg</strong></div>
        </div>

        <div class="audit-kpi-card kpi-crit">
            <div class="audit-kpi-header">
                <span>Órdenes Anuladas</span>
                <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
            </div>
            <div class="audit-kpi-value" id="kpiOrdenesAnuladas"><?= number_format((float)($kpis['ordenes_anuladas'] ?? 0), 0) ?></div>
            <div class="audit-kpi-subtitle">Despostes cancelados con restitución</div>
        </div>

        <div class="audit-kpi-card kpi-crit">
            <div class="audit-kpi-header">
                <span>Mermas / Rend. Críticos</span>
                <svg class="icon" viewBox="0 0 24 24"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"></polygon><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            </div>
            <div class="audit-kpi-value" id="kpiDesviaciones"><?= number_format((float)($kpis['desviaciones_rendimiento'] ?? 0), 0) ?></div>
            <div class="audit-kpi-subtitle">Órdenes con rendimiento &lt;75% o &gt;102%</div>
        </div>

        <div class="audit-kpi-card kpi-warn">
            <div class="audit-kpi-header">
                <span>Seguridad &amp; Accesos</span>
                <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            </div>
            <div class="audit-kpi-value" id="kpiEventosSeguridad"><?= number_format((float)($kpis['eventos_seguridad'] ?? 0), 0) ?></div>
            <div class="audit-kpi-subtitle">Logins fallidos, bloqueos y eventos 403</div>
        </div>
    </div>

    <!-- Pestañas de Navegación -->
    <div class="audit-tabs-wrapper">
        <button type="button" class="audit-tab-btn active" data-tab="tab-general">
            <svg class="icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            Bitácora General
        </button>
        <button type="button" class="audit-tab-btn" data-tab="tab-cuts">
            <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            Pesajes en Mesa
        </button>
        <button type="button" class="audit-tab-btn" data-tab="tab-yield">
            <svg class="icon" viewBox="0 0 24 24"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"></polygon><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            Mermas Críticas
        </button>
        <button type="button" class="audit-tab-btn" data-tab="tab-security">
            <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            Seguridad y Accesos
        </button>
        <button type="button" class="audit-tab-btn" data-tab="tab-trace">
            <svg class="icon" viewBox="0 0 24 24"><line x1="6" y1="3" x2="6" y2="15"></line><circle cx="18" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><path d="M18 9a9 9 0 0 1-9 9"></path></svg>
            Trazabilidad Cárnica
        </button>
    </div>

    <!-- Barra de Filtros Dinámicos -->
    <div class="audit-filter-bar">
        <div class="audit-filter-group" style="flex: 1 1 140px;">
            <label for="filterPresetDate">Período Rápido</label>
            <select id="filterPresetDate">
                <option value="today" selected>Hoy</option>
                <option value="yesterday">Ayer</option>
                <option value="this_week">Esta Semana</option>
                <option value="this_month">Este Mes</option>
                <option value="all">Todo el Histórico</option>
                <option value="custom">Personalizado...</option>
            </select>
        </div>

        <div class="audit-filter-group" id="groupDateFrom" style="display: none;">
            <label for="filterDateFrom">Fecha Desde</label>
            <input type="date" id="filterDateFrom" value="<?= date('Y-m-d') ?>">
        </div>

        <div class="audit-filter-group" id="groupDateTo" style="display: none;">
            <label for="filterDateTo">Fecha Hasta</label>
            <input type="date" id="filterDateTo" value="<?= date('Y-m-d') ?>">
        </div>

        <div class="audit-filter-group" id="groupModuleFilter">
            <label for="filterModule">Módulo</label>
            <select id="filterModule">
                <option value="">Todos los Módulos</option>
                <?php foreach (($modules ?? []) as $mod): ?>
                    <option value="<?= Security::escape($mod) ?>"><?= Security::escape($mod) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="audit-filter-group" id="groupUserFilter">
            <label for="filterUser">Usuario</label>
            <select id="filterUser">
                <option value="">Todos los Usuarios</option>
                <?php foreach (($users ?? []) as $u): ?>
                    <option value="<?= Security::escape($u['CodUsua']) ?>">
                        <?= Security::escape($u['CodUsua']) ?> - <?= Security::escape($u['Descrip']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="audit-filter-group" id="groupSeverityFilter">
            <label for="filterSeverity">Severidad</label>
            <select id="filterSeverity">
                <option value="">Todas las Severidades</option>
                <option value="CRITICAL">🔴 Solo Alertas Críticas</option>
                <option value="WARN">🟡 Advertencias</option>
                <option value="INFO">🟢 Informativos</option>
            </select>
        </div>

        <div class="audit-filter-group" style="flex: 2 1 220px;">
            <label for="filterSearchQuery">Buscar (Lote, Orden, Doc, IP...)</label>
            <div style="display: flex; gap: 0.35rem;">
                <input type="text" id="filterSearchQuery" placeholder="Ej: DESP-, LOT-, REC-, 192.168...">
                <button type="button" id="btnClearSearch" class="btn btn-outline" style="padding: 0 0.6rem;" title="Limpiar búsqueda">&times;</button>
            </div>
        </div>

        <div class="audit-filter-actions">
            <button type="button" id="btnApplyFilters" class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.85rem;">
                Filtrar
            </button>
            <button type="button" id="btnResetFilters" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.85rem;">
                Restablecer
            </button>
        </div>
    </div>

    <!-- PESTAÑA 1: Bitácora General -->
    <div id="tab-general" class="audit-tab-content active">
        <div class="audit-table-card">
            <div class="audit-table-wrapper">
                <table class="audit-table" id="tableGeneralEvents">
                    <thead>
                        <tr>
                            <th>Fecha/Hora UTC</th>
                            <th>Módulo</th>
                            <th>Documento / Clave</th>
                            <th>Acción</th>
                            <th>Severidad</th>
                            <th>Usuario</th>
                            <th>IP</th>
                            <th style="text-align: center;">Detalle</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyGeneralEvents">
                        <tr><td colspan="8" style="text-align:center; padding: 2rem;">Cargando bitácora general...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="audit-pagination-bar" id="paginationGeneral">
                <span id="pageInfoGeneral">Mostrando registros...</span>
                <div class="audit-page-controls">
                    <button type="button" class="audit-page-btn" id="btnPrevGeneral" disabled>&laquo; Anterior</button>
                    <span id="pageCurrentGeneral" style="font-weight: 700; color: var(--text-primary);">1</span>
                    <button type="button" class="audit-page-btn" id="btnNextGeneral" disabled>Siguiente &raquo;</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PESTAÑA 2: Pesajes en Mesa -->
    <div id="tab-cuts" class="audit-tab-content">
        <div class="audit-table-card">
            <div class="audit-table-wrapper">
                <table class="audit-table" id="tableCutsLog">
                    <thead>
                        <tr>
                            <th>Fecha/Hora</th>
                            <th>Nro. Orden</th>
                            <th>Lote</th>
                            <th>Corte</th>
                            <th>Tipo Acción</th>
                            <th>Peso Anterior</th>
                            <th>Peso Nuevo</th>
                            <th>Delta (Kg)</th>
                            <th>Motivo Declarado</th>
                            <th>Operador</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyCutsLog">
                        <tr><td colspan="11" style="text-align:center; padding: 2rem;">Cargando pesajes en mesa...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="audit-pagination-bar" id="paginationCuts">
                <span id="pageInfoCuts">Mostrando registros...</span>
                <div class="audit-page-controls">
                    <button type="button" class="audit-page-btn" id="btnPrevCuts" disabled>&laquo; Anterior</button>
                    <span id="pageCurrentCuts" style="font-weight: 700; color: var(--text-primary);">1</span>
                    <button type="button" class="audit-page-btn" id="btnNextCuts" disabled>Siguiente &raquo;</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PESTAÑA 3: Mermas y Rendimientos Críticos -->
    <div id="tab-yield" class="audit-tab-content">
        <div class="audit-table-card">
            <div class="audit-table-wrapper">
                <table class="audit-table" id="tableYield">
                    <thead>
                        <tr>
                            <th>Fecha Cierre</th>
                            <th>Nro. Orden</th>
                            <th>Especie / Canal</th>
                            <th>Peso Canal</th>
                            <th>Peso Cortes</th>
                            <th>Peso Merma</th>
                            <th>Rendimiento</th>
                            <th>Merma Real</th>
                            <th>Alerta Detectada</th>
                            <th>Usuario Cierre</th>
                            <th>Cruce ERP</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyYield">
                        <tr><td colspan="11" style="text-align:center; padding: 2rem;">Cargando análisis de rendimiento...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="audit-pagination-bar" id="paginationYield">
                <span id="pageInfoYield">Mostrando registros...</span>
                <div class="audit-page-controls">
                    <button type="button" class="audit-page-btn" id="btnPrevYield" disabled>&laquo; Anterior</button>
                    <span id="pageCurrentYield" style="font-weight: 700; color: var(--text-primary);">1</span>
                    <button type="button" class="audit-page-btn" id="btnNextYield" disabled>Siguiente &raquo;</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PESTAÑA 4: Seguridad y Accesos -->
    <div id="tab-security" class="audit-tab-content">
        <div class="audit-table-card">
            <div class="audit-table-wrapper">
                <table class="audit-table" id="tableSecurity">
                    <thead>
                        <tr>
                            <th>Fecha/Hora UTC</th>
                            <th>Evento de Seguridad</th>
                            <th>Usuario / Cuenta</th>
                            <th>IP Cliente</th>
                            <th>Severidad</th>
                            <th>Detalle Técnico</th>
                        </tr>
                    </thead>
                    <tbody id="tbodySecurity">
                        <tr><td colspan="6" style="text-align:center; padding: 2rem;">Cargando bitácora de seguridad...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="audit-pagination-bar" id="paginationSecurity">
                <span id="pageInfoSecurity">Mostrando registros...</span>
                <div class="audit-page-controls">
                    <button type="button" class="audit-page-btn" id="btnPrevSecurity" disabled>&laquo; Anterior</button>
                    <span id="pageCurrentSecurity" style="font-weight: 700; color: var(--text-primary);">1</span>
                    <button type="button" class="audit-page-btn" id="btnNextSecurity" disabled>Siguiente &raquo;</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PESTAÑA 5: Trazabilidad Cárnica -->
    <div id="tab-trace" class="audit-tab-content">
        <div class="card" style="margin-bottom: 1rem;">
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                <input type="text" id="inputTraceCode" placeholder="Ingrese Nro de Lote (LOT-...), Orden (DESP-...) o Recepción (REC-...)" style="flex: 1 1 300px; background: #141a22; border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px; padding: 0.6rem 0.85rem; font-size: 0.95rem;">
                <button type="button" id="btnSearchTrace" class="btn btn-primary" style="padding: 0.6rem 1.25rem;">
                    <svg class="icon" viewBox="0 0 24 24" style="width: 16px; height: 16px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    Rastrear Cadena Forense
                </button>
            </div>
        </div>

        <div id="traceResultsContainer" style="display: none;">
            <div class="trace-chain" id="traceChainContent">
                <!-- Se inyecta dinámicamente con JS -->
            </div>
        </div>

        <div id="traceEmptyNotice" class="card" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
            <svg class="icon" viewBox="0 0 24 24" style="width: 42px; height: 42px; stroke: var(--text-muted); margin-bottom: 0.75rem;"><line x1="6" y1="3" x2="6" y2="15"></line><circle cx="18" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><path d="M18 9a9 9 0 0 1-9 9"></path></svg>
            <p style="margin: 0; font-size: 0.95rem;">Ingrese un número de lote de corte, orden de desposte o recepción para reconstruir la historia cárnica completa.</p>
        </div>
    </div>
</div>

<!-- MODAL: Inspección de Payload JSON / Diff -->
<div id="modalAuditJson" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(20,15,12,0.85); z-index:120; align-items:center; justify-content:center; padding:1rem; backdrop-filter:blur(4px);">
    <div class="card" style="width:100%; max-width:680px; max-height:85vh; display:flex; flex-direction:column; box-shadow:0 16px 40px rgba(0,0,0,0.7); border:1px solid var(--border-color); padding:1.25rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
            <div>
                <h3 style="font-size:1.15rem; font-weight:700; color:var(--text-primary); margin:0;" id="modalAuditJsonTitle">
                    Detalle de Evento Auditado
                </h3>
                <span style="font-size:0.8rem; color:var(--text-muted);" id="modalAuditJsonMeta">...</span>
            </div>
            <button type="button" class="btn btn-outline" id="btnCloseAuditJson" style="padding:0 8px; font-size:1.3rem; line-height:1; border:none; color:var(--text-muted);">&times;</button>
        </div>

        <div class="json-diff-container" id="modalAuditJsonBody">
            <!-- Se inyecta con JS -->
        </div>

        <div style="display:flex; justify-content:flex-end; margin-top:1rem; padding-top:0.75rem; border-top:1px solid var(--border-color);">
            <button type="button" class="btn btn-primary" id="btnOkAuditJson" style="padding:0.4rem 1.25rem;">
                Cerrar
            </button>
        </div>
    </div>
</div>

<script src="/js/auditoria.js"></script>
<?php
$content = ob_get_clean();
$title = 'Auditoría y Control Forense - ST Despostadora';
$pageTitle = 'Auditoría y Control Forense';
$activeNav = 'auditoria';

require __DIR__ . '/layout.php';
