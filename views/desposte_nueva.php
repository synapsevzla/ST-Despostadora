<?php
declare(strict_types=1);

use Config\Security;

$hasMesas = !empty($mesas);

ob_start();
?>

<div class="content-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.25rem;">
            <a href="/deboning" class="btn btn-outline btn-sm" style="text-decoration:none;">
                <svg class="icon" viewBox="0 0 24 24" style="width:16px; height:16px;"><polyline points="15 18 9 12 15 6"></polyline></svg>
                Volver a la Sala de Desposte
            </a>
            <span class="badge badge-warning" style="font-family:monospace; font-size:0.85rem;">
                Siguiente: <?= Security::escape($nextCorrelativo) ?>
            </span>
        </div>
        <h1 class="page-title" style="font-size:1.6rem; font-weight:700; color:var(--text-primary); margin:0;">
            Abrir Nueva Orden de Desposte
        </h1>
        <p style="color:var(--text-secondary); margin-top:0.25rem; font-size:0.9rem;">
            <?= $hasMesas ? 'Asignación de mesa operativa, selección de canal de origen e inicio del pesaje en sala.' : 'Selección de canal de origen e inicio del pesaje en sala.' ?>
        </p>
    </div>
</div>

<!-- Alerta de Notificación y Alerta de Borrador -->
<div id="desposteDraftNotice" class="alert alert-info" style="display:none; margin-bottom:1.25rem; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.5rem;">
    <span><strong>Borrador en curso recuperado:</strong> Se restauraron los datos seleccionados previamente.</span>
    <button type="button" class="btn btn-outline btn-sm" id="btnDescartarBorrador" style="padding:0.25rem 0.6rem; font-size:0.8rem; background:rgba(0,0,0,0.15);">Descartar Borrador</button>
</div>
<div id="desposteAlert" class="alert" style="display:none; margin-bottom:1.25rem;"></div>

<form id="formNuevaOrdenPagina">
    <input type="hidden" name="csrf_token" id="csrfToken" value="<?= Security::escape($csrf_token) ?>">

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:1.5rem; margin-bottom:1.5rem;">
        
        <!-- COLUMNA IZQUIERDA: MESA Y CANAL -->
        <div style="display:flex; flex-direction:column; gap:1.5rem;">
            
            <?php if ($hasMesas): ?>
            <!-- TARJETA 1: LÍNEA / MESA DE TRABAJO (Solo si hay mesas configuradas) -->
            <div class="card">
                <h3 class="card-title" style="font-size:1.15rem; margin-bottom:1.25rem; display:flex; align-items:center; gap:0.5rem; color:var(--text-primary);">
                    <svg class="icon" viewBox="0 0 24 24" style="color:var(--primary); width:20px; height:20px;"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    1. Línea / Mesa Operativa
                </h3>

                <div class="form-group" style="margin-bottom:1.25rem;">
                    <label class="form-label" style="margin-bottom:0.5rem;">Selección Rápida de Mesa:</label>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(100px, 1fr)); gap:0.5rem; margin-bottom:0.75rem;">
                        <?php foreach ($mesas as $idx => $m): ?>
                            <button type="button" class="btn btn-secondary mesa-select-btn <?= $idx === 0 ? 'active' : '' ?>" data-mesa="<?= Security::escape($m['Nombre']) ?>">
                                <?= Security::escape($m['Nombre']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <label class="form-label" for="inputLineaMesa" style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.35rem;">Identificador o Nombre de la Mesa:</label>
                    <input type="text" name="linea_mesa" id="inputLineaMesa" class="form-input" value="<?= Security::escape($mesas[0]['Nombre']) ?>" required style="font-weight:700;">
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" for="inputObservaciones">Observaciones de la Corrida (Opcional):</label>
                    <input type="text" name="observaciones" id="inputObservaciones" class="form-input" placeholder="Ej. Lote de reses de engorde #42, turno matutino">
                </div>
            </div>
            <?php else: ?>
                <!-- No hay mesas configuradas: el uso de mesa no es necesario -->
                <input type="hidden" name="linea_mesa" id="inputLineaMesa" value="">
            <?php endif; ?>

            <!-- TARJETA: ESPECIE Y CANAL DE RECEPCIÓN -->
            <div class="card">
                <h3 class="card-title" style="font-size:1.15rem; margin-bottom:1.25rem; display:flex; align-items:center; gap:0.5rem; color:var(--text-primary);">
                    <svg class="icon" viewBox="0 0 24 24" style="color:var(--primary); width:20px; height:20px;"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                    <?= $hasMesas ? '2.' : '1.' ?> Especie y Canal de Origen
                </h3>

                <div class="form-group" style="margin-bottom:1.25rem;">
                    <label class="form-label" for="selectCanalEspecie">Especie / Tipo de Canal *</label>
                    <select name="codprod_canal" id="selectCanalEspecie" class="form-input" required>
                        <option value="">-- Seleccione una especie configurada --</option>
                        <?php 
                        $selectedEspecie = $selectedEspecie ?? '';
                        foreach ($species as $sp): 
                            $cCanal = (string)($sp['CodProdCanal'] ?? $sp['CodProd'] ?? '');
                            $nCanal = (string)($sp['NombreCanal'] ?? $sp['NombreEspecie'] ?? $cCanal);
                            $isSelected = ($selectedEspecie !== '' && ($selectedEspecie === $cCanal || $selectedEspecie === ($sp['CodProd'] ?? '')));
                        ?>
                            <option value="<?= Security::escape($cCanal) ?>" <?= $isSelected ? 'selected' : '' ?>>
                                <?= Security::escape($nCanal) ?> (<?= Security::escape($cCanal) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:0.5rem;">
                    <label class="form-label" for="selectRecepcion">Recepción de Canal con Peso Disponible *</label>
                    <select name="nro_recepcion" id="selectRecepcion" class="form-input" required>
                        <option value="">-- Seleccione primero una especie --</option>
                    </select>
                    <small id="recAvailableHelp" style="color:var(--text-muted); font-size:0.8rem; margin-top:4px; display:block;"></small>
                </div>

                <!-- Selección de Pieza Individual (si aplica Método 3) -->
                <div class="form-group" id="grpPiezaIndividual" style="display:none; margin-top:1.25rem; background:rgba(0,0,0,0.2); padding:1rem; border-radius:8px; border-left:3px solid var(--primary);">
                    <label class="form-label" for="selectPiezaIndividual" style="color:var(--primary); font-weight:700; margin-bottom:0.35rem;">
                        Res / Pieza Individual a Despostar *:
                    </label>
                    <select name="nro_especie" id="selectPiezaIndividual" class="form-input">
                        <option value="">-- Seleccione la res individual --</option>
                    </select>
                    <small style="color:var(--text-muted); font-size:0.8rem; display:block; margin-top:4px;">
                        Esta recepción requiere seleccionar la pieza exacta registrada individualmente.
                    </small>
                </div>
                <?php if (!$hasMesas): ?>
                <div class="form-group" style="margin-top:1.25rem; margin-bottom:0;">
                    <label class="form-label" for="inputObservaciones">Observaciones de la Corrida (Opcional):</label>
                    <input type="text" name="observaciones" id="inputObservaciones" class="form-input" placeholder="Ej. Lote de reses de engorde #42, turno matutino">
                </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- COLUMNA DERECHA: PESO DE ENTRADA Y TECLADO TÁCTIL -->
        <div style="display:flex; flex-direction:column; gap:1.5rem;">
            
            <div class="card">
                <h3 class="card-title" style="font-size:1.15rem; margin-bottom:1.25rem; display:flex; align-items:center; gap:0.5rem; color:var(--text-primary);">
                    <svg class="icon" viewBox="0 0 24 24" style="color:var(--primary); width:20px; height:20px;"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg>
                    <?= $hasMesas ? '3.' : '2.' ?> Peso de Canal para la Orden
                </h3>

                <div class="form-group" style="margin-bottom:1.25rem;">
                    <label class="form-label" for="inputPesoEntrada" style="display:flex; justify-content:space-between;">
                        <span>Peso a Procesar (kg) *</span>
                        <span id="badgePesoDisp" style="color:var(--primary); font-weight:600; text-transform:none;"></span>
                    </label>
                    <input type="text" inputmode="decimal" name="peso_entrada" id="inputPesoEntrada" class="form-input" 
                           style="font-size:1.8rem; font-weight:800; text-align:center; padding:0.75rem; color:var(--primary); letter-spacing:0.05em; width:100%; box-sizing:border-box;" 
                           placeholder="0.0000" required>
                </div>

                <!-- Teclado Numérico Táctil -->
                <div style="background:rgba(0,0,0,0.15); padding:1rem; border-radius:10px; margin-bottom:1.25rem;">
                    <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.5rem; text-transform:uppercase; letter-spacing:0.05em; text-align:center;">
                        Teclado Táctil de Pesaje
                    </div>
                    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:0.5rem;">
                        <button type="button" class="keypad-btn" data-key="7">7</button>
                        <button type="button" class="keypad-btn" data-key="8">8</button>
                        <button type="button" class="keypad-btn" data-key="9">9</button>
                        <button type="button" class="keypad-btn" data-key="4">4</button>
                        <button type="button" class="keypad-btn" data-key="5">5</button>
                        <button type="button" class="keypad-btn" data-key="6">6</button>
                        <button type="button" class="keypad-btn" data-key="1">1</button>
                        <button type="button" class="keypad-btn" data-key="2">2</button>
                        <button type="button" class="keypad-btn" data-key="3">3</button>
                        <button type="button" class="keypad-btn" data-key="C" style="color:var(--danger); font-size:1.1rem;">BORRAR</button>
                        <button type="button" class="keypad-btn" data-key="0">0</button>
                        <button type="button" class="keypad-btn" data-key=".">.</button>
                    </div>
                </div>

                <!-- Resumen previo -->
                <div style="background:rgba(0,0,0,0.15); padding:0.85rem 1rem; border-radius:8px; font-size:0.85rem; line-height:1.6; color:var(--text-secondary); margin-bottom:1.25rem;">
                    <?php if ($hasMesas): ?>
                    <div style="display:flex; justify-content:space-between;" id="rowResumenMesa">
                        <span>Mesa Asignada:</span>
                        <strong id="resumenMesa" style="color:var(--text-primary);"><?= Security::escape($mesas[0]['Nombre'] ?? '') ?></strong>
                    </div>
                    <?php endif; ?>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Especie / Canal:</span>
                        <strong id="resumenEspecie" style="color:var(--text-primary);">-</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Recepción Origen:</span>
                        <strong id="resumenRecepcion" style="color:var(--text-primary);">-</strong>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div style="display:flex; justify-content:flex-end; gap:0.75rem; align-items:center;">
                    <a href="/deboning" class="btn btn-outline" style="min-width:120px;">
                        Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary" id="btnSubmitNuevaOrden" style="flex:1; font-size:1rem; padding:0.85rem 1.25rem;">
                        <svg class="icon" viewBox="0 0 24 24" style="width:18px; height:18px; margin-right:4px;"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                        Abrir Orden e Iniciar Pesaje
                    </button>
                </div>
            </div>

        </div>

    </div>
</form>

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
    user-select: none;
}
.keypad-btn:active {
    background-color: var(--primary);
    color: #fff;
    transform: scale(0.96);
}
.mesa-select-btn {
    padding: 0.6rem 0.5rem;
    font-size: 0.85rem;
    font-weight: 600;
    border-radius: 6px;
    cursor: pointer;
}
.mesa-select-btn.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary-hover);
    box-shadow: 0 0 10px rgba(244, 107, 58, 0.35);
}
</style>

<script src="/js/desposte_nueva.js"></script>

<?php
$content = ob_get_clean();
$title = 'Nueva Orden de Desposte - ST Despostadora';
$pageTitle = 'Abrir Nueva Orden de Desposte';
$activeNav = 'deboning';

require __DIR__ . '/layout.php';
