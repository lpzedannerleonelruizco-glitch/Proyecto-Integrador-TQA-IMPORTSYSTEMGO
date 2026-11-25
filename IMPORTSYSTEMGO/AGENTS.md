# Reglas permanentes para agentes y colaboradores

## Alcance

Actualización E1-001 (2026-10-21): PROMPT MAESTRO 003 autoriza continuar el desarrollo por entregas y preparar migraciones. Leer primero `PROGRESO.md`, `DECISIONES.md` y `PRUEBAS.md` para continuidad. Los límites históricos de Fase C/D no impiden desarrollo de código, pero siguen prohibidas nuevas migraciones/seeders principales sin aprobación, cambios administrativos y producción fiscal. No declarar completa Entrega 1: falta administración web de usuarios, membresías y roles, entre otros pendientes documentados.

Las pruebas deben entrar por `phpunit.xml` (SQLite en memoria) o `phpunit.mysql.xml` (cuenta exclusiva). No retirar la comprobación efectiva de conexión en `Tests\TestCase::createApplication`, que corre antes de RefreshDatabase. No ejecutar el comando de administrador inicial automáticamente: pide autorización de datos iniciales y secretos por terminal oculta.

Estas reglas aplican a todo cambio en IMPORTSYSTEMGO. Antes de modificar código o datos, leer `REQUISITOS.md`, `ARQUITECTURA.md`, `PERMISOS.md`, `MODELO_DATOS.md` y `ROADMAP.md` en lo que corresponda.

## Reglas obligatorias

1. Respetar las decisiones confirmadas. Una recomendación o decisión pendiente no se convierte en requisito sin autorización explícita.
2. No modificar el esquema de base de datos manualmente: todo cambio se realiza mediante migraciones versionadas, revisadas y probadas.
3. No ejecutar comandos destructivos, migraciones de reversión, reinicios de datos, borrados masivos ni operaciones equivalentes sin autorización específica y respaldo verificado.
4. No exponer secretos en código, Git, logs, comandos, capturas, pruebas, documentación o respuestas. Usar variables de entorno o un gestor de secretos aprobado.
5. Validar autenticación, permisos y alcance en el servidor mediante middleware, Policies y reglas de dominio. La interfaz nunca es una frontera de seguridad.
6. Mantener aislamiento por sucursal en lecturas, escrituras, exportaciones, trabajos en cola y reportes. No confiar en un `branch_id` enviado por el cliente.
7. Usar transacciones y el control de concurrencia documentado en operaciones críticas de inventario, reservas, ventas, caja, pagos, créditos y facturación.
8. No inventar integraciones fiscales. Verificar normativa, esquemas, catálogos, endpoints y procedimientos con fuentes oficiales vigentes del SIN y registrar la versión usada.
9. Escribir pruebas automatizadas para toda funcionalidad nueva: caso permitido, caso denegado, límites de ámbito, concurrencia e idempotencia cuando apliquen.
10. Mantener actualizada la documentación y registrar las decisiones relevantes como ADR antes o junto con el cambio.

## Convenciones de implementación

- Mantener el monolito modular; no crear microservicios sin una decisión arquitectónica aprobada.
- Mantener Controllers delgados, Form Requests para entrada, Policies para autorización y Services/Actions para casos de uso.
- Evitar lógica de negocio en vistas, controladores, observers genéricos o modelos excesivamente amplios.
- Usar identificadores internos sin significado empresarial y códigos públicos únicos cuando sean necesarios.
- Usar `DECIMAL` para dinero y cantidades que requieran precisión; nunca `float` o `double`.
- Toda clave foránea debe declarar su comportamiento al actualizar/eliminar. En historia transaccional se prefiere restringir, archivar o anular antes que borrar.
- No permitir asignación masiva de campos sensibles como estado, sucursal, autorización, importe aprobado o actor.
- Los eventos de dominio se publican después de confirmar la transacción. Para efectos externos, usar outbox e idempotencia.
- Los jobs deben ser reintentables, idempotentes y conservar el contexto de sucursal y correlación.
- Los logs deben ser estructurados y redactar tokens, contraseñas, claves, certificados y datos sensibles.

## Flujo mínimo de cada cambio

1. Identificar requisito confirmado, recomendación o decisión pendiente.
2. Definir autorización, ámbito, invariantes y riesgos.
3. Implementar la unidad mínima dentro del módulo propietario.
4. Añadir migración solo si fue autorizada; revisar `up()` y `down()` sin ejecutarla automáticamente.
5. Añadir pruebas y documentación.
6. Ejecutar análisis, pruebas y compilación aplicables.
7. Informar archivos cambiados, comandos ejecutados, resultados y cualquier riesgo residual.

## Límites actuales

- La Fase D, Etapa 1, autoriza preparar organización, identidad y RBAC.
- La Fase D, Etapa 2, validó las migraciones exclusivamente en `importsystemgo_testing` con una cuenta aislada.
- Las siete migraciones empresariales D1 ya fueron aplicadas y verificadas en `importsystemgo`; no volver a ejecutarlas, revertirlas ni alterarlas manualmente.
- Cualquier migración posterior requiere una nueva autorización explícita, respaldo previo y verificación de identidad de la conexión.
- Todo comando destructivo de pruebas debe verificar primero que la base efectiva es `importsystemgo_testing` y la cuenta es `importsystemgo_test@localhost`.
- No ejecutar seeders contra la base principal sin autorización.
- No emitir facturas reales ni conectar a producción del SIN.
- No publicar el repositorio ni crear recursos externos sin autorización.

