<?php
declare(strict_types=1);

use Config\Security;

ob_start();
?>

<div class="content-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h1 class="page-title" style="font-size:1.6rem; font-weight:700; color:var(--text-primary); margin:0;">
            Sala de Desposte Cárnico
        </h1>
        <p style="color:var(--text-secondary); margin-top:0.25rem; font-size:0.9rem;">
            Control multi-línea, pesaje táctil de cortes, generación automática de lotes y costeo con absorción de merma.
        </p>
    </div>
    <div style="display:flex; gap:0.75rem;">
        <button type="button" class="btn btn-outline" id="btnToggleBoard" data-action="view-board">
            <svg class="icon" viewBox="0 0 24 24" style="width:16px; height:16px; margin-right:6px;"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            Tablero de Mesas (<span id="countActiveOrders"><?= count($activeOrders) ?></span>)
        </button>
        <button type="button" class="btn btn-primary" id="btnOpenNewOrderTop" data-action="open-new-order">
            <svg class="icon" viewBox="0 0 24 24" style="width:18px; height:18px; margin-right:6px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Nueva Orden de Desposte
        </button>
    </div>
</div>

<!-- Alerta de Notificación Rápida -->
<div id="deboningAlert" class="alert" style="display:none; margin-bottom:1.25rem;"></div>

<!-- VISTA 1: TABLERO MULTI-LÍNEA / MULTI-MESA -->
<div id="viewBoard" style="display:block;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h2 style="font-size:1.15rem; font-weight:600; color:var(--text-primary); margin:0;">
            Mesas y Líneas Activas en Producción
        </h2>
        <span style="font-size:0.85rem; color:var(--text-secondary);">
            Haga clic en "Operar Mesa" para entrar al pesaje táctil en vivo.
        </span>
    </div>

    <div id="activeOrdersGrid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:1.25rem;">
        <?php if (empty($activeOrders)): ?>
            <div class="card" style="grid-column: 1 / -1; text-align:center; padding:3rem 1.5rem;">
                <svg class="icon" viewBox="0 0 24 24" style="width:48px; height:48px; stroke:var(--text-muted); margin-bottom:1rem;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                <h3 style="font-size:1.1rem; color:var(--text-primary); margin-bottom:0.5rem;">No hay órdenes de desposte activas</h3>
                <p style="color:var(--text-secondary); max-width:450px; margin:0 auto 1.5rem auto; font-size:0.9rem;">
                    Inicie una nueva orden asignando una mesa y una canal de recepción para comenzar el pesaje y desglose de cortes.
                </p>
                <button type="button" class="btn btn-primary" data-action="open-new-order">
                    Iniciar Primera Orden
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($activeOrders as $ord): ?>
                <div class="card" style="border-left: 4px solid var(--primary); display:flex; flex-direction:column; justify-content:space-between;">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem;">
                            <div>
                                <span class="badge" style="background:#55433a; color:var(--primary); font-weight:700; font-size:0.8rem;">
                                    <?= Security::escape($ord['LineaMesa']) ?>
                                </span>
                                <h3 style="font-size:1.05rem; font-weight:700; margin-top:0.35rem; color:var(--text-primary);">
                                    <?= Security::escape($ord['NroOrdenDesposte']) ?>
                                </h3>
                            </div>
                            <span class="badge badge-success" style="font-size:0.75rem;">EN PROCESO</span>
                        </div>

                        <div style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:1rem; line-height:1.5;">
                            <div><strong>Especie:</strong> <?= Security::escape($ord['NombreCanal']) ?> (<?= Security::escape($ord['CodProdCanal']) ?>)</div>
                            <div><strong>Recepción:</strong> <?= Security::escape($ord['NroRecepcion']) ?></div>
                            <div><strong>Inicio:</strong> <?= substr(Security::escape($ord['FechaHoraInicio']), 0, 16) ?></div>
                        </div>

                        <!-- Métricas de la Mesa -->
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; background:rgba(0,0,0,0.15); padding:0.75rem; border-radius:8px; margin-bottom:1rem;">
                            <div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">Canal Entrada</div>
                                <div style="font-size:1.1rem; font-weight:700; color:var(--text-primary);"><?= number_format((float)$ord['PesoCanalEntrada'], 2) ?> kg</div>
                            </div>
                            <div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">Cortes Pesados</div>
                                <div style="font-size:1.1rem; font-weight:700; color:var(--primary);"><?= number_format((float)$ord['PesoTotalCortes'], 2) ?> kg</div>
                            </div>
                            <div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">Rendimiento</div>
                                <div style="font-size:1rem; font-weight:600; color:var(--success);"><?= number_format((float)$ord['RendimientoPct'], 2) ?>%</div>
                            </div>
                            <div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">Merma Actual</div>
                                <div style="font-size:1rem; font-weight:600; color:var(--danger);"><?= number_format((float)$ord['MermaPct'], 2) ?>%</div>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary btn-operate-order" style="width:100%; justify-content:center;" data-order="<?= Security::escape($ord['NroOrdenDesposte']) ?>">
                        <svg class="icon" viewBox="0 0 24 24" style="width:16px; height:16px; margin-right:6px;"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                        Operar Mesa / Pesar
                    </button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- VISTA 2: ESTACIÓN TÁCTIL DE PESAJE Y COSTEO -->
<div id="viewStation" style="display:none;">
    <!-- Barra superior de la estación -->
    <div class="card" style="margin-bottom:1.25rem; padding:1rem 1.25rem; background:linear-gradient(135deg, #43342d 0%, #4e3d36 100%);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <div>
                <span class="badge" id="stationMesaBadge" style="background:var(--primary); color:#fff; font-weight:700; font-size:0.85rem;">Mesa 1</span>
                <span style="font-size:1.15rem; font-weight:700; margin-left:0.5rem; color:var(--text-primary);" id="stationOrderTitle">DESP-0000000000000001</span>
                <span style="font-size:0.9rem; color:var(--text-secondary); margin-left:0.75rem;" id="stationCanalSubtitle">Novillo Especial (CARNEC)</span>
            </div>
            <div style="display:flex; gap:0.5rem;">
                <button type="button" class="btn btn-outline" data-action="view-board">
                    ← Volver al Tablero
                </button>
                <button type="button" class="btn btn-danger" id="btnPromptCloseStation" data-action="prompt-close-order">
                    <svg class="icon" viewBox="0 0 24 24" style="width:16px; height:16px; margin-right:6px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    Finalizar y Cerrar Desposte
                </button>
            </div>
        </div>
    </div>

    <!-- Indicador de Descuadre Físico (oculto por defecto) -->
    <div id="descuadreAlert" class="alert alert-danger" style="display:none; margin-bottom:1.25rem; font-weight:700; font-size:1rem; border:2px solid #ff3b30;">
        ⚠️ ALERTA DE DESCUADRE FÍSICO: El peso total de los cortes pesados supera el peso de entrada de la canal. Verifique o anule pesajes erróneos antes de continuar.
    </div>

    <!-- Panel de Métricas en Tiempo Real -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:1rem; margin-bottom:1.25rem;">
        <div class="card" style="padding:1rem; text-align:center;">
            <div style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Peso Canal Entrada</div>
            <div style="font-size:1.6rem; font-weight:800; color:var(--text-primary); margin-top:0.25rem;" id="statPesoCanal">0.00 kg</div>
            <div style="font-size:0.75rem; color:var(--text-secondary);" id="statCostoCanal">$0.00 /kg</div>
        </div>
        <div class="card" style="padding:1rem; text-align:center;">
            <div style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Cortes Obtenidos</div>
            <div style="font-size:1.6rem; font-weight:800; color:var(--primary); margin-top:0.25rem;" id="statPesoCortes">0.00 kg</div>
            <div style="font-size:0.75rem; color:var(--text-secondary);"><span id="statCantCortes">0</span> piezas pesadas</div>
        </div>
        <div class="card" style="padding:1rem; text-align:center;">
            <div style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Merma Física</div>
            <div style="font-size:1.6rem; font-weight:800; color:var(--danger); margin-top:0.25rem;" id="statPesoMerma">0.00 kg</div>
            <div style="font-size:0.75rem; color:var(--danger);" id="statPerdidaMerma">Pérdida: $0.00</div>
        </div>
        <div class="card" style="padding:1rem; text-align:center;">
            <div style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Rendimiento %</div>
            <div style="font-size:1.6rem; font-weight:800; color:var(--success); margin-top:0.25rem;" id="statRendimiento">0.00%</div>
            <div style="font-size:0.75rem; color:var(--text-secondary);">de aprovechamiento</div>
        </div>
        <div class="card" style="padding:1rem; text-align:center;">
            <div style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Merma %</div>
            <div style="font-size:1.6rem; font-weight:800; color:#d8865e; margin-top:0.25rem;" id="statMermaPct">0.00%</div>
            <div style="font-size:0.75rem; color:var(--text-secondary);">absorbida en cortes</div>
        </div>
    </div>

    <!-- Área de Trabajo: Captura Táctil de Pesaje y Grilla de Lotes -->
    <div style="display:grid; grid-template-columns: 1fr 1.3fr; gap:1.25rem; margin-bottom:1.5rem;">
        <!-- Columna Izquierda: Teclado y Selección Táctil de Cortes -->
        <div class="card" style="padding:1.25rem;">
            <h3 style="font-size:1.05rem; font-weight:700; margin-bottom:1rem; color:var(--text-primary); display:flex; align-items:center; gap:0.5rem;">
                <svg class="icon" viewBox="0 0 24 24" style="width:18px; height:18px; stroke:var(--primary);"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                1. Seleccione el Corte a Pesar
            </h3>

            <!-- Botonera Táctil de Cortes Asignados -->
            <div id="cutsButtonGrid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap:0.6rem; max-height:220px; overflow-y:auto; margin-bottom:1.25rem; padding-right:4px;">
                <div style="color:var(--text-muted); font-size:0.85rem; grid-column:1/-1;">Cargando cortes asociados...</div>
            </div>

            <div style="background:rgba(0,0,0,0.25); border:1px solid var(--border-color); border-radius:8px; padding:0.75rem 1rem; margin-bottom:1rem;">
                <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase;">Corte Seleccionado:</div>
                <div id="selectedCutDisplay" style="font-size:1.15rem; font-weight:700; color:var(--primary); margin-top:2px;">
                    Ninguno seleccionado
                </div>
            </div>

            <!-- Entrada de Peso y Keypad Táctil -->
            <h3 style="font-size:1.05rem; font-weight:700; margin-bottom:0.75rem; color:var(--text-primary); display:flex; align-items:center; gap:0.5rem;">
                <svg class="icon" viewBox="0 0 24 24" style="width:18px; height:18px; stroke:var(--primary);"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                2. Peso Neto (Balanza / Táctil)
            </h3>

            <div style="position:relative; margin-bottom:0.75rem;">
                <input type="number" step="0.001" id="weighInput" class="form-control" style="font-size:2rem; font-weight:800; text-align:right; padding:0.75rem 3.5rem 0.75rem 1rem; height:auto; color:var(--text-primary); background:#271e1a;" placeholder="0.000">
                <span style="position:absolute; right:1.25rem; top:50%; transform:translateY(-50%); font-size:1.2rem; font-weight:700; color:var(--text-secondary);">kg</span>
            </div>

            <!-- Keypad Numérico Táctil con delegación por data-key -->
            <div id="keypadContainer" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:0.5rem; margin-bottom:1rem;">
                <button type="button" class="btn btn-secondary keypad-btn" data-key="7">7</button>
                <button type="button" class="btn btn-secondary keypad-btn" data-key="8">8</button>
                <button type="button" class="btn btn-secondary keypad-btn" data-key="9">9</button>
                <button type="button" class="btn btn-secondary keypad-btn" data-key="4">4</button>
                <button type="button" class="btn btn-secondary keypad-btn" data-key="5">5</button>
                <button type="button" class="btn btn-secondary keypad-btn" data-key="6">6</button>
                <button type="button" class="btn btn-secondary keypad-btn" data-key="1">1</button>
                <button type="button" class="btn btn-secondary keypad-btn" data-key="2">2</button>
                <button type="button" class="btn btn-secondary keypad-btn" data-key="3">3</button>
                <button type="button" class="btn btn-secondary keypad-btn" data-key="C" style="background:#5c352a; color:#ff9d80;">C</button>
                <button type="button" class="btn btn-secondary keypad-btn" data-key="0">0</button>
                <button type="button" class="btn btn-secondary keypad-btn" data-key=".">.</button>
            </div>

            <button type="button" class="btn btn-primary" id="btnRegisterCut" style="width:100%; height:52px; font-size:1.15rem; font-weight:700; justify-content:center;">
                <svg class="icon" viewBox="0 0 24 24" style="width:20px; height:20px; margin-right:8px;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                REGISTRAR CORTE
            </button>
        </div>

        <!-- Columna Derecha: Tabla Dinámica de Cortes Pesados con Lotes y Costeo Real -->
        <div class="card" style="padding:1.25rem; display:flex; flex-direction:column;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <h3 style="font-size:1.05rem; font-weight:700; color:var(--text-primary); margin:0;">
                    Cortes Registrados en la Orden
                </h3>
                <span class="badge" style="background:#55433a; color:var(--text-secondary); font-size:0.75rem;">
                    Absorción de Merma Ponderada
                </span>
            </div>

            <div style="flex:1; overflow-x:auto; max-height:480px;">
                <table class="table" style="width:100%; font-size:0.85rem; border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:2px solid var(--border-color); text-align:left; color:var(--text-muted); font-size:0.75rem; text-transform:uppercase;">
                            <th style="padding:0.6rem 0.5rem;">#</th>
                            <th style="padding:0.6rem 0.5rem;">Lote Generado</th>
                            <th style="padding:0.6rem 0.5rem;">Corte</th>
                            <th style="padding:0.6rem 0.5rem; text-align:right;">Peso Neto</th>
                            <th style="padding:0.6rem 0.5rem; text-align:right;">Rend %</th>
                            <th style="padding:0.6rem 0.5rem; text-align:right;">Costo Base</th>
                            <th style="padding:0.6rem 0.5rem; text-align:right;">+ Merma/Kg</th>
                            <th style="padding:0.6rem 0.5rem; text-align:right;">Costo Real</th>
                            <th style="padding:0.6rem 0.5rem; text-align:right;">P. Sugerido</th>
                            <th style="padding:0.6rem 0.5rem; text-align:center;">Venc.</th>
                            <th style="padding:0.6rem 0.5rem; text-align:center;">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="cutsTableBody">
                        <tr>
                            <td colspan="11" style="text-align:center; padding:2rem; color:var(--text-muted);">
                                No se han registrado pesajes de cortes para esta orden todavía.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: APERTURA DE NUEVA ORDEN DE DESPOSTE -->
<div id="modalNewOrder" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(20,15,12,0.8); z-index:100; align-items:center; justify-content:center; padding:1.5rem; backdrop-filter:blur(3px);">
    <div class="card" style="width:100%; max-width:580px; max-height:90vh; overflow-y:auto; position:relative; box-shadow:0 12px 32px rgba(0,0,0,0.6);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
            <h2 style="font-size:1.25rem; font-weight:700; color:var(--text-primary); margin:0;">
                Abrir Nueva Orden de Desposte
            </h2>
            <button type="button" class="btn-close" data-action="close-new-order" style="background:transparent; border:none; color:var(--text-secondary); cursor:pointer; font-size:1.4rem;">&times;</button>
        </div>

        <form id="formNewOrder">
            <input type="hidden" name="csrf_token" value="<?= Security::escape($csrf_token) ?>">

            <!-- Línea / Mesa de Trabajo -->
            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label" style="display:block; font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.35rem;">Línea / Mesa Operativa:</label>
                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:0.5rem; margin-bottom:0.5rem;">
                    <button type="button" class="btn btn-secondary mesa-select-btn active" data-mesa="Mesa 1">Mesa 1</button>
                    <button type="button" class="btn btn-secondary mesa-select-btn" data-mesa="Mesa 2">Mesa 2</button>
                    <button type="button" class="btn btn-secondary mesa-select-btn" data-mesa="Mesa 3">Mesa 3</button>
                    <button type="button" class="btn btn-secondary mesa-select-btn" data-mesa="Línea A">Línea A</button>
                </div>
                <input type="text" name="linea_mesa" id="inputLineaMesa" class="form-control" value="Mesa 1" required style="font-weight:600;">
            </div>

            <!-- Especie / Canal -->
            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label" style="display:block; font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.35rem;">Especie / Tipo de Canal:</label>
                <select name="codprod_canal" id="selectCanalEspecie" class="form-control" required>
                    <option value="">-- Seleccione una especie configurada --</option>
                    <?php foreach ($species as $sp): 
                        $cCanal = (string)($sp['CodProdCanal'] ?? $sp['CodProd'] ?? '');
                        $nCanal = (string)($sp['NombreCanal'] ?? $sp['NombreEspecie'] ?? $cCanal);
                    ?>
                        <option value="<?= Security::escape($cCanal) ?>">
                            <?= Security::escape($nCanal) ?> (<?= Security::escape($cCanal) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Recepción de Origen -->
            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label" style="display:block; font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.35rem;">Recepción de Canal con Peso Disponible:</label>
                <select name="nro_recepcion" id="selectRecepcion" class="form-control" required>
                    <option value="">-- Seleccione primero una especie --</option>
                </select>
                <small id="recAvailableHelp" style="color:var(--text-muted); font-size:0.8rem; margin-top:4px; display:block;"></small>
            </div>

            <!-- Selección de Pieza Individual (si aplica Método 3) -->
            <div class="form-group" id="grpPiezaIndividual" style="display:none; margin-bottom:1rem; background:rgba(0,0,0,0.15); padding:0.75rem; border-radius:8px;">
                <label class="form-label" style="display:block; font-size:0.85rem; color:var(--primary); font-weight:600; margin-bottom:0.35rem;">
                    Res / Pieza Individual a Despostar:
                </label>
                <select name="nro_especie" id="selectPiezaIndividual" class="form-control">
                    <option value="">-- Seleccione la res individual --</option>
                </select>
            </div>

            <!-- Peso a Procesar -->
            <div class="form-group" style="margin-bottom:1.25rem;">
                <label class="form-label" style="display:block; font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.35rem;">Peso de Canal para la Orden (kg):</label>
                <input type="number" step="0.001" name="peso_entrada" id="inputPesoEntrada" class="form-control" style="font-size:1.2rem; font-weight:700;" placeholder="0.000" required>
            </div>

            <div class="form-group" style="margin-bottom:1.5rem;">
                <label class="form-label" style="display:block; font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.35rem;">Observaciones (Opcional):</label>
                <input type="text" name="observaciones" class="form-control" placeholder="Ej. Lote de reses de engorde lote #42">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
                <button type="button" class="btn btn-outline" data-action="close-new-order">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitNewOrder">
                    Abrir Orden e Iniciar Pesaje
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: CONFIRMACIÓN DE CIERRE DEFINITIVO DE DESPOSTE -->
<div id="modalCloseOrder" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(20,15,12,0.8); z-index:100; align-items:center; justify-content:center; padding:1.5rem; backdrop-filter:blur(3px);">
    <div class="card" style="width:100%; max-width:540px; box-shadow:0 12px 32px rgba(0,0,0,0.6);">
        <h2 style="font-size:1.25rem; font-weight:700; color:var(--text-primary); margin-bottom:0.75rem;">
            Confirmar Cierre de Orden de Desposte
        </h2>
        <p style="color:var(--text-secondary); font-size:0.9rem; line-height:1.5; margin-bottom:1.25rem;">
            Al confirmar, se ejecutarán de forma atómica los siguientes movimientos internos:
        </p>
        <ul style="color:var(--text-secondary); font-size:0.85rem; margin-bottom:1.25rem; padding-left:1.25rem; line-height:1.6;">
            <li>Fijación definitiva del costo real unitario con absorción de merma para cada lote de corte.</li>
            <li>Carga del stock de los cortes pesados en <code>ST_Despostadora_Existencias</code> con sus lotes únicos y vencimientos.</li>
            <li>Carga del stock de merma en <code>ST_Despostadora_Existencias</code> si aplica.</li>
            <li>Descarga definitiva de la carne en canal de <code>ST_Despostadora_Existencias</code>.</li>
            <li><strong>Cero impacto a tablas ERP directas</strong> (SAPROD.Existen permanece 100% intacto).</li>
        </ul>

        <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
            <button type="button" class="btn btn-outline" data-action="close-modal-close-order">Cancelar</button>
            <button type="button" class="btn btn-danger" id="btnConfirmCloseOrder">
                Confirmar y Cerrar Desposte
            </button>
        </div>
    </div>
</div>

<style>
.keypad-btn {
    height: 48px;
    font-size: 1.25rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: #372a25;
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    cursor: pointer;
    transition: background-color 0.15s, transform 0.05s;
}
.keypad-btn:active {
    background-color: var(--primary);
    color: #fff;
    transform: scale(0.96);
}
.cut-btn {
    padding: 0.6rem 0.5rem;
    font-size: 0.82rem;
    font-weight: 600;
    text-align: center;
    border-radius: 8px;
    background: #372a25;
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.15s ease;
    word-break: break-word;
}
.cut-btn:hover, .cut-btn.selected {
    background: #4e3d36;
    border-color: var(--primary);
    color: var(--primary);
}
.cut-btn.selected {
    border-width: 2px;
    box-shadow: 0 0 10px rgba(244, 107, 58, 0.35);
}
.mesa-select-btn.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary-hover);
}
</style>

<script src="/js/deboning.js"></script>

<?php
$content = ob_get_clean();
$title = 'Sala de Desposte - ST Despostadora';
$pageTitle = 'Sala de Desposte Cárnico';
$activeNav = 'deboning';

require __DIR__ . '/layout.php';
