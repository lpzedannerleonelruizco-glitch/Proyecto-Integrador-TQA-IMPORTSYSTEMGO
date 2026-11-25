# ADR 0001: autorización global y por sucursal

- Estado: aceptada
- Fecha: 2026-10-21

## Contexto

Los empleados pueden desempeñar varios roles en distintas sucursales. Además, existen cargos globales con capacidades limitadas y jefes de tienda que pueden consultar indicadores consolidados sin operar fuera de su sucursal.

Spatie Laravel Permission Teams resuelve asignaciones por sucursal, pero su asignación de usuario siempre requiere un equipo. Replicar un rol global en cada sucursal produciría ventanas de error al crear nuevas sucursales y dificultaría la revocación inmediata.

## Decisión

- Usar Spatie Laravel Permission 8.3 con Teams y `branch_id` para roles locales.
- Mantener membresías de usuario separadas de los roles.
- Usar `global_user_roles` para asignaciones globales con vigencia.
- Evaluar capacidades globales mediante `GlobalAuthorizationService`, sin `Gate::before` universal.
- Hacer que Policies y Services comprueben también la pertenencia del recurso.
- Limpiar equipo y relaciones en memoria al cambiar de sucursal.
- Prohibir permisos directos a usuarios.

## Consecuencias

- La autorización global es explícita y no necesita replicarse por sucursal.
- Existe una capa propia pequeña que debe mantenerse junto con Spatie.
- Las asignaciones locales requieren coherencia entre membresía y pivot polimórfico; el servicio la garantiza transaccionalmente.
- Los trabajos sin sesión HTTP deben establecer y limpiar su contexto de sucursal explícitamente.
- La administración de roles no debe habilitarse en producción hasta incorporar auditoría funcional.
