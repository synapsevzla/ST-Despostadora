# Directivas para Agentes de IA (AGENTS.md) - ST Despostadora

Este archivo define las reglas globales y directivas de comportamiento para cualquier agente de IA (Gemini, Claude, Cursor, Copilot, etc.) que opere en este repositorio.

---

## 1. Regla de Exclusión de Directorios (Obligatoria)

> [!IMPORTANT]
> **DIRECTORIO EXCLUIDO: `promocionales/`**
> - El directorio `promocionales/` contiene renders de video MP4, proyectos de composición audiovisual, capturas de pantalla de alta resolución y archivos multimedia de marketing.
> - **Los agentes NUNCA deben leer, indexar, buscar, greppear o analizar archivos dentro de `promocionales/`.**
> - Este directorio está ignorado en `.gitignore` para no ser subido a GitHub y debe ser tratado como una caja negra de entrega final para el usuario.
> - Otros directorios excluidos de lectura profunda: `dist_windows/`, `dist_linux/`, `dist_web/`, `dist_webxampp/`, `.git/`, `yakpro-po/`.

---

## 2. Filosofía y Stack del Proyecto

- **Filosofía Ponytail:** Soluciones mínimas, directas, eficientes, sin dependencias superfluas ni abstracciones especulativas (YAGNI).
- **Backend:** PHP 8.1+ puro, con `declare(strict_types=1);` obligatorio en la primera línea. Sin frameworks pesados. Consultas PDO estrictamente preparadas para Microsoft SQL Server 2019+.
- **Frontend:** HTML5 semántico, CSS3 puro (Mobile-First y Touch-Friendly), JavaScript vanilla ES6+ sin librerías externas.
- **Base de Datos:** Microsoft SQL Server 2019+ integrado con el ERP Saint Enterprise Administrativo. Las tablas propias usan el prefijo obligatorio `ST_Despostadora_`.
- **Límite de Modularidad:** Ningún archivo debe superar las **600 líneas de código**. Responsabilidad única por archivo.

---

## 3. Seguridad Estricta

1. **SQL Injection:** 100% sentencias preparadas con `PDO::prepare()` y binding tipado (`bindValue`). Concatenación directa estrictamente prohibida.
2. **XSS & CSRF:** Salidas escapadas con `htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`. Tokens CSRF criptográficos obligatorios en toda petición mutativa (POST/PUT/DELETE).
3. **Cero ejecución arbitraria:** Prohibido el uso de `exec()`, `shell_exec()`, `system()`, `eval()`, `unserialize()`, `extract()`.
