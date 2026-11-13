# Soluciones Totales - Despostadora (ST-Despostadora)

Sistema industrial y operativo de alto rendimiento para el **control, pesaje, desglose cárnico, rendimiento porcentual y costeo con absorción de merma**, integrado con Microsoft SQL Server 2019+ del ERP administrativo.

Desarrollado bajo la filosofía **/ponytail**: soluciones nativas mínimas, sin sobreingeniería, sin dependencias pesadas y con estricto límite de modularidad (**ningún archivo excede las 600 líneas de código**).

---

## 1. Principios Arquitectónicos y Reglas Inviolables

1. **Invariabilidad Estricta de `SAPROD.Existen`:**
   Ninguna operación de recepción, pesaje o desposte modifica directamente la columna `SAPROD.Existen`. Toda existencia, canal y lote de corte se almacena, valoriza y controla exclusivamente en tablas propias `ST_Despostadora_Existencias`.
2. **Prefijo Obligatorio `ST_Despostadora_`:**
   Todas las tablas creadas en SQL Server utilizan el prefijo `ST_Despostadora_` para evitar colisiones con otros módulos del ERP.
3. **Correlativos Configurables sin `IDENTITY`:**
   Manejados en `ST_Despostadora_Correlativos` mediante transacciones atómicas con bloqueo pesimista `WITH (UPDLOCK, ROWLOCK)`. Formato alfanumérico configurable de hasta 20 caracteres.
4. **Trazabilidad y Auditoría Inmutable:**
   Cada apertura, pesaje, anulación o cierre registra auditoría con timestamp UTC (`SYSUTCDATETIME()`), IP, usuario y diffs estructurados en JSON en `ST_Despostadora_Auditoria`.

---

## 2. Módulos Implementados

### 2.1 Módulo de Configuración (`/configuracion`)
- **Depósito Operativo (`SADEPO`):** Selección del depósito de desposte donde se gestionará la carne en canal y los cortes terminados.
- **Correlativos del Sistema:** Configuración de prefijo (máx 10 caracteres), longitud total (máx 20 caracteres), inicio/secuencia y previsualización en tiempo real para:
  - `RECEPCION` (`REC-0000000000000001`)
  - `DESPOSTE` (`DESP-000000000000001`)
  - `TRANSFERENCIA` (`TRF-0000000000000001`)
  - `LOTE` (`LOT-`)
- **Maestro de Especies y Matriz de Cortes:** Vinculación de especies de canal (`SAPROD` con `ExDecimal=1 AND EsEmpaque=0`), producto asignado para merma/desperdicio, margen comercial predeterminado y catálogo de cortes autorizados con rendimientos esperados y vidas útiles (días de vencimiento).

### 2.2 Módulo de Recepción de Carne en Canal (`/recepcion`)
Soporta tres métodos operativos de ingreso:
1. **Solo Peso:** Registro por pesada global (peso bruto, tara, peso neto y costo total según `SAPROD.CostAct`).
2. **Peso y Cantidad:** Pesaje global con conteo de cabezas/canales y cálculo de peso promedio automático.
3. **Peso por Especie Separada (Individual):** Pesaje pieza por pieza (ej. 3 novillos de 285.5 kg, 310.2 kg y 298.3 kg) con detalle granular en `ST_Despostadora_RecepcionesDetalle` y seguimiento de estado independiente (`DISPONIBLE`, `EN_DESPOSTE`, `DESPOSTADO`).

### 2.3 Módulo de Sala de Desposte Multi-Línea (`/desposte` o `/deboning`)
- **Tablero Multi-Mesa en Paralelo:** Supervisión y control simultáneo de múltiples líneas o mesas de desposte activas (`Mesa 1`, `Mesa 2`, `Línea A`, etc.).
- **Estación Táctil de Pesaje:**
  - Botonera táctil de cortes permitidos según la especie de la res.
  - Teclado numérico táctil en pantalla (keypad decimal) y soporte para básculas de sala.
  - **Generación Automática de Lotes (`NroLote`):** Formato estructurado `LOT-AAAAMMDD-ORDEN-ITEM` (ej. `LOT-20261113-0004-01`) con cálculo automático de fecha de elaboración y fecha de vencimiento según la vida útil configurada.
  - Feedback auditivo/sonoro nativo mediante Web Audio API al registrar pesajes o alertar errores.
  - Anulación/reversión de pesajes en caliente antes del cierre.
  - **Alerta de Descuadre Físico:** Detección visual inmediata e impeditiva de cierre si el total de cortes excede el peso de la canal ($P_{cortes} > P_{canal}$).

### 2.4 Motor de Rendimiento y Costeo con Absorción de Merma
- **Rendimiento Útil:** $(P_{cortes} / P_{canal}) \times 100$
- **Merma Física:** $P_{merma} = P_{canal} - P_{cortes}$ en kilogramos y porcentaje.
- **Pérdida Monetaria por Merma:** $P_{merma} \times CostAct_{canal}$
- **Distribución de Absorción de Merma:** La pérdida monetaria de la merma se distribuye ponderadamente entre todos los cortes pesados basándose en su valor comercial y en su costo base `SAPROD.CostAct`.
- **Costo Real Unitario ($/kg):** Refleja el costo exacto del corte tras absorber la merma generada por la res.
- **Precio de Venta Sugerido:** Calculado dinámicamente según el margen de rentabilidad configurado.
- **Cierre Atómico Definitivo:** En una única transacción SQL (`BEGIN TRAN...COMMIT`):
  - Fija los costos reales definitivos en `ST_Despostadora_DespostesDetalle`.
  - Carga los cortes producidos por lote en `ST_Despostadora_Existencias` con su costo unitario real y fecha de vencimiento.
  - Carga la merma en `ST_Despostadora_Existencias` si aplica.
  - Descarga definitiva de la carne en canal en `ST_Despostadora_Existencias`.
  - Marca la orden en `FINALIZADO` y la especie/recepción en `DESPOSTADO`.

---

## 3. Estructura de Tablas en SQL Server

| Tabla | Descripción |
|---|---|
| `ST_Despostadora_Correlativos` | Correlativos alfanuméricos configurables (máx 20 caracteres) con bloqueo pesimista. |
| `ST_Despostadora_Configuracion` | Parámetros globales (depósito `SADEPO`, márgenes, prefijos de lote). |
| `ST_Despostadora_EspeciesConfig` | Configuración de especies de canal y asignación de producto para merma. |
| `ST_Despostadora_EspeciesCortes` | Matriz de cortes autorizados por especie con días de vida útil. |
| `ST_Despostadora_Recepciones` | Encabezado de recepciones de canales (Métodos 1, 2 y 3). |
| `ST_Despostadora_RecepcionesDetalle` | Desglose individual de pesos por animal/pieza en Método 3. |
| `ST_Despostadora_Despostes` | Encabezado de órdenes de desposte en líneas/mesas. |
| `ST_Despostadora_DespostesDetalle` | Cortes pesados, lotes automáticos, costos reales y precios sugeridos. |
| `ST_Despostadora_Existencias` | Control granular de stock interno por lote, costo real y vencimiento. |
| `ST_Despostadora_Auditoria` | Bitácora inmutable de auditoría con fecha UTC, IP, usuario y JSON diff. |

---

## 4. Requisitos de Ejecución

- **PHP:** 8.1+ con extensión `pdo_sqlsrv` o `pdo_dblib`.
- **Base de Datos:** Microsoft SQL Server 2019+.
- **Servidor Web:** Apache, Nginx o PHP Built-in Server.

Para iniciar el servidor de desarrollo local:
```bash
php -S 0.0.0.0:8000 -t public/
```
