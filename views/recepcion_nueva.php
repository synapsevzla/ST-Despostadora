<?php
declare(strict_types=1);

use Config\Security;

ob_start();
?>

<div class="content-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.25rem;">
            <a href="/recepcion" class="btn btn-outline btn-sm" style="text-decoration:none;">
                <svg class="icon" viewBox="0 0 24 24" style="width:16px; height:16px;"><polyline points="15 18 9 12 15 6"></polyline></svg>
                Volver al Historial
            </a>
            <span class="badge badge-warning" style="font-family:monospace; font-size:0.85rem;">
                Siguiente: <?= Security::escape($nextCorrelativo) ?>
            </span>
        </div>
        <h1 class="page-title" style="font-size:1.6rem; font-weight:700; color:var(--text-primary); margin:0;">
            Nueva Recepción de Carne en Canal
        </h1>
        <p style="color:var(--text-secondary); margin-top:0.25rem; font-size:0.9rem;">
            Registro formal de ingreso de reses, bovinos, cerdos, aves o pescado al depósito operativo.
        </p>
    </div>
</div>

<!-- Alerta de Notificación -->
<div id="recepcionAlert" class="alert" style="display:none; margin-bottom:1.25rem;"></div>

<form id="formNuevaRecepcionPagina">
    <input type="hidden" name="csrf_token" id="csrfToken" value="<?= Security::escape($csrf_token) ?>">

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:1.5rem; margin-bottom:1.5rem;">
        
        <!-- TARJETA 1: IDENTIFICACIÓN Y DEPÓSITO -->
        <div class="card">
            <h3 class="card-title" style="font-size:1.15rem; margin-bottom:1.25rem; display:flex; align-items:center; gap:0.5rem;">
                <svg class="icon" viewBox="0 0 24 24" style="color:var(--primary); width:20px; height:20px;"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                1. Especie y Destino Operativo
            </h3>

            <div class="form-group">
                <label class="form-label" for="rec_codprod">Especie / Canal Habilitada *</label>
                <select name="codprod" id="rec_codprod" class="form-input" required>
                    <option value="">-- Seleccione Especie Cárnica --</option>
                    <?php foreach ($species as $sp): ?>
                        <option value="<?= Security::escape($sp['CodProd']) ?>" 
                                data-costo="<?= (float)$sp['CostActCanal'] ?>" 
                                data-unidad="<?= Security::escape($sp['Unidad']) ?>"
                                data-descrip="<?= Security::escape($sp['NombreEspecie']) ?>">
                            [<?= Security::escape($sp['CodProd']) ?>] <?= Security::escape($sp['NombreEspecie']) ?> - Costo ERP: $<?= number_format((float)$sp['CostActCanal'], 2) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color:var(--text-muted); font-size:0.8rem; display:block; margin-top:0.35rem;">
                    Configuradas previamente en la Matriz de Cortes de Desposte.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="rec_codubic">Depósito de Entrada *</label>
                <select name="codubic" id="rec_codubic" class="form-input" required>
                    <?php foreach ($deposits as $dep): ?>
                        <option value="<?= Security::escape($dep['CodUbic']) ?>" <?= $defaultCodUbic === $dep['CodUbic'] ? 'selected' : '' ?>>
                            [<?= Security::escape($dep['CodUbic']) ?>] <?= Security::escape($dep['Descrip']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" for="rec_observaciones">Guía de Matadero / Documento / Observaciones</label>
                <textarea name="observaciones" id="rec_observaciones" class="form-input" rows="3" placeholder="Ej. Guía Sanitaria INSAI #78491, Transporte refrigerado Placa A45BZ, Lote de proveedor Ganadería El Carmen"></textarea>
            </div>
        </div>

        <!-- TARJETA 2: MÉTODO DE RECEPCIÓN -->
        <div class="card">
            <h3 class="card-title" style="font-size:1.15rem; margin-bottom:1.25rem; display:flex; align-items:center; gap:0.5rem;">
                <svg class="icon" viewBox="0 0 24 24" style="color:var(--primary); width:20px; height:20px;"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg>
                2. Selección del Método de Pesaje
            </h3>

            <div style="display:grid; grid-template-columns:1fr; gap:0.75rem; margin-bottom:1.25rem;">
                <label class="metodo-card-btn active" id="card_metodo_1" data-method="SOLO_PESO">
                    <input type="radio" name="metodo_entrada" value="SOLO_PESO" checked style="display:none;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-weight:700; font-size:1rem; color:var(--text-primary);">Método 1: Solo Peso</div>
                            <div style="font-size:0.8rem; color:var(--text-secondary);">Registro del peso global bruto, tara y cálculo de peso neto.</div>
                        </div>
                        <span class="badge badge-success">Global</span>
                    </div>
                </label>

                <label class="metodo-card-btn" id="card_metodo_2" data-method="PESO_CANTIDAD">
                    <input type="radio" name="metodo_entrada" value="PESO_CANTIDAD" style="display:none;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-weight:700; font-size:1rem; color:var(--text-primary);">Método 2: Peso y Cantidad de Especies</div>
                            <div style="font-size:0.8rem; color:var(--text-secondary);">Lote consolidado de reses con cálculo automático de peso y costo promedio.</div>
                        </div>
                        <span class="badge badge-info">Lote</span>
                    </div>
                </label>

                <label class="metodo-card-btn" id="card_metodo_3" data-method="PESO_INDIVIDUAL">
                    <input type="radio" name="metodo_entrada" value="PESO_INDIVIDUAL" style="display:none;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-weight:700; font-size:1rem; color:var(--text-primary);">Método 3: Pieza por Pieza (Pesaje Separado)</div>
                            <div style="font-size:0.8rem; color:var(--text-secondary);">Captura individual de cada res/novillo recibido (ej. 300 o 350 canales).</div>
                        </div>
                        <span class="badge badge-warning">Individual</span>
                    </div>
                </label>
            </div>
        </div>
    </div>

    <!-- SECCIÓN DINÁMICA: PESAJE SEGÚN EL MÉTODO -->

    <!-- PANEL PARA MÉTODO 1 (SOLO PESO) Y MÉTODO 2 (PESO Y CANTIDAD) -->
    <div id="panel_pesos_estandar" class="card" style="margin-bottom:1.5rem;">
        <h3 class="card-title" style="font-size:1.15rem; margin-bottom:1.25rem;">
            3. Registro de Pesaje Global de la Carga
        </h3>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1.25rem; margin-bottom:1.25rem;">
            <div class="form-group" id="grp_cantidad_especies" style="display:none; margin-bottom:0;">
                <label class="form-label" for="rec_cantidad">N° Total de Especies / Reses *</label>
                <input type="number" name="cantidad" id="rec_cantidad" class="form-input" min="1" value="1" style="font-size:1.15rem; font-weight:700;">
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" for="rec_peso_bruto">Peso Bruto (Kg) *</label>
                <input type="number" step="0.01" min="0" name="peso_bruto" id="rec_peso_bruto" class="form-input" value="0.00" style="font-size:1.15rem; font-weight:700;">
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" for="rec_tara">Tara Transporte / Ganchos (Kg)</label>
                <input type="number" step="0.01" min="0" name="tara" id="rec_tara" class="form-input" value="0.00" style="font-size:1.15rem; font-weight:700;">
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" for="rec_peso_neto">Peso Neto Obtenido (Kg)</label>
                <input type="number" step="0.01" min="0" name="peso_neto" id="rec_peso_neto" class="form-input" value="0.00" readonly style="background:var(--bg-main); font-size:1.25rem; font-weight:700; color:var(--primary); border-color:var(--primary);">
            </div>
        </div>

        <!-- Resumen de Promedios para Método 2 -->
        <div id="box_promedio_cabeza" style="display:none; background:var(--bg-main); border:1px solid var(--border-color); border-radius:10px; padding:1.25rem;">
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; text-align:center;">
                <div>
                    <div style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Peso Promedio por Res</div>
                    <div id="lbl_peso_promedio" style="font-size:1.5rem; font-weight:800; color:var(--primary);">0.00 kg</div>
                </div>
                <div>
                    <div style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Costo Estimado por Cabeza</div>
                    <div id="lbl_costo_promedio" style="font-size:1.5rem; font-weight:800; color:var(--success);">$0.00</div>
                </div>
                <div>
                    <div style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Inversión Total de la Carga</div>
                    <div id="lbl_costo_total_estandar" style="font-size:1.5rem; font-weight:800; color:var(--text-primary);">$0.00</div>
                </div>
            </div>
        </div>
    </div>

    <!-- PANEL PARA MÉTODO 3: PESO POR ESPECIE SEPARADA (PESAJE INDIVIDUAL) -->
    <div id="panel_pesos_individual" class="card" style="display:none; margin-bottom:1.5rem;">
        <h3 class="card-title" style="font-size:1.15rem; margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center;">
            <span>3. Estación de Pesaje Individual de Canales</span>
            <span class="badge badge-warning" id="badgeTotalPiezasTop">0 Reses Capturadas</span>
        </h3>

        <!-- Métricas Principales del Pesaje Individual -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; background:var(--bg-main); border:1px solid var(--border-color); border-radius:10px; padding:1.25rem; margin-bottom:1.5rem; text-align:center;">
            <div>
                <div style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Piezas / Reses</div>
                <div id="lbl_ind_total_piezas" style="font-size:1.8rem; font-weight:800; color:var(--primary);">0</div>
            </div>
            <div>
                <div style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Peso Neto Total</div>
                <div id="lbl_ind_total_peso" style="font-size:1.8rem; font-weight:800; color:var(--text-primary);">0.00 kg</div>
            </div>
            <div>
                <div style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Peso Promedio</div>
                <div id="lbl_ind_peso_promedio" style="font-size:1.8rem; font-weight:800; color:var(--accent);">0.00 kg</div>
            </div>
            <div>
                <div style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Valor Estimado del Lote</div>
                <div id="lbl_ind_costo_total" style="font-size:1.8rem; font-weight:800; color:var(--success);">$0.00</div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:1.5rem;">
            <!-- Captura de Peso y Teclado Táctil -->
            <div style="background:var(--bg-main); border:1px solid var(--border-color); border-radius:10px; padding:1.25rem;">
                <label class="form-label" style="margin-bottom:0.75rem;">Visor de Captura de Peso</label>
                
                <div style="display:flex; gap:0.5rem; margin-bottom:1rem;">
                    <input type="number" step="0.01" min="0.1" id="input_peso_individual" class="form-input" placeholder="0.00" style="font-size:1.75rem; font-weight:800; text-align:right; font-family:monospace; color:var(--primary); height:60px;">
                    <button type="button" class="btn btn-primary" id="btnAgregarPieza" style="width:auto; min-width:140px; font-size:1.05rem;" data-action="agregar-pieza">
                        <svg class="icon" viewBox="0 0 24 24" style="width:20px; height:20px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        + Agregar
                    </button>
                </div>

                <!-- Teclado Numérico Táctil Rápido -->
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
                    <button type="button" class="keypad-btn" data-key="0">0</button>
                    <button type="button" class="keypad-btn" data-key=".">.</button>
                    <button type="button" class="keypad-btn" data-key="clear" style="background:#b83a22; color:#fff;">C</button>
                </div>
            </div>

            <!-- Lista de Reses Recibidas con Scroll -->
            <div style="background:var(--bg-main); border:1px solid var(--border-color); border-radius:10px; padding:1.25rem; display:flex; flex-direction:column;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; border-bottom:1px solid var(--border-color); padding-bottom:0.5rem;">
                    <span style="font-weight:700; color:var(--text-primary);">Desglose de Animales Ingresados</span>
                    <button type="button" class="btn btn-outline btn-sm" id="btnLimpiarPiezas" style="font-size:0.75rem; padding:0.25rem 0.5rem; color:#fa8f65;" data-action="limpiar-piezas">
                        Limpiar Lista
                    </button>
                </div>

                <div id="lista_piezas_individuales" style="flex:1; max-height:280px; overflow-y:auto; display:flex; flex-direction:column; gap:0.4rem; padding-right:0.25rem;">
                    <div id="msgSinPiezas" style="color:var(--text-muted); font-size:0.9rem; text-align:center; padding:3rem 1rem;">
                        <svg class="icon" viewBox="0 0 24 24" style="width:40px; height:40px; stroke:var(--border-color); margin-bottom:0.5rem;"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                        <div>No se han registrado pesajes todavía.</div>
                        <div style="font-size:0.8rem; margin-top:0.25rem;">Ingrese el peso de cada res y presione "+ Agregar" o la tecla Enter.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- BARRA INFERIOR DE ACCIONES -->
    <div class="card" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <a href="/recepcion" class="btn btn-outline" style="text-decoration:none;">
            Cancelar y Regresar
        </a>
        <button type="submit" class="btn btn-primary" id="btnProcesarRecepcion" style="width:auto; min-width:240px; font-size:1.1rem; padding:1rem 2rem;">
            <svg class="icon" viewBox="0 0 24 24" style="width:20px; height:20px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            Guardar y Registrar Recepción
        </button>
    </div>
</form>

<style>
.metodo-card-btn {
    display: block;
    padding: 1rem 1.25rem;
    border-radius: 10px;
    background: var(--bg-main);
    border: 2px solid var(--border-color);
    cursor: pointer;
    transition: all 0.2s ease;
}
.metodo-card-btn:hover {
    border-color: var(--accent);
    background: rgba(244, 107, 58, 0.05);
}
.metodo-card-btn.active {
    border-color: var(--primary);
    background: rgba(244, 107, 58, 0.12);
    box-shadow: 0 0 12px rgba(244, 107, 58, 0.2);
}
.keypad-btn {
    padding: 0.85rem 0.5rem;
    font-size: 1.25rem;
    font-weight: 700;
    text-align: center;
    border-radius: 8px;
    background: #43342d;
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.1s ease;
}
.keypad-btn:hover {
    background: #55433a;
    border-color: var(--primary);
}
.keypad-btn:active {
    background: var(--primary);
    color: #fff;
    transform: scale(0.96);
}
.pieza-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    padding: 0.65rem 1rem;
    border-radius: 8px;
    transition: border-color 0.15s ease;
}
.pieza-item:hover {
    border-color: var(--primary);
}
.pieza-btn-eliminar {
    background: transparent;
    border: none;
    color: #fa8f65;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.pieza-btn-eliminar:hover {
    background: rgba(238, 103, 55, 0.2);
}
</style>

<script src="/js/recepcion_nueva.js"></script>

<?php
$content = ob_get_clean();
$title = 'Nueva Recepción de Canales - ST Despostadora';
$pageTitle = 'Registro de Recepción en Canal';
$activeNav = 'recepcion';

require __DIR__ . '/layout.php';
