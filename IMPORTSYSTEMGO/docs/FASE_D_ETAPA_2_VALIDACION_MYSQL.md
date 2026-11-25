# Fase D, Etapa 2: validación MySQL

Fecha: 2026-10-21.

## Alcance y aislamiento

- Base validada: `importsystemgo_testing`.
- Cuenta efectiva: `importsystemgo_test@localhost`.
- La cuenta tiene `USAGE` global y privilegios DDL/DML exclusivamente sobre la base de pruebas.
- Una lectura explícita de `importsystemgo.users` fue rechazada por MySQL.
- La credencial de pruebas está cifrada mediante DPAPI CurrentUser fuera del repositorio.
- La base principal permaneció con 9 tablas, 3 migraciones aplicadas y ninguna tabla empresarial.

## Correcciones realizadas

1. Se agregó `employees_valid_employment_dates` para impedir que la fecha de baja preceda a la fecha de contratación.
2. Se agregó `branch_memberships_valid_period` para exigir que el final de una membresía sea posterior a su inicio.
3. El índice temporal de membresías ahora comienza por `user_id, branch_id` para ajustarse a las consultas de solapamiento.
4. Se agregó `roles_valid_scope` para limitar el alcance a `global` o `branch` y exigir `branch_id=NULL` en roles globales.
5. Se agregó `global_roles_valid_period` para validar la vigencia de roles globales.
6. Se agregó un bootstrap PHPUnit que detiene la ejecución antes de `RefreshDatabase` si la base o la cuenta efectivas no son las autorizadas.
7. Se agregaron pruebas MySQL de columnas generadas, restricciones, aislamiento entre bases y periodos inválidos.
8. Se agregó una prueba de idempotencia del seeder de autorización.
9. El primer test de introspección se corrigió para normalizar los nombres en mayúsculas devueltos por `INFORMATION_SCHEMA`.

El primer intento de generación de contraseña no llegó a MySQL porque la versión de PowerShell no exponía el método criptográfico estático solicitado. Se sustituyó por `RandomNumberGenerator.Create().GetBytes`. El intento de copiar literalmente el propietario ACL del almacén anterior fue rechazado después de crear el archivo; se verificó que el nuevo archivo conserva el propietario esperado, ACL heredada y recuperación DPAPI correcta.

## Resultado del esquema

- Tablas: 19 de 19 esperadas.
- Migraciones: 10 de 10, incluidas las 7 empresariales.
- Claves foráneas: 19.
- Restricciones `CHECK` aplicadas: 4, todas `ENFORCED`.
- Columnas generadas almacenadas: 3.
- Configuración: InnoDB, `utf8mb4`, `utf8mb4_0900_ai_ci` y modo estricto.

## Pruebas

- PHPUnit sobre MySQL: 19 pruebas aprobadas, 44 aserciones, 0 fallos y 0 omitidas.
- Laravel Pint: aprobado.
- Se comprobaron roles distintos en dos sucursales, cambio de contexto, permisos globales limitados, denegación cruzada, revocación inmediata, periodos solapados, unicidad con `NULL` e idempotencia del seeder.

## Riesgos residuales

- Las restricciones entre tablas, como exigir que todo rol local corresponda a una membresía vigente, siguen dependiendo de servicios transaccionales; una escritura SQL manual podría eludirlas.
- Las pruebas no simulan todavía dos transacciones MySQL concurrentes intentando crear periodos históricos solapados.
- La cuenta de pruebas conserva `DROP` porque PHPUnit necesita reconstruir su esquema; no debe reutilizarse fuera de pruebas.
- La credencial DPAPI solo debe recuperarse bajo el usuario de Windows autorizado.

## Recomendación

Las migraciones son aptas para pasar al punto de aprobación de la base principal. Antes de ejecutarlas en `importsystemgo` se debe realizar un respaldo verificable, repetir la identidad efectiva de conexión y obtener autorización explícita. Esta validación no autoriza ese paso.
