/**
 * ST Despostadora - JavaScript del Módulo de Auditoría y Control Forense
 */

document.addEventListener('DOMContentLoaded', () => {
    const state = {
        activeTab: 'tab-general',
        pages: { general: 1, cuts: 1, yield: 1, security: 1 },
        filters: { datePreset: 'today', dateFrom: '', dateTo: '', module: '', user: '', severity: '', q: '' }
    };

    const elements = {
        tabs: document.querySelectorAll('.audit-tab-btn'),
        tabContents: document.querySelectorAll('.audit-tab-content'),
        presetDate: document.getElementById('filterPresetDate'),
        groupDateFrom: document.getElementById('groupDateFrom'),
        groupDateTo: document.getElementById('groupDateTo'),
        dateFrom: document.getElementById('filterDateFrom'),
        dateTo: document.getElementById('filterDateTo'),
        moduleFilter: document.getElementById('filterModule'),
        userFilter: document.getElementById('filterUser'),
        severityFilter: document.getElementById('filterSeverity'),
        searchQuery: document.getElementById('filterSearchQuery'),
        btnClearSearch: document.getElementById('btnClearSearch'),
        btnApply: document.getElementById('btnApplyFilters'),
        btnReset: document.getElementById('btnResetFilters'),
        btnExportCsv: document.getElementById('btnExportCsv'),
        // Traceability
        inputTrace: document.getElementById('inputTraceCode'),
        btnSearchTrace: document.getElementById('btnSearchTrace'),
        traceContainer: document.getElementById('traceResultsContainer'),
        traceContent: document.getElementById('traceChainContent'),
        traceEmptyNotice: document.getElementById('traceEmptyNotice'),
        // Modal
        modalJson: document.getElementById('modalAuditJson'),
        modalJsonTitle: document.getElementById('modalAuditJsonTitle'),
        modalJsonMeta: document.getElementById('modalAuditJsonMeta'),
        modalJsonBody: document.getElementById('modalAuditJsonBody'),
        btnCloseJson: document.getElementById('btnCloseAuditJson'),
        btnOkJson: document.getElementById('btnOkAuditJson'),
    };

    function formatDate(d) {
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }

    function getPresetDates(preset) {
        const now = new Date();
        switch (preset) {
            case 'today':
                const today = formatDate(now);
                return { from: today, to: today };
            case 'yesterday':
                const yest = new Date(now);
                yest.setDate(yest.getDate() - 1);
                const yestStr = formatDate(yest);
                return { from: yestStr, to: yestStr };
            case 'this_week':
                const firstDayWeek = new Date(now);
                firstDayWeek.setDate(now.getDate() - ((now.getDay() + 6) % 7));
                return { from: formatDate(firstDayWeek), to: formatDate(now) };
            case 'this_month':
                return { from: formatDate(new Date(now.getFullYear(), now.getMonth(), 1)), to: formatDate(now) };
            case 'all':
                return { from: '', to: '' };
            default:
                return { from: elements.dateFrom.value, to: elements.dateTo.value };
        }
    }

    const initDates = getPresetDates('today');
    state.filters.dateFrom = initDates.from;
    state.filters.dateTo = initDates.to;
    if (elements.dateFrom) elements.dateFrom.value = initDates.from;
    if (elements.dateTo) elements.dateTo.value = initDates.to;

    elements.presetDate.addEventListener('change', () => {
        const val = elements.presetDate.value;
        state.filters.datePreset = val;
        const isCustom = val === 'custom';
        elements.groupDateFrom.style.display = isCustom ? 'flex' : 'none';
        elements.groupDateTo.style.display = isCustom ? 'flex' : 'none';
        if (!isCustom) {
            const d = getPresetDates(val);
            state.filters.dateFrom = d.from;
            state.filters.dateTo = d.to;
            if (elements.dateFrom) elements.dateFrom.value = d.from;
            if (elements.dateTo) elements.dateTo.value = d.to;
        }
        applyFilters();
    });

    elements.tabs.forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.tab;
            if (!target || target === state.activeTab) return;
            elements.tabs.forEach(t => t.classList.remove('active'));
            elements.tabContents.forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            const targetEl = document.getElementById(target);
            if (targetEl) targetEl.classList.add('active');
            state.activeTab = target;
            loadTab();
        });
    });

    function applyFilters() {
        if (state.filters.datePreset === 'custom') {
            state.filters.dateFrom = elements.dateFrom.value;
            state.filters.dateTo = elements.dateTo.value;
        }
        state.filters.module = elements.moduleFilter.value;
        state.filters.user = elements.userFilter.value;
        state.filters.severity = elements.severityFilter.value;
        state.filters.q = elements.searchQuery.value.trim();
        state.pages = { general: 1, cuts: 1, yield: 1, security: 1 };
        loadKpis();
        loadTab();
    }

    elements.btnApply.addEventListener('click', applyFilters);

    elements.btnReset.addEventListener('click', () => {
        elements.presetDate.value = 'today';
        elements.groupDateFrom.style.display = 'none';
        elements.groupDateTo.style.display = 'none';
        const d = getPresetDates('today');
        elements.dateFrom.value = d.from;
        elements.dateTo.value = d.to;
        elements.moduleFilter.value = '';
        elements.userFilter.value = '';
        elements.severityFilter.value = '';
        elements.searchQuery.value = '';
        state.filters = { datePreset: 'today', dateFrom: d.from, dateTo: d.to, module: '', user: '', severity: '', q: '' };
        applyFilters();
    });

    elements.btnClearSearch.addEventListener('click', () => {
        elements.searchQuery.value = '';
        elements.searchQuery.focus();
        applyFilters();
    });

    elements.searchQuery.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); applyFilters(); }
    });

    elements.btnExportCsv.addEventListener('click', () => {
        const qParams = new URLSearchParams({
            modulo: state.filters.module,
            codusua: state.filters.user,
            severidad: state.filters.severity,
            fecha_desde: state.filters.dateFrom,
            fecha_hasta: state.filters.dateTo,
            q: state.filters.q
        });
        window.location.href = (window.APP_BASE_URL || '') + `/api/auditoria/exportar-csv?${qParams.toString()}`;
    });

    async function loadKpis() {
        try {
            const q = new URLSearchParams({ fecha_desde: state.filters.dateFrom, fecha_hasta: state.filters.dateTo });
            const res = await fetch(`/api/auditoria/kpis?${q.toString()}`);
            const json = await res.json();
            if (json.success && json.data) {
                const d = json.data;
                document.getElementById('kpiTotalEventos').textContent = Number(d.total_eventos || 0).toLocaleString();
                document.getElementById('kpiPesajesModificados').textContent = Number(d.pesajes_modificados || 0).toLocaleString();
                document.getElementById('kpiDeltaKilos').textContent = `${Number(d.delta_kilos_modificados || 0).toFixed(2)} kg`;
                document.getElementById('kpiOrdenesAnuladas').textContent = Number(d.ordenes_anuladas || 0).toLocaleString();
                document.getElementById('kpiDesviaciones').textContent = Number(d.desviaciones_rendimiento || 0).toLocaleString();
                document.getElementById('kpiEventosSeguridad').textContent = Number(d.eventos_seguridad || 0).toLocaleString();
            }
        } catch (e) { console.error('[AUDIT KPIS ERROR]', e); }
    }

    function setupPagination(key, loadFn) {
        const cap = key.charAt(0).toUpperCase() + key.slice(1);
        const btnPrev = document.getElementById(`btnPrev${cap}`);
        const btnNext = document.getElementById(`btnNext${cap}`);
        btnPrev.addEventListener('click', () => { if (state.pages[key] > 1) { state.pages[key]--; loadFn(); } });
        btnNext.addEventListener('click', () => { state.pages[key]++; loadFn(); });
    }

    setupPagination('general', loadGeneral);
    setupPagination('cuts', loadCuts);
    setupPagination('yield', loadYield);
    setupPagination('security', loadSecurity);

    function updatePaginationUI(key, data) {
        const cap = key.charAt(0).toUpperCase() + key.slice(1);
        const pageInfo = document.getElementById(`pageInfo${cap}`);
        const pageCurrent = document.getElementById(`pageCurrent${cap}`);
        const btnPrev = document.getElementById(`btnPrev${cap}`);
        const btnNext = document.getElementById(`btnNext${cap}`);
        if (!data || data.total === 0) {
            pageInfo.textContent = '0 registros encontrados';
            btnPrev.disabled = true;
            btnNext.disabled = true;
            return;
        }
        pageInfo.textContent = `Mostrando ${(data.page - 1) * data.per_page + 1} - ${Math.min(data.page * data.per_page, data.total)} de ${data.total}`;
        pageCurrent.textContent = data.page;
        btnPrev.disabled = data.page <= 1;
        btnNext.disabled = data.page >= data.total_pages;
    }

    function loadTab() {
        if (state.activeTab === 'tab-general') loadGeneral();
        else if (state.activeTab === 'tab-cuts') loadCuts();
        else if (state.activeTab === 'tab-yield') loadYield();
        else if (state.activeTab === 'tab-security') loadSecurity();
        else if (state.activeTab === 'tab-trace' && elements.inputTrace.value.trim() !== '') {
            searchTrace(elements.inputTrace.value.trim());
        }
    }

    async function loadGeneral() {
        const tbody = document.getElementById('tbodyGeneralEvents');
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding: 2rem;">Cargando bitácora general...</td></tr>`;
        try {
            const q = new URLSearchParams({
                page: state.pages.general, per_page: 25,
                modulo: state.filters.module, codusua: state.filters.user,
                severidad: state.filters.severity, fecha_desde: state.filters.dateFrom,
                fecha_hasta: state.filters.dateTo, q: state.filters.q
            });
            const res = await (await fetch(`/api/auditoria/eventos?${q.toString()}`)).json();
            if (!res.success || !res.data || res.data.items.length === 0) {
                tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding: 2rem; color: var(--text-muted);">No se encontraron eventos auditados.</td></tr>`;
                updatePaginationUI('general', { total: 0 });
                return;
            }
            tbody.innerHTML = res.data.items.map(item => {
                const sevClass = item.severidad === 'CRITICAL' ? 'sev-critical' : (item.severidad === 'WARN' ? 'sev-warn' : 'sev-info');
                const payload = item.detalle ? encodeURIComponent(JSON.stringify(item.detalle)) : '';
                return `<tr>
                    <td style="white-space:nowrap;font-size:0.8rem;color:var(--text-muted);">${item.fecha_hora}</td>
                    <td><span class="badge-code">${esc(item.modulo)}</span></td>
                    <td><strong>${esc(item.documento_id)}</strong></td>
                    <td>${esc(item.accion)}</td>
                    <td><span class="badge-sev ${sevClass}">${item.severidad}</span></td>
                    <td><div>${esc(item.nombre_usuario)}</div><small style="color:var(--text-muted);">${esc(item.codusua)}</small></td>
                    <td style="font-family:monospace;font-size:0.78rem;">${esc(item.ip)}</td>
                    <td style="text-align:center;">${item.detalle ? `<button type="button" class="btn btn-outline btn-view-json" style="padding:0.25rem 0.5rem;font-size:0.75rem;" data-action="${esc(item.accion)}" data-doc="${esc(item.documento_id)}" data-meta="${esc(item.fecha_hora)} | ${esc(item.nombre_usuario)}" data-payload="${payload}">👁️ Detalle</button>` : '-'}</td>
                </tr>`;
            }).join('');
            updatePaginationUI('general', res.data);
            attachJsonTriggers();
        } catch (e) { tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; color:#ef4444; padding:1.5rem;">Error de conexión.</td></tr>`; }
    }

    async function loadCuts() {
        const tbody = document.getElementById('tbodyCutsLog');
        tbody.innerHTML = `<tr><td colspan="11" style="text-align:center; padding: 2rem;">Cargando pesajes en mesa...</td></tr>`;
        try {
            const q = new URLSearchParams({
                page: state.pages.cuts, per_page: 25, codusua: state.filters.user,
                fecha_desde: state.filters.dateFrom, fecha_hasta: state.filters.dateTo, q: state.filters.q
            });
            const res = await (await fetch(`/api/auditoria/pesajes-log?${q.toString()}`)).json();
            if (!res.success || !res.data || res.data.items.length === 0) {
                tbody.innerHTML = `<tr><td colspan="11" style="text-align:center; padding: 2rem; color: var(--text-muted);">No se encontraron pesajes registrados.</td></tr>`;
                updatePaginationUI('cuts', { total: 0 });
                return;
            }
            tbody.innerHTML = res.data.items.map(item => {
                const delta = item.diferencia_peso;
                let deltaHtml = `<span class="delta-tag delta-zero">0.000 kg</span>`;
                if (delta > 0) deltaHtml = `<span class="delta-tag delta-plus">+${delta.toFixed(3)} kg</span>`;
                else if (delta < 0) deltaHtml = `<span class="delta-tag delta-minus">${delta.toFixed(3)} kg</span>`;
                return `<tr>
                    <td style="white-space:nowrap;font-size:0.8rem;color:var(--text-muted);">${item.fecha_hora}</td>
                    <td><strong>${esc(item.nro_orden)}</strong></td>
                    <td><span class="badge-code">${esc(item.nro_lote)}</span></td>
                    <td>${esc(item.nombre_corte)}</td>
                    <td><span class="badge-code">${esc(item.tipo_accion)}</span></td>
                    <td>${item.peso_anterior !== null ? `${item.peso_anterior.toFixed(3)} kg` : '-'}</td>
                    <td>${item.peso_nuevo !== null ? `<strong>${item.peso_nuevo.toFixed(3)} kg</strong>` : '-'}</td>
                    <td>${deltaHtml}</td>
                    <td style="font-size:0.82rem;max-width:180px;">${esc(item.motivo || '-')}</td>
                    <td><div>${esc(item.nombre_usuario)}</div><small style="color:var(--text-muted);">${esc(item.codusua)}</small></td>
                    <td style="font-family:monospace;font-size:0.78rem;">${esc(item.ip)}</td>
                </tr>`;
            }).join('');
            updatePaginationUI('cuts', res.data);
        } catch (e) { tbody.innerHTML = `<tr><td colspan="11" style="text-align:center; color:#ef4444; padding:1.5rem;">Error de conexión.</td></tr>`; }
    }

    async function loadYield() {
        const tbody = document.getElementById('tbodyYield');
        tbody.innerHTML = `<tr><td colspan="11" style="text-align:center; padding: 2rem;">Cargando análisis de rendimiento...</td></tr>`;
        try {
            const q = new URLSearchParams({
                page: state.pages.yield, per_page: 25,
                fecha_desde: state.filters.dateFrom, fecha_hasta: state.filters.dateTo, q: state.filters.q
            });
            const res = await (await fetch(`/api/auditoria/mermas-criticas?${q.toString()}`)).json();
            if (!res.success || !res.data || res.data.items.length === 0) {
                tbody.innerHTML = `<tr><td colspan="11" style="text-align:center; padding: 2rem; color: var(--text-muted);">No se encontraron órdenes cerradas.</td></tr>`;
                updatePaginationUI('yield', { total: 0 });
                return;
            }
            tbody.innerHTML = res.data.items.map(item => {
                let badge = `<span class="badge-sev sev-info">NORMAL</span>`;
                if (item.alerta_rendimiento && item.alerta_merma) badge = `<span class="badge-sev sev-critical">🔴 REND &amp; MERMA</span>`;
                else if (item.alerta_rendimiento) badge = `<span class="badge-sev sev-critical">🔴 RENDIMIENTO</span>`;
                else if (item.alerta_merma) badge = `<span class="badge-sev sev-warn">🟡 MERMA</span>`;
                return `<tr>
                    <td style="white-space:nowrap;font-size:0.8rem;color:var(--text-muted);">${item.fecha_cierre}</td>
                    <td><strong>${esc(item.nro_orden)}</strong></td>
                    <td>${esc(item.nombre_canal)}</td>
                    <td>${item.peso_canal.toFixed(2)} kg</td>
                    <td><strong>${item.peso_cortes.toFixed(2)} kg</strong></td>
                    <td>${item.peso_merma.toFixed(2)} kg</td>
                    <td style="font-weight:700;color:${item.alerta_rendimiento ? '#f87171' : '#4ade80'};">${item.rendimiento_pct.toFixed(2)}%</td>
                    <td style="font-weight:700;color:${item.alerta_merma ? '#f87171' : 'var(--text-primary)'};">${item.merma_pct.toFixed(2)}%</td>
                    <td>${badge}</td>
                    <td>${esc(item.usuario_cierre)}</td>
                    <td style="font-size:0.75rem;font-family:monospace;">${item.doc_descargo ? `D: ${esc(item.doc_descargo)}<br>` : ''}${item.doc_cargo ? `C: ${esc(item.doc_cargo)}` : ''}</td>
                </tr>`;
            }).join('');
            updatePaginationUI('yield', res.data);
        } catch (e) { tbody.innerHTML = `<tr><td colspan="11" style="text-align:center; color:#ef4444; padding:1.5rem;">Error de conexión.</td></tr>`; }
    }

    async function loadSecurity() {
        const tbody = document.getElementById('tbodySecurity');
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 2rem;">Cargando bitácora de seguridad...</td></tr>`;
        try {
            const q = new URLSearchParams({
                page: state.pages.security, per_page: 25, codusua: state.filters.user,
                fecha_desde: state.filters.dateFrom, fecha_hasta: state.filters.dateTo, q: state.filters.q
            });
            const res = await (await fetch(`/api/auditoria/seguridad?${q.toString()}`)).json();
            if (!res.success || !res.data || res.data.items.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 2rem; color: var(--text-muted);">No se encontraron eventos de seguridad.</td></tr>`;
                updatePaginationUI('security', { total: 0 });
                return;
            }
            tbody.innerHTML = res.data.items.map(item => {
                const sevClass = item.severidad === 'CRITICAL' ? 'sev-critical' : (item.severidad === 'WARN' ? 'sev-warn' : 'sev-info');
                return `<tr>
                    <td style="white-space:nowrap;font-size:0.8rem;color:var(--text-muted);">${item.fecha_hora}</td>
                    <td><strong>${esc(item.accion)}</strong></td>
                    <td><div>${esc(item.nombre_usuario)}</div><small style="color:var(--text-muted);">${esc(item.codusua)}</small></td>
                    <td style="font-family:monospace;font-size:0.78rem;">${esc(item.ip)}</td>
                    <td><span class="badge-sev ${sevClass}">${item.severidad}</span></td>
                    <td style="font-family:monospace;font-size:0.78rem;max-width:280px;word-break:break-all;">${esc(item.detalle ? JSON.stringify(item.detalle) : '-')}</td>
                </tr>`;
            }).join('');
            updatePaginationUI('security', res.data);
        } catch (e) { tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:#ef4444; padding:1.5rem;">Error de conexión.</td></tr>`; }
    }

    elements.btnSearchTrace.addEventListener('click', () => {
        const c = elements.inputTrace.value.trim();
        if (c) searchTrace(c);
    });
    elements.inputTrace.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); const c = elements.inputTrace.value.trim(); if (c) searchTrace(c); }
    });

    async function searchTrace(code) {
        elements.traceEmptyNotice.style.display = 'none';
        elements.traceContainer.style.display = 'block';
        elements.traceContent.innerHTML = `<div class="card" style="text-align:center; padding: 2rem;">Rastreando cadena para '<strong>${esc(code)}</strong>'...</div>`;
        try {
            const res = await (await fetch(`/api/auditoria/trazabilidad?code=${encodeURIComponent(code)}`)).json();
            if (!res.success || !res.data) {
                elements.traceContent.innerHTML = `<div class="card" style="text-align:center; padding: 2rem; color: #ef4444;">${esc(res.error || 'No se encontró trazabilidad.')}</div>`;
                return;
            }
            const d = res.data;
            const rec = d.recepcion;
            const desp = d.desposte;
            const cortes = d.cortes || [];
            const logs = d.historial_mesa || [];

            elements.traceContent.innerHTML = `
            <div class="trace-step-card">
                <div class="trace-step-header"><div class="trace-step-title">1. Recepción de Materia Prima</div><span class="badge-code">${rec ? esc(rec.NroRecepcion) : 'N/A'}</span></div>
                ${rec ? `<div class="trace-meta-grid">
                    <div class="trace-meta-item"><span class="trace-meta-label">Fecha</span><span class="trace-meta-val">${rec.FechaHora}</span></div>
                    <div class="trace-meta-item"><span class="trace-meta-label">Canal</span><span class="trace-meta-val">${esc(rec.CodProdCanal)}</span></div>
                    <div class="trace-meta-item"><span class="trace-meta-label">Método</span><span class="trace-meta-val">${esc(rec.MetodoEntrada)}</span></div>
                    <div class="trace-meta-item"><span class="trace-meta-label">Peso Neto</span><span class="trace-meta-val">${Number(rec.PesoTotalNeto).toFixed(2)} kg</span></div>
                    <div class="trace-meta-item"><span class="trace-meta-label">Doc ERP</span><span class="trace-meta-val">${esc(rec.DocEsperaNumeroD || '-')}</span></div>
                </div>` : '<p style="color:var(--text-muted);margin:0;">No vinculada.</p>'}
            </div>
            <div class="trace-step-card">
                <div class="trace-step-header"><div class="trace-step-title">2. Orden de Desposte</div><span class="badge-code">${esc(desp.NroOrdenDesposte)}</span></div>
                <div class="trace-meta-grid">
                    <div class="trace-meta-item"><span class="trace-meta-label">Mesa</span><span class="trace-meta-val">${esc(desp.LineaMesa)}</span></div>
                    <div class="trace-meta-item"><span class="trace-meta-label">Estado</span><span class="trace-meta-val"><strong>${esc(desp.Estado)}</strong></span></div>
                    <div class="trace-meta-item"><span class="trace-meta-label">Inicio / Cierre</span><span class="trace-meta-val">${desp.FechaHoraInicio} | ${desp.FechaHoraCierre || 'Activo'}</span></div>
                    <div class="trace-meta-item"><span class="trace-meta-label">Peso Canal</span><span class="trace-meta-val">${Number(desp.PesoCanalEntrada).toFixed(2)} kg</span></div>
                    <div class="trace-meta-item"><span class="trace-meta-label">Rendimiento</span><span class="trace-meta-val" style="color:#4ade80;font-weight:700;">${Number(desp.RendimientoPct).toFixed(2)}%</span></div>
                    <div class="trace-meta-item"><span class="trace-meta-label">Merma Real</span><span class="trace-meta-val" style="color:#f87171;font-weight:700;">${Number(desp.MermaPct).toFixed(2)}%</span></div>
                    <div class="trace-meta-item"><span class="trace-meta-label">Doc Descargo</span><span class="trace-meta-val">${esc(desp.DocDescargoNumeroD || '-')}</span></div>
                    <div class="trace-meta-item"><span class="trace-meta-label">Doc Cargo</span><span class="trace-meta-val">${esc(desp.DocCargoNumeroD || '-')}</span></div>
                </div>
            </div>
            <div class="trace-step-card">
                <div class="trace-step-header"><div class="trace-step-title">3. Cortes y Lotes Asignados (${cortes.length})</div></div>
                <div class="audit-table-wrapper" style="max-height:220px;overflow-y:auto;">
                    <table class="audit-table"><thead><tr><th>#</th><th>Lote</th><th>Corte</th><th>Peso Neto</th><th>Elaboración</th><th>Vencimiento</th></tr></thead>
                    <tbody>${cortes.map(c => `<tr style="${d.lote_especifico && c.NroLote === d.lote_especifico ? 'background:rgba(244,107,58,0.15);font-weight:700;' : ''}"><td>${c.NroItem}</td><td><span class="badge-code">${esc(c.NroLote)}</span></td><td>${esc(c.NombreCorte)}</td><td>${Number(c.PesoNeto).toFixed(3)} kg</td><td>${c.FechaElaboracion}</td><td>${c.FechaVencimiento}</td></tr>`).join('')}</tbody></table>
                </div>
            </div>
            <div class="trace-step-card">
                <div class="trace-step-header"><div class="trace-step-title">4. Auditoría en Mesa (${logs.length} eventos)</div></div>
                ${logs.length > 0 ? `<div class="audit-table-wrapper"><table class="audit-table"><thead><tr><th>Fecha</th><th>Acción</th><th>Lote</th><th>Peso Ant.</th><th>Peso Nuevo</th><th>Delta</th><th>Motivo</th><th>Usuario</th></tr></thead>
                <tbody>${logs.map(l => `<tr><td>${l.FechaHora}</td><td>${esc(l.TipoAccion)}</td><td>${esc(l.NroLote)}</td><td>${l.PesoAnterior !== null ? Number(l.PesoAnterior).toFixed(3) : '-'}</td><td>${l.PesoNuevo !== null ? Number(l.PesoNuevo).toFixed(3) : '-'}</td><td>${l.DiferenciaPeso !== null ? (l.DiferenciaPeso > 0 ? `+${l.DiferenciaPeso.toFixed(3)}` : l.DiferenciaPeso.toFixed(3)) : '0.000'} kg</td><td>${esc(l.Motivo || '-')}</td><td>${esc(l.CodUsua)}</td></tr>`).join('')}</tbody></table></div>` : '<p style="color:var(--text-muted);margin:0;">Sin modificaciones de peso ni anulaciones registradas.</p>'}
            </div>`;
        } catch (e) { elements.traceContent.innerHTML = `<div class="card" style="text-align:center; padding: 2rem; color: #ef4444;">Error consultando trazabilidad.</div>`; }
    }

    function attachJsonTriggers() {
        document.querySelectorAll('.btn-view-json').forEach(btn => {
            btn.addEventListener('click', () => {
                elements.modalJsonTitle.textContent = `${btn.dataset.action || 'Detalle'}: ${btn.dataset.doc || ''}`;
                elements.modalJsonMeta.textContent = btn.dataset.meta || '';
                try {
                    const parsed = JSON.parse(decodeURIComponent(btn.dataset.payload || '{}'));
                    let rows = '';
                    for (const [k, v] of Object.entries(parsed)) {
                        rows += `<tr><td class="diff-key">${esc(k)}</td><td>${esc(typeof v === 'object' && v !== null ? JSON.stringify(v) : String(v))}</td></tr>`;
                    }
                    elements.modalJsonBody.innerHTML = `<table class="diff-table"><thead><tr><th>Parámetro / Campo</th><th>Valor Registrado</th></tr></thead><tbody>${rows}</tbody></table>`;
                } catch (e) { elements.modalJsonBody.textContent = decodeURIComponent(btn.dataset.payload || ''); }
                elements.modalJson.style.display = 'flex';
            });
        });
    }

    elements.btnCloseJson.addEventListener('click', () => { elements.modalJson.style.display = 'none'; });
    elements.btnOkJson.addEventListener('click', () => { elements.modalJson.style.display = 'none'; });
    elements.modalJson.addEventListener('click', (e) => { if (e.target === elements.modalJson) elements.modalJson.style.display = 'none'; });

    function esc(s) {
        if (!s) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    loadGeneral();
});
