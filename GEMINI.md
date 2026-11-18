# GEMINI.md - Soluciones Totales Despostadora

## 1. Visión y Alcance
Aplicación enfocada en la gestión, control y registro operativo del proceso de **desposte y procesamiento cárnico** (canales, cortes primarios, secundarios, subproductos, mermas y rendimientos cárnicos), conectada a **Microsoft SQL Server (2019+)** del sistema administrativo ERP.
Objetivo principal: Permitir el registro preciso y ágil de pesajes, desglose de canales bovinas/porcinas, control de rendimiento porcentual de cortes versus peso en canal, registro de mermas y generación atómica de movimientos de inventario en el ERP con tolerancia y ergonomía operativa en salas de proceso.

Stack tecnológico:
- **Backend:** PHP puro 8.1+ (sin frameworks pesados, PDO `sqlsrv`/`dblib` con sentencias preparadas estrictas).
- **Frontend:** HTML5 semántico, CSS3 puro (Mobile-First y Touch-Friendly, sin dependencias como Bootstrap o Tailwind), JavaScript vanilla moderno (ES6+, sin dependencias externas, soporte para básculas/pesaje, escaneo de códigos de barra nativo y feedback auditivo).
- **Base de Datos:** Microsoft SQL Server 2019+ (usando funciones nativas modernas como `OPENJSON`, `TRIM`, `IIF`, tipos estrictos y transacciones atómicas `BEGIN TRAN...COMMIT/ROLLBACK`).
- **Filosofía de desarrollo:** `/ponytail` (soluciones mínimas, nativas, sin sobreingeniería, cero boilerplate innecesario, código directo y conciso).
- **Límite de modularidad:** Ningún archivo debe exceder las **600 líneas de código**. Modularidad por responsabilidad única.

---

## 1.1 Flujo Operativo de Despostadora

1. **Recepción e Ingreso de Canales / Materia Prima:**
   - Selección de lote / proveedor y pesaje inicial de canal (cuarto delantero, cuarto trasero, media canal).
   - Verificación de peso recibido vs peso documentado y cálculo de merma por oreo/frío.

2. **Proceso de Desposte y Pesaje de Cortes (Cortes con Hueso, Pulpa, Grasa, Hueso, Merma):**
   - Registro de peso por corte con captura directa (teclado táctil optimizado para decimales / integración de pesaje).
   - Comparación en tiempo real: Peso Total Cortes Obtenidos vs. Peso Inicial de Canal.
   - Cálculo automático del porcentaje de rendimiento y merma de desposte.

3. **Cierre y Generación de Movimientos de Inventario:**
   - Descargo de materia prima (Canal) y cargo de productos terminados/cortes en las tablas del sistema administrativo.
   - Persistencia atómica mediante transacciones seguras en SQL Server.

---

## 1.2 Integración y Consultas SQL del Sistema Administrativo

### 1. Autenticación de Usuarios (`ssusrs`)
Inicio de sesión estricto (solo lectura / validación contra usuarios del ERP):
- **Campos de validación:** Código de Usuario (`CodUsua`), `Email` y `Clave` reconstruida desde `SDATA1`, `SDATA2` y `SDATA3`.
```sql
SELECT codusUa, descrip, email,
   RTRIM(LTRIM(SUBSTRING(SDATA3,175,1)+
   SUBSTRING(SDATA1,33,1)+
   SUBSTRING(SDATA2,90,1)+
   SUBSTRING(SDATA3,14,1)+
   SUBSTRING(SDATA1,207,1)+
   SUBSTRING(SDATA3,111,1)+
   SUBSTRING(SDATA3,145,1)+
   SUBSTRING(SDATA2,180,1)+
   SUBSTRING(SDATA2,9,1)+
   SUBSTRING(SDATA3,53,1))) AS Clave,
   RTRIM(LTRIM(SUBSTRING(SDATA2,193,1)+
   SUBSTRING(SDATA1,77,1))) AS Nivel,
   ISNULL(CodVend,'') AS CodVend 
FROM ssusrs
WHERE CodUsua = :codusua AND Email = :email
```

### 2. Validación de Licencia del Sistema (`SACONF` + Firebase RTDB)
Consulta de Serial y Razón Social de la empresa:
```sql
SELECT TOP 1 NroSerial, Descrip 
FROM SACONF 
WHERE CodSucu = '00000' OR CodSucu IS NULL 
ORDER BY CASE WHEN CodSucu = '00000' THEN 0 ELSE 1 END
```

### 3. Catálogo de Artículos y Cortes de Desposte (`SAPROD`, `SAEXIS`, `SADEPO`)
Consulta de productos clasificados para materia prima y cortes cárnicos:
```sql
SELECT 
    p.CodProd,
    p.Descrip,
    p.Descrip2,
    p.Descrip3,
    ISNULL(p.UndEmpaq, p.Unidad) AS Unidad,
    p.ExDecimal,
    ISNULL(e.Existen, 0) AS ExistenciaActual,
    ISNULL(cb.CodAlte, '') AS CodigoBarra
FROM SAPROD p
LEFT JOIN SAEXIS e ON e.CodProd = p.CodProd AND e.CodUbic = :codubic
LEFT JOIN SACODBAR cb ON cb.CodProd = p.CodProd
WHERE p.Inactivo = 0
ORDER BY p.Descrip
```

---

## 2. Matriz de Seguridad y Mitigación Estricta

El proyecto implementa defensas obligatorias contra vulnerabilidades críticas:

| Vulnerabilidad | Estrategia de Mitigación en el Código |
|---|---|
| **SQLi (SQL Injection)** | 100% sentencias preparadas con `PDO::prepare()` y parámetros tipados (`bindValue`). Prohibida terminantemente la concatenación directa de variables. |
| **OS Command Injection** | Prohibido el uso de `exec()`, `shell_exec()`, `system()`, `passthru()`, `proc_open()`, backticks. |
| **PHP Code Injection** | Prohibición absoluta de `eval()`, `create_function()`, `assert()` con strings y `preg_replace` con modificador `/e`. |
| **LFI / RFI** | Carga de módulos mediante lista blanca estricta (whitelisting) de rutas fijas relativas. Prohibido `include`/`require` dinámico basado en input del usuario. |
| **Unrestricted File Upload** | En caso de adjuntos: verificación estricta de MIME real (`finfo`), hashes aleatorios para el nombre y almacenamiento fuera del webroot. |
| **PHP Object Injection** | Prohibido el uso de `unserialize()`. Uso exclusivo de `json_encode()` y `json_decode()`. |
| **PHP Wrappers Abuse** | Prohibido pasar variables de usuario a funciones de filesystem (`file_get_contents`, `include`) con esquemas `php://`, `phar://`, `data://`. |
| **Reflected & Stored XSS** | Salida siempre escapada con `htmlspecialchars($val, ENT_QUOTES \| ENT_SUBSTITUTE, 'UTF-8')`. Cabecera `Content-Security-Policy` restrictiva sin inline scripts (`addEventListener`). |
| **CSRF** | Tokens anti-CSRF criptográficos (`random_bytes(32)`) generados por sesión y validados con `hash_equals()` en todas las peticiones POST/PUT/DELETE. |
| **Session Hijacking / Fixation** | `session_regenerate_id(true)` tras login exitoso. Flags de cookies: `HttpOnly`, `Secure` (en HTTPS), `SameSite=Strict`. |
| **Brute Force & Credential Stuffing** | Rate limiter nativo por IP y usuario almacenado en caché/memoria o tabla temporal. |
| **SSRF** | Prohibido realizar peticiones HTTP salientes arbitrarias controladas por usuario. Solo llamadas a servicios autorizados (Firebase/Hora oficial) con URLs fijas. |
| **PHP Type Juggling** | Comparaciones estrictas obligatorias (`===` y `!==`). Tipado estricto `declare(strict_types=1);` en la primera línea de cada archivo PHP. |
| **extract() Global Overwriting** | Prohibido el uso de `extract()`. Variables procesadas explícitamente desde `$_POST`, `$_GET`, etc. |
| **Exposición de Información** | `display_errors = Off` y `display_startup_errors = Off` en producción. Manejo centralizado de excepciones con logging seguro a archivo y respuestas de error genéricas al usuario. |

---

## 3. Estructura de Directorios del Proyecto

```text
/
├── .env                      # Variables de entorno y credenciales (ignorado en git)
├── .env.example              # Plantilla de variables de entorno
├── .gitignore                # Reglas de exclusión de git
├── config/
│   ├── database.php          # Parser nativo de .env y conexión Singleton PDO SQL Server
│   └── security.php          # Cabeceras HTTP, sesión segura, CSRF y sanitización
├── database/
│   └── migrations/           # Scripts SQL inmutables (AAAAMMDD_HHMMSS_Nombre.sql)
├── src/
│   ├── Auth/
│   │   ├── AuthController.php# Login contra ssusrs, logout y control de acceso
│   │   ├── LicenseService.php# Validación de licencia (SACONF + Firebase RTDB)
│   │   └── RateLimiter.php   # Control de intentos fallidos sin dependencias
│   ├── Database/
│   │   └── MigrationRunner.php # Ejecutor autónomo de migraciones SQL con checksum y validaciones
│   ├── Deboning/             # Módulo Central de Desposte / Despostadora
│   │   ├── DeboningModel.php # Consultas, cálculos de rendimiento y transacciones SQL Server
│   │   └── DeboningController.php # Control de flujo, pesajes y endpoints API JSON
│   └── Utils/
│       ├── Assets.php        # SVG embebidos e isotipos oficiales
│       ├── Validator.php     # Validador y sanitizador de esquemas de entrada
│       └── View.php          # Renderizador de plantillas seguro con auto-escape
├── public/                   # Raíz pública del servidor web
│   ├── index.php             # Front controller, router minimalista y verificación de licencia
│   ├── css/
│   │   └── app.css           # Estilos Mobile-First y Touch-Friendly en CSS3 puro
│   └── js/
│       └── app.js            # Lógica cliente JS vanilla (pesaje, keypad, validaciones)
├── views/                    # Plantillas HTML limpias
│   ├── layout.php            # Shell principal HTML5 responsive
│   ├── login.php             # Pantalla de inicio de sesión
│   ├── deboning.php          # Interfaz operativa de desposte y pesaje
│   ├── db_error.php          # Pantalla de error estilizada de base de datos
│   └── license_error.php     # Pantalla de bloqueo/error de licenciamiento
├── promocionales/            # Videos promocionales y renders (IGNORADO POR AGENTES Y GIT)
├── GEMINI.md                 # Especificación técnica, reglas del agente y SQL
├── AGENTS.md                 # Instrucciones y directivas para agentes de IA
└── README.md                 # Documentación operativa
```

---

## 4. Reglas de Desarrollo "Ponytail" para el Agente
1. **YAGNI absoluto:** Implementar solo la funcionalidad estrictamente necesaria para la operativa de desposte.
2. **Nativo primero:**
   - Sin frameworks PHP (Vanilla PHP 8.1+ limpio y estructurado).
   - Sin ORM: PDO directo y optimizado para SQL Server 2019+.
   - Sin frameworks de UI (CSS3 nativo ergonómico para entornos táctiles/operativos).
   - Sin librerías JS pesadas (Vanilla ES6+ puro).
3. **Seguridad no negociable:** La simplicidad del código nunca prescinde de la sanitización, tokens CSRF, cabeceras seguras ni validaciones estrictas.
4. **Respeto a los límites de tamaño:** Ningún archivo debe exceder las 600 líneas de código.
5. **Exclusión estricta de directorios multimedia:** El directorio `promocionales/` contiene renders de video MP4, composiciones y assets audiovisuales de marketing. Los agentes (Gemini y otros) NUNCA deben leer, parsear, escanear ni buscar archivos dentro de `promocionales/` para evitar sobrecargar contexto con binarios o artefactos de medios.
