# Sistema de Migraciones de Base de Datos - ST Despostadora

Las migraciones permiten evolucionar la base de datos de manera controlada, segura y automatizada para Microsoft SQL Server.

## Reglas Obligatorias de Migración

1. **Ubicación de archivos:**
   - Todos los scripts deben almacenarse en este directorio: `database/migrations/`.

2. **Inmutabilidad estricta (Sin re-edición ni reemplazo):**
   - Una vez que un archivo de migración ha sido creado y aplicado, **nunca debe modificarse ni eliminarse**.
   - El sistema almacena un checksum SHA-256 de cada migración aplicada. Si detecta que un archivo aplicado fue alterado, bloqueará la ejecución con una excepción.
   - Cualquier ajuste, corrección de bugs o nuevo cambio debe implementarse en un **nuevo archivo de migración**.

3. **Nomenclatura de Tablas:**
   - Todas las tablas creadas por el sistema deben tener el prefijo:
     `ST_Despostadora_NombreTabla` (ejemplo: `ST_Despostadora_Canales`, `ST_Despostadora_Pesajes`).

4. **Nomenclatura de Archivos de Migración:**
   - Formato estricto: `AAAAMMDD_HHMMSS_NombreMigracion.sql`
   - Ejemplo: `20261113_114500_CreateInitialTables.sql`

5. **Ejecución Autónoma:**
   - Las migraciones se evalúan y ejecutan automáticamente al iniciar la aplicación (`public/index.php`) y al realizar un login exitoso (`AuthController.php`), siempre que el `.env` esté configurado y haya conexión a la BD.

6. **Protección de Tablas Existentes del ERP:**
   - Las migraciones **tienen prohibido** modificar o alterar cualquier tabla del sistema ERP existente.
   - Solo se permiten sentencias `CREATE TABLE` con prefijo `ST_Despostadora_` y sentencias `ALTER TABLE` o `DROP TABLE` dirigidas a tablas que comiencen por `ST_` o `STB_`. El ejecutor valida esto automáticamente antes de procesar el archivo.
