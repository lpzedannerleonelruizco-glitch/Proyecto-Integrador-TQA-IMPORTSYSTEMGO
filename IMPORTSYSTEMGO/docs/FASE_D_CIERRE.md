# Cierre de la Fase D

Fecha: 2026-10-21.

## Estado final

- Base principal: `importsystemgo`.
- Cuenta efectiva: `importsystemgo_app@localhost`.
- Servicio: `MySQL2610` en ejecución.
- Tablas: 19.
- Migraciones aplicadas: 10, sin migraciones pendientes.
- Claves foráneas: 19, con relaciones exactas verificadas.
- Restricciones `CHECK`: 4, todas aplicadas.
- Columnas generadas almacenadas: 3.
- Estructuras de índice críticas: 25, verificadas por tabla, unicidad, columnas y orden.
- Tablas fuera de InnoDB: 0.
- Modo SQL estricto: habilitado.

## Respaldo previo

Antes de migrar se generó un volcado completo con `mysqldump`, se comprobó la presencia de las nueve estructuras originales y la marca de finalización, y se verificó una recuperación byte a byte.

- Protección: Windows DPAPI CurrentUser.
- Ubicación: fuera del repositorio, bajo el directorio local seguro de IMPORTSYSTEMGO.
- SHA-256 del SQL original: `FFEBB40AEEA79224DB17667CE15A4C96A686E29EB14E3908B140AB75907D144F`.
- La copia temporal sin cifrar fue eliminada después de verificar el respaldo protegido.

## Verificación de índices

MySQL publica las claves primarias con el nombre reservado `PRIMARY`, aunque Laravel reciba nombres personalizados al definirlas. La discrepancia observada en `model_has_permissions`, `model_has_roles` y `role_has_permissions` era exclusivamente nominal. Sus columnas, orden y unicidad coinciden con las migraciones aprobadas.

El verificador final compara propiedades estructurales y no depende de nombres que MySQL normaliza.

## Pruebas

- Validación previa en MySQL aislado: 19 pruebas, 44 aserciones, 0 fallos.
- Regresión final en SQLite `:memory:`: 19 pruebas, 29 aserciones, 0 fallos y 4 omisiones exclusivas de MySQL.
- Verificador principal: conexión, identidad, tablas, migraciones, relaciones, restricciones, columnas generadas, índices, motor y modo estricto aprobados.
- No se ejecutó `RefreshDatabase`, seeder, rollback, fresh o wipe contra la base principal.

## Riesgos pendientes

- La coherencia entre membresías vigentes y roles locales depende también de servicios transaccionales; SQL manual puede eludir reglas de aplicación.
- Falta una prueba concurrente MySQL con dos transacciones intentando crear periodos históricos solapados.
- La cuenta exclusiva de pruebas conserva `DROP` para permitir la reconstrucción de `importsystemgo_testing` y no debe reutilizarse fuera de ese entorno.
- El respaldo DPAPI depende de la cuenta autorizada de Windows; su restauración debe ensayarse de forma controlada antes de depender de él como única copia.

La Fase D queda completada. Ninguna fase o módulo posterior queda autorizado por este cierre.
