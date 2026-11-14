<?php
declare(strict_types=1);

use Config\Security;
use Src\Deboning\CorrelativeService;

$lotePrefijo = $lotePrefijo ?? 'LOT-';
$margenDefault = $margenDefault ?? '30.00';
$instances = $instances ?? [];

ob_start();
?>
<div class="config-wrapper">
    <div class="card" style="margin-bottom: 1.5rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 class="card-title" style="margin-bottom: 0.25rem;">Configuración General y Maestros</h3>
                <p class="card-desc">Parámetros operativos para desposte, correlativos de hasta 20 caracteres, lotes y cortes cárnicos.</p>
            </div>
            <span style="background: rgba(244, 107, 58, 0.15); color: var(--primary); padding: 0.35rem 0.85rem; border-radius: 9999px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(244, 107, 58, 0.3);">
                Nivel Administrador (<?= Security::escape($user['nivel'] ?? '01') ?>)
            </span>
        </div>

        <!-- Pestañas de Navegación de Configuración -->
        <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; flex-wrap: wrap;">
            <button type="button" class="tab-btn active" data-tab="tab-general">
                <svg class="icon" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                Depósito y Lotes
            </button>
            <button type="button" class="tab-btn" data-tab="tab-correlativos">
                <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                Correlativos del Sistema
            </button>
            <button type="button" class="tab-btn" data-tab="tab-especies">
                <svg class="icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                Especies y Matriz de Cortes
            </button>
            <button type="button" class="tab-btn" data-tab="tab-mesas">
                <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                Líneas y Mesas
            </button>
        </div>
    </div>

    <!-- SECCIÓN 1: Depósito y Lotes -->
    <div id="tab-general" class="config-tab-panel active">
        <div class="card">
            <h4 style="margin-bottom: 1rem; color: var(--text-primary); font-size: 1.1rem;">Parámetros Operativos Base</h4>
            <form id="formConfigGeneral">
                <input type="hidden" name="csrf_token" value="<?= Security::escape($csrf_token) ?>">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label" for="default_codubic">Depósito Operativo de Desposte (SADEPO)</label>
                        <select name="default_codubic" id="default_codubic" class="form-input" required>
                            <option value="">-- Seleccione Depósito --</option>
                            <?php foreach ($deposits as $dep): ?>
                                <option value="<?= Security::escape($dep['CodUbic']) ?>" <?= $defaultCodUbic === $dep['CodUbic'] ? 'selected' : '' ?>>
                                    [<?= Security::escape($dep['CodUbic']) ?>] <?= Security::escape($dep['Descrip']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 0.35rem;">
                            Depósito del ERP donde se procesarán las recepciones y despostes internos.
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="lote_prefijo">Prefijo Automático para Números de Lote</label>
                        <input type="text" name="lote_prefijo" id="lote_prefijo" class="form-input" value="<?= Security::escape($lotePrefijo) ?>" maxlength="10" required>
                        <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 0.35rem;">
                            Ejemplo resultante: <strong><span id="previewLote"><?= Security::escape($lotePrefijo) . date('Ymd') . '-0001-01' ?></span></strong>
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="margen_default">Margen de Rentabilidad Objetivo (%)</label>
                        <input type="number" step="0.0001" min="0" max="100" name="margen_default" id="margen_default" class="form-input" value="<?= Security::escape(number_format((float)$margenDefault, 4, '.', '')) ?>" required>
                        <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 0.35rem;">
                            Utilidad sugerida para calcular el precio de venta real tras absorber la merma.
                        </small>
                    </div>
                </div>

                <div style="margin-top: 1.5rem; text-align: right;">
                    <button type="submit" class="btn btn-primary" style="width: auto; min-width: 180px;">
                        Guardar Parámetros
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- SECCIÓN 2: Correlativos del Sistema -->
    <div id="tab-correlativos" class="config-tab-panel" style="display: none;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap: 1.5rem;">
            <?php foreach ($correlatives as $corr): ?>
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; position: relative;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div>
                            <h4 style="margin: 0; font-size: 1.05rem; color: var(--text-primary);"><?= Security::escape($corr['Descripcion']) ?></h4>
                            <span style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">TIPO: <?= Security::escape($corr['Tipo']) ?></span>
                        </div>
                        <span class="badge <?= (int)$corr['Activo'] === 1 ? 'badge-success' : 'badge-warning' ?>">
                            <?= (int)$corr['Activo'] === 1 ? 'ACTIVO' : 'INACTIVO' ?>
                        </span>
                    </div>

                    <form class="form-correlativo" data-tipo="<?= Security::escape($corr['Tipo']) ?>">
                        <input type="hidden" name="csrf_token" value="<?= Security::escape($csrf_token) ?>">
                        <input type="hidden" name="tipo" value="<?= Security::escape($corr['Tipo']) ?>">

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 0.75rem;">Prefijo (Máx 10)</label>
                                <input type="text" name="prefijo" id="pref_<?= Security::escape($corr['Tipo']) ?>" class="form-input corr-input" 
                                       value="<?= Security::escape($corr['Prefijo']) ?>" maxlength="10" required 
                                       data-tipo="<?= Security::escape($corr['Tipo']) ?>">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 0.75rem;">Longitud Total (Máx 20)</label>
                                <input type="number" name="longitud" id="long_<?= Security::escape($corr['Tipo']) ?>" class="form-input corr-input" 
                                       value="<?= (int)$corr['Longitud'] ?>" min="8" max="20" required 
                                       data-tipo="<?= Security::escape($corr['Tipo']) ?>">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 0.75rem;">Último N° Emitido</label>
                                <input type="number" name="ultimo_numero" id="num_<?= Security::escape($corr['Tipo']) ?>" class="form-input corr-input" 
                                       value="<?= (int)$corr['UltimoNumero'] ?>" min="0" required 
                                       data-tipo="<?= Security::escape($corr['Tipo']) ?>">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 0.75rem;">Estado</label>
                                <select name="activo" class="form-input">
                                    <option value="1" <?= (int)$corr['Activo'] === 1 ? 'selected' : '' ?>>Activo</option>
                                    <option value="0" <?= (int)$corr['Activo'] === 0 ? 'selected' : '' ?>>Inactivo</option>
                                </select>
                            </div>
                        </div>

                        <!-- Previsualización en Vivo -->
                        <div style="background: var(--bg-main); border: 1px dashed var(--border-color); border-radius: 8px; padding: 0.65rem 0.85rem; margin-bottom: 1rem;">
                            <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">Siguiente correlativo a emitir:</span>
                            <span id="prev_<?= Security::escape($corr['Tipo']) ?>" style="font-family: monospace; font-size: 1.05rem; font-weight: 700; color: var(--primary);">
                                <?= Security::escape(Src\Deboning\CorrelativeService::preview((string)$corr['Prefijo'], (int)$corr['Longitud'], (int)$corr['UltimoNumero'] + 1)) ?>
                            </span>
                        </div>

                        <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1rem; font-size: 0.9rem;">
                            Guardar Correlativo
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- SECCIÓN 3: Especies Cárnicas y Matriz de Cortes -->
    <div id="tab-especies" class="config-tab-panel" style="display: none;">
        <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem;">
            
            <!-- Formulario para Registrar / Editar Especie de Canal -->
            <div class="card">
                <h4 style="margin-bottom: 1rem; color: var(--text-primary); font-size: 1.1rem; display: flex; align-items: center; justify-content: space-between;">
                    <span>Configurar Especie / Canal de Entrada</span>
                    <button type="button" class="btn" id="btnNuevaEspecie" style="background: var(--bg-sidebar); border: 1px solid var(--border-color); color: var(--text-secondary); font-size: 0.85rem; padding: 0.4rem 0.75rem;">
                        + Nueva Especie
                    </button>
                </h4>

                <form id="formEspecie">
                    <input type="hidden" name="csrf_token" value="<?= Security::escape($csrf_token) ?>">

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Filtrar Instancia ERP</label>
                            <select id="filtro_instancia" class="form-input">
                                <option value="">-- Todas las Instancias --</option>
                                <?php foreach ($instances as $inst): ?>
                                    <option value="<?= Security::escape($inst['CodInst']) ?>"><?= Security::escape($inst['Descrip']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Producto Canal (SAPROD)</label>
                            <select name="codprod" id="sel_codprod" class="form-input" required>
                                <option value="">-- Seleccione Producto Canal --</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Producto para Merma / Desperdicio</label>
                            <select name="codprod_merma" id="sel_codprod_merma" class="form-input" required>
                                <option value="">-- Seleccione Producto Merma --</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Margen Utilidad Default (%)</label>
                            <input type="number" step="0.0001" name="margen" id="esp_margen" class="form-input" value="30.0000" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Venc. Refrigerado (Días)</label>
                            <input type="number" name="dias_refrig" id="esp_refrig" class="form-input" value="15" min="1" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Venc. Congelado (Días)</label>
                            <input type="number" name="dias_congel" id="esp_congel" class="form-input" value="180" min="1" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Estado</label>
                            <select name="activo" id="esp_activo" class="form-input">
                                <option value="1">Activa</option>
                                <option value="0">Inactiva</option>
                            </select>
                        </div>
                    </div>

                    <div style="text-align: right;">
                        <button type="submit" class="btn btn-primary" style="width: auto; min-width: 160px;">
                            Guardar Especie
                        </button>
                    </div>
                </form>
            </div>

            <!-- Listado de Especies Configuradas y sus Cortes -->
            <div class="card">
                <h4 style="margin-bottom: 1rem; color: var(--text-primary); font-size: 1.1rem;">Especies Habilitadas para Desposte</h4>

                <?php if (empty($species)): ?>
                    <p style="color: var(--text-muted); font-size: 0.95rem; text-align: center; padding: 2rem;">
                        No hay especies registradas aún. Seleccione una canal y un producto de merma en el formulario superior para habilitarla.
                    </p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($species as $esp): ?>
                            <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 10px; padding: 1.25rem;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;">
                                    <div>
                                        <h5 style="margin: 0; font-size: 1.1rem; color: var(--text-primary); font-weight: 700;">
                                            [<?= Security::escape($esp['CodProd']) ?>] <?= Security::escape($esp['NombreEspecie']) ?>
                                        </h5>
                                        <div style="display: flex; gap: 1rem; font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.35rem; flex-wrap: wrap;">
                                            <span>Unidad: <strong><?= Security::escape($esp['Unidad']) ?></strong></span>
                                            <span>Costo Actual: <strong>$<?= number_format((float)$esp['CostActCanal'], 4) ?></strong></span>
                                            <span>Merma Asignada: <strong style="color: var(--danger);"><?= Security::escape($esp['NombreMerma'] ?: $esp['CodProdMerma']) ?></strong></span>
                                            <span>Cortes Asociados: <strong style="color: var(--primary);"><?= (int)$esp['TotalCortes'] ?></strong></span>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button type="button" class="btn btn-admin-cortes" style="background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 0.85rem; padding: 0.4rem 0.75rem;" 
                                                data-canal="<?= Security::escape($esp['CodProd']) ?>" data-nombre="<?= Security::escape($esp['NombreEspecie']) ?>">
                                            Gestionar Cortes
                                        </button>
                                    </div>
                                </div>

                                <!-- Contenedor dinámico de cortes para esta especie -->
                                <div id="panel_cortes_<?= Security::escape($esp['CodProd']) ?>" style="display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed var(--border-color);">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                        <h6 style="margin: 0; font-size: 0.95rem; color: var(--accent);">Matriz de Cortes Asignados</h6>
                                        <button type="button" class="btn btn-primary btn-abrir-modal-corte" style="padding: 0.35rem 0.65rem; font-size: 0.8rem; width: auto;" data-canal="<?= Security::escape($esp['CodProd']) ?>">
                                            + Asociar Corte
                                        </button>
                                    </div>
                                    <div id="lista_cortes_<?= Security::escape($esp['CodProd']) ?>">
                                        <!-- Se carga por AJAX -->
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- SECCIÓN 4: Líneas y Mesas de Desposte -->
    <div id="tab-mesas" class="config-tab-panel" style="display: none;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap: 1.5rem;">
            
            <!-- Formulario para Crear / Editar Mesa -->
            <div class="card">
                <h4 style="margin-bottom: 0.5rem; color: var(--text-primary); font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <svg class="icon" viewBox="0 0 24 24" style="color:var(--primary); width:20px; height:20px;"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    <span id="tituloFormMesa">Nueva Línea o Mesa Operativa</span>
                </h4>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.25rem;">
                    Configure las mesas o líneas operativas de la sala. <strong>Si no crea ninguna mesa</strong>, el sistema abrirá las órdenes de desposte sin requerir asignación de mesa.
                </p>

                <form id="formMesa">
                    <input type="hidden" name="csrf_token" value="<?= Security::escape($csrf_token) ?>">
                    <input type="hidden" name="id" id="mesa_id" value="">

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label" for="mesa_nombre">Identificador o Nombre de la Mesa *</label>
                        <input type="text" name="nombre" id="mesa_nombre" class="form-input" placeholder="Ej. Mesa 1, Mesa 2, Línea A, Deshuese 1" maxlength="50" required>
                        <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 0.35rem;">
                            Identificador que aparecerá en los botones de selección rápida en la sala.
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label" for="mesa_descripcion">Descripción o Ubicación (Opcional)</label>
                        <input type="text" name="descripcion" id="mesa_descripcion" class="form-input" placeholder="Ej. Área delantera, mesa de corte secundario" maxlength="150">
                    </div>

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label class="form-label" for="mesa_activo">Estado</label>
                        <select name="activo" id="mesa_activo" class="form-input">
                            <option value="1">Activa (Disponible para operar)</option>
                            <option value="0">Inactiva (Fuera de servicio)</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                        <button type="button" id="btnCancelarEdicionMesa" class="btn" style="display: none; background: var(--bg-sidebar); border: 1px solid var(--border-color); color: var(--text-secondary); width: auto;">
                            Cancelar Edición
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnGuardarMesa" style="width: auto; min-width: 150px;">
                            Guardar Mesa
                        </button>
                    </div>
                </form>
            </div>

            <!-- Listado de Mesas Configuradas -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
                    <h4 style="margin: 0; color: var(--text-primary); font-size: 1.1rem;">Mesas y Líneas Registradas</h4>
                    <span id="badgeTotalMesas" class="badge <?= !empty($mesas) ? 'badge-success' : 'badge-warning' ?>">
                        <?= count($mesas ?? []) ?> <?= count($mesas ?? []) === 1 ? 'mesa' : 'mesas' ?>
                    </span>
                </div>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.25rem;">
                    Mesas actualmente registradas para la operación en sala.
                </p>

                <div id="contenedorListaMesas">
                    <?php if (empty($mesas)): ?>
                        <div class="empty-state-mesas" style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted); background: var(--bg-main); border: 1px dashed var(--border-color); border-radius: 8px;">
                            <svg class="icon" viewBox="0 0 24 24" style="width: 36px; height: 36px; margin-bottom: 0.5rem; opacity: 0.5;"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                            <p style="margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--text-secondary);">No hay mesas o líneas configuradas</p>
                            <p style="margin: 0.25rem 0 0; font-size: 0.82rem;">El sistema abrirá órdenes de desposte sin requerir asignación de mesa.</p>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach ($mesas as $m): ?>
                                <div class="item-mesa-card" style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.85rem 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                                    <div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <strong style="font-size: 1rem; color: var(--text-primary);"><?= Security::escape($m['Nombre']) ?></strong>
                                            <span class="badge <?= (int)$m['Activo'] === 1 ? 'badge-success' : 'badge-warning' ?>" style="font-size: 0.7rem;">
                                                <?= (int)$m['Activo'] === 1 ? 'ACTIVA' : 'INACTIVA' ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($m['Descripcion'])): ?>
                                            <span style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-top: 0.2rem;">
                                                <?= Security::escape($m['Descripcion']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <button type="button" class="btn btn-outline btn-sm btn-editar-mesa" 
                                                data-id="<?= (int)$m['ID'] ?>" 
                                                data-nombre="<?= Security::escape($m['Nombre']) ?>" 
                                                data-descripcion="<?= Security::escape($m['Descripcion'] ?? '') ?>" 
                                                data-activo="<?= (int)$m['Activo'] ?>"
                                                style="padding: 0.35rem 0.65rem; font-size: 0.8rem; width: auto;">
                                            Editar
                                        </button>
                                        <button type="button" class="btn btn-sm btn-toggle-mesa" 
                                                data-id="<?= (int)$m['ID'] ?>" 
                                                data-activo="<?= (int)$m['Activo'] === 1 ? 0 : 1 ?>"
                                                style="background: var(--bg-sidebar); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 0.35rem 0.65rem; font-size: 0.8rem; width: auto;">
                                            <?= (int)$m['Activo'] === 1 ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                        <button type="button" class="btn btn-outline btn-sm btn-eliminar-mesa" 
                                                data-id="<?= (int)$m['ID'] ?>" 
                                                data-nombre="<?= Security::escape($m['Nombre']) ?>" 
                                                style="border-color: rgba(220, 53, 69, 0.4); color: var(--danger); padding: 0.35rem 0.65rem; font-size: 0.8rem; width: auto;">
                                            Eliminar
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal para Asociar Corte a Especie -->
<div id="modalCorte" class="sidebar-overlay" style="display: none; align-items: center; justify-content: center; z-index: 100;">
    <div class="card" style="max-width: 520px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h4 style="margin: 0; font-size: 1.15rem; color: var(--text-primary);">Asociar Corte a Especie</h4>
            <button type="button" data-action="cerrar-modal-corte" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.4rem;">&times;</button>
        </div>
        
        <form id="formAgregarCorte">
            <input type="hidden" name="csrf_token" value="<?= Security::escape($csrf_token) ?>">
            <input type="hidden" name="canal" id="modal_canal_cod">

            <div class="form-group">
                <label class="form-label">Especie Canal</label>
                <input type="text" id="modal_canal_nombre" class="form-input" readonly style="opacity: 0.8;">
            </div>

            <div class="form-group">
                <label class="form-label">Seleccionar Corte (SAPROD)</label>
                <select name="corte" id="modal_sel_corte" class="form-input" required>
                    <option value="">-- Cargando cortes disponibles --</option>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div class="form-group">
                    <label class="form-label">% Rendimiento Esperado</label>
                    <input type="number" step="0.0001" min="0" max="100" name="rendimiento" class="form-input" value="0.0000" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Margen Utilidad (%)</label>
                    <input type="number" step="0.0001" min="0" max="100" name="margen" class="form-input" value="30.0000" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Días de Vida Útil / Vencimiento</label>
                <input type="number" name="dias_vencimiento" class="form-input" value="15" min="1" required>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="button" class="btn" data-action="cerrar-modal-corte" style="background: var(--bg-sidebar); border: 1px solid var(--border-color); color: var(--text-secondary); width: auto;">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary" style="width: auto;">
                    Vincular Corte
                </button>
            </div>
        </form>
    </div>
</div>

<script src="/js/configuracion.js"></script>

<style>
.tab-btn {
    background: transparent;
    border: none;
    color: var(--text-secondary);
    padding: 0.65rem 1.15rem;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
}
.tab-btn:hover {
    color: var(--text-primary);
    background: var(--bg-hover, rgba(255,255,255,0.05));
}
.tab-btn.active {
    color: var(--primary);
    background: rgba(244, 107, 58, 0.15);
    border: 1px solid rgba(244, 107, 58, 0.3);
}
.badge {
    padding: 0.25rem 0.55rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.04em;
}
.badge-success {
    background: rgba(193, 218, 198, 0.15);
    color: var(--success);
    border: 1px solid rgba(193, 218, 198, 0.3);
}
.badge-warning {
    background: rgba(244, 107, 58, 0.15);
    color: var(--primary);
    border: 1px solid rgba(244, 107, 58, 0.3);
}
</style>

<?php
$content = ob_get_clean();
$title = 'Configuración - ST Despostadora';
$pageTitle = 'Configuración General';
$activeNav = 'configuracion';

require __DIR__ . '/layout.php';
