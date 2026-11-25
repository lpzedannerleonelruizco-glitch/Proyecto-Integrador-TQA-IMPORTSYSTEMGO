# Decisiones de implementación

## ADR E1-001 — Núcleo sobre la instalación existente (2026-10-21)

PROMPT MAESTRO 003 autoriza desarrollo autónomo, preparar migraciones y pruebas aisladas; reemplaza el antiguo límite documental de Fase C/D para desarrollo. No autoriza ejecutar nuevas migraciones principales, sembrar datos reales automáticamente, alterar MySQL ni emitir facturas fiscales.

Se conserva Laravel/Blade/Bootstrap/Vite, sin dependencias adicionales ni SPA. Se utiliza autenticación de sesión de Laravel, regeneración tras login/cambio de sucursal y middleware `auth.session`. Referencias oficiales consultadas: https://laravel.com/docs/13.x/authentication y https://laravel.com/docs/13.x/passwords.

La recuperación utiliza el broker de Laravel. `PASSWORD_RESET_MAILER` debe seleccionar expresamente un transporte permitido. Se rechazan log, failover, roundrobin y transportes desconocidos. Array solo está permitido en testing. No se reportan excepciones originales del transporte, que podrían contener secretos; el evento de fallo es genérico. Las respuestas no revelan la existencia de cuentas. Pendiente configuración real de correo y supervisión operacional de fallos.

Spatie administra roles y permisos, pero no registra su comprobación automática en Gate. Policies y servicios propios consultan rol, guard `web`, alcance y vigencia. No hay bypass universal. Los permisos locales se consultan en SQL sin depender del caché de Spatie; `model_has_permissions` no concede acceso. Los roles globales solo conceden capacidades existentes explícitas, no propiedad sobre toda operación futura.

`ended_by` no nulo significa revocación explícita inmediata. `ends_at` mantiene un periodo válido (`> starts_at`) incluso al cancelar asignaciones futuras o creadas en el mismo segundo. `updated_at`/actor/motivo dejan evidencia de revocación; estas filas no vuelven a habilitarse por su fecha futura. Esta semántica aplica a todas las consultas de vigencia. La creación de membresías bloquea al usuario estable antes de buscar solapamientos; aún falta prueba paralela MySQL.

Los recursos organizativos usan campos definidos en servidor, no nombres de tabla o atributos arbitrarios del cliente. Almacenes y empleados se crean en la sucursal del contexto; no se aceptan `branch_id`, `home_branch_id` ni `user_id` desde estos formularios. Las transferencias y vinculaciones tendrán casos de uso propios, no edición libre de claves foráneas.

Auditoría organizativa append-only a nivel de aplicación, en la misma transacción. Una nueva migración crea `audit_logs`, dos FK restrictivas e índices por sucursal/fecha y recurso/fecha. No altera datos previos. `down()` es destructivo y no está autorizado en principal. No se afirma inmutabilidad frente a un operador SQL con privilegios de modificación.

## Inventario E1-001

Archivos nuevos:

- `app/Console/Commands/CreateInitialAdministrator.php`.
- `app/Http/Controllers/{SessionController,BranchSelectionController,DashboardController,PasswordController,PasswordRecoveryController,OrganizationController}.php`.
- `app/Http/Requests/{LoginRequest,SelectBranchRequest,ChangePasswordRequest,ResetPasswordRequest,OrganizationRequest}.php`.
- `app/Http/Middleware/{EnsureActiveUser,ProtectSensitiveResponses}.php`.
- `app/Services/{AuthenticationService,PasswordService,PasswordRecoveryService,OrganizationService}.php`.
- `app/Notifications/ResetPasswordNotification.php`, `app/Support/OrganizationResources.php`, `config/security.php`, `lang/es/validation.php`.
- `resources/views/layouts/app.blade.php`, `resources/views/auth/{login,password,forgot-password,reset-password}.blade.php`, `resources/views/branches/select.blade.php`, `resources/views/dashboard.blade.php`, `resources/views/organization/{index,form}.blade.php`.
- `database/migrations/2026_10_21_030001_create_audit_logs_table.php`.
- `tests/sqlite-bootstrap.php`, `tests/Feature/{AuthenticationTest,PasswordSecurityTest,InitialAdministratorTest,OrganizationWorkflowTest}.php`, `tests/Feature/Authorization/FreshAuthorizationTest.php`.
- `scripts/inspect_delivery_readiness.php`, `PROGRESO.md`, `DECISIONES.md`, `PRUEBAS.md`.

Archivos modificados:

- `bootstrap/app.php`, `routes/web.php`, `app/Providers/AppServiceProvider.php`, `config/permission.php`.
- `app/Models/{User,BranchUserMembership,GlobalUserRole}.php`.
- `app/Services/Authorization/{BranchContext,ScopedAuthorizationService,GlobalAuthorizationService,BranchMembershipService,RoleAssignmentService}.php`.
- `resources/views/welcome.blade.php`, `phpunit.xml`, `tests/TestCase.php`, `tests/Feature/Authorization/ScopedAuthorizationTest.php`, `scripts/inspect_mysql_testing.php`.
- `AGENTS.md`, `ROADMAP.md`, `REQUISITOS.md`, `MODELO_DATOS.md`.

También se regeneró `public/build` (artefactos ignorados). No se modificaron las diez migraciones ya aplicadas, `.env`, credenciales, cuentas MySQL ni configuración del sistema.

## ADR E1-002 — Administración de cuentas y baja coordinada (2026-10-21)

- La cuenta se vincula a un solo empleado activo; el empleado conserva su única cuenta mediante la restricción `employees.user_id UNIQUE`.
- El vínculo exige membresía vigente en la sucursal principal laboral del empleado. La asignación de membresía y el vínculo son pasos administrativos separados y auditados.
- Desactivar una cuenta revoca membresías vigentes/futuras, roles locales/globales, permisos directos heredados y sucursal principal en una transacción. Una reactivación no restaura permisos automáticamente.
- Marcar empleado como terminado desactiva la cuenta vinculada y revoca sus accesos dentro de la misma transacción de organización. Una reinstalación laboral no reactiva la cuenta.
- El último administrador general no puede desactivarse ni perder su rol; tampoco se permite que un actor se asigne roles a sí mismo.
- Toda revocación de rol requiere motivo. El motivo se conserva en auditoría.
- Las plantillas del sistema son inmutables desde la web. Roles personalizados solo pueden recibir permisos globales que el actor tenga vigentes; la asignación a otra persona comprueba esa misma capacidad de delegación. No se permiten autootorgamientos.
- No existe borrado web de roles: la retirada de un rol en uso requiere revocar asignaciones de forma explícita y auditable.
- E1-002 no modificó el esquema. En ese momento las operaciones de escritura fallaban cerradas hasta desplegar `audit_logs`; E1-003 documenta su posterior aplicación autorizada.

La verificación E1-002 se ejecutó en SQLite `:memory:`. No se repitieron pruebas MySQL porque no se dispuso de la credencial de pruebas protegida en el entorno del proceso. Durante E1-002 no se aplicó migración ni se modificaron datos de `importsystemgo`.

## ADR E1-003 — Despliegue de auditoría en principal (2026-10-21)

- El usuario autorizó preparar y verificar un respaldo cifrado y aplicar exclusivamente `2026_10_21_030001_create_audit_logs_table`.
- Se verificaron servicio MySQL2610 en ejecución, MySQL 26.10.0 y conexión efectiva a `importsystemgo` como `importsystemgo_app@localhost` inmediatamente antes de migrar. La cuenta conservó únicamente `USAGE` global y sus permisos limitados al esquema.
- Se creó un dump con bloqueos de tablas de esta base, sin conceder privilegios globales. Se comprimió y protegió con DPAPI del usuario actual de Windows; la verificación descifró y descomprimió el archivo y confirmó igualdad SHA-256. No se guardó un dump en claro en el proyecto. No se probó una restauración en otra base.
- Se aplicó solo la migración autorizada. La tabla resultante tiene nueve columnas, dos FK con `RESTRICT`, dos índices secundarios explícitos y cero filas. No se ejecutaron seeders ni se alteraron datos empresariales.
- `down()` sigue siendo destructivo y no está autorizado. La inicialización del administrador y los datos iniciales requieren autorización separada.

## ADR E1-004 — Demostración multi-sucursal con aislamiento territorial (2026-10-21)

La base activa de pruebas usa `operational_areas` y `operational_area_assignments` para segmentar el acceso de líderes regionales por ciudad. El rol `jefe-operaciones` es global pero queda restringido a la operación del área asignada; la misma lógica se refleja en `OperationalAreaAccessService` y en `GlobalAuthorizationService` al comprobar que el usuario está vinculado a la `operational_area_id` de la sucursal del contexto.

Se preparó `DemoRetailSeeder` para crear exactamente las siete sucursales comerciales, las dos áreas (La Paz y El Alto), sus almacenes principales, sus jefes de tienda, vendedores y cajeros, y los dos jefes de operaciones con sus asignaciones territoriales. El seeder no se ejecuta en producción ni contra la base principal sin aprobación previa; además se intenta evitar duplicados con `firstOrCreate` y `updateOrInsert` y sólo se emplea dentro de entornos `local` o `testing`.

La demostración no crea datos reales de ventas, clientes ni inventario; sólo usuarios, empleados, memberships y relaciones de autorización, con correos sintéticos bajo `importsystemgo.example` y contraseñas generadas por Laravel Hash en runtime. Los datos quedan claramente etiquetados como ficticios y requieren autorización antes de la inserción en la base principal.

## ADR E1-005 — Proteccion de datos de credito y controles de caja (2026-10-22)

- Los saldos e historiales de credito solo se consultan cuando el usuario tiene `credits.view`; la restriccion se aplica en el servidor y en las vistas.
- Los KPI del dashboard respetan permisos y sucursales autorizadas. El cajero ve metricas vinculadas a su turno, no indicadores operativos ajenos.
- El acceso al registro rapido de clientes se decide usando la autorizacion efectiva para la sucursal activa.
- Las operaciones de caja rechazan cajas inactivas, claves idempotentes reutilizadas con un payload distinto, referencias que contengan numeros completos de tarjeta y la autoaprobacion del cierre.
- Las referencias de pago no deben contener el numero completo de una tarjeta; se valida tanto en la entrada como en el servicio.
- Las reglas se cubren con pruebas de regresion de autorizacion, idempotencia, privacidad y alcance de dashboards. No requirieron cambios de esquema.
- La validacion original uso SQLite en memoria. En una autorizacion posterior, la migracion y la sincronizacion descritas en ADR E1-006 se aplicaron solo a DEMO.

## ADR E1-006 — Despliegue DEMO de caja y sincronizacion auditada de roles (2026-10-22)

- Se autorizo aplicar exclusivamente `2026_10_22_020001_enhance_customers_dashboards_and_cash.php` en `importsystemgo_demo`, tras inspeccion de `up()`, pruebas MySQL aisladas y respaldo SQL previo cifrado con DPAPI. La migracion no contiene borrado en `up()`; no se ejecutaron otras migraciones ni su `down()`.
- La base DEMO quedo con 41 tablas y 14 migraciones. No se consulto ni modifico la base principal y se conservaron las cuentas y datos operativos existentes.
- La auditoria revelo que el catalogo de permisos almacenado estaba desfasado respecto a `AuthorizationCatalog`. Tras solicitar y recibir autorizacion separada, se sincronizo una vez `AuthorizationSeeder` solo en DEMO.
- En la ejecucion DEMO el seeder resuelve un actor administrador DEMO activo, aplica cambios en una transaccion y registra creacion de permisos y cambios de permisos de roles en `audit_logs`. Si falta la tabla de auditoria o el actor, falla sin completar la sincronizacion. En pruebas/fresh install no-demo mantiene su comportamiento normal.
- El envoltorio protegido incorpora la accion explicita `authorization-seed`; no ejecuta `DemoRetailSeeder` ni `OperationalDemoSeeder`.
- El cajero y jefe de tienda LP01 cuentan ahora con los permisos de caja esperados. Permanecen discrepancias en `demo:verify` por sus conteos rigidos y una formula de arqueo que suma medios no efectivos; los datos no se alteraron para hacer pasar el verificador.

## ADR E1-007 — Inventario transaccional y despliegue pendiente (2026-10-23)

- El costo de adquisicion se cambia solo mediante recepcion; los formularios de producto no lo aceptan y el servicio usa lista explicita de atributos.
- No se permite eliminar productos con historia; se conserva la relacion y se cambia `is_active`.
- Los movimientos de saldo se realizan desde servicios dentro de transacciones y con bloqueos; las recepciones/transferencias/ajustes usan claves idempotentes persistentes y registran kardex y auditoria.
- Transferir no acredita saldo destino hasta confirmar recepcion. Ajustes requieren aprobador diferente del solicitante y vuelven a validar la cantidad antes de aplicar el movimiento.
- Los informes aplican alcance en servidor: sucursal para jefes de tienda, area operativa para jefes territoriales, y global solo para permisos globales sin restriccion territorial. Los jefes de tienda no pueden enumerar transferencias de otras sucursales.
- Los triggers unicamente escriben en `database_trigger_audits`; no mutan saldos. La migracion de triggers depende de la tabla de auditoria y de las tablas de inventario nuevas.
- Las nuevas rutas fallan cerradas con HTTP 503 descriptivo cuando no esta desplegado el esquema o faltan los triggers MySQL requeridos. Esta capa no sustituye la aplicacion de migraciones.
- Las migraciones quedan preparadas, no ejecutadas. No se autoriza implicita ni retroactivamente su aplicacion a DEMO o principal, ni la sincronizacion persistente de roles/permisos.
- No se inventan umbrales de autorizacion de monto/cantidad: hasta que negocio los defina, se usa la matriz de permisos por rol.
- Limitaciones deliberadas: XLS compatible con Excel y PDF mediante impresion del navegador, no formatos de archivo nativos; ajustes individuales de serial/IMEI pendientes. La ejecucion y sintaxis de triggers MySQL requiere prueba aislada antes de despliegue.

## ADR E1-008 — Dump MySQL y validacion de despliegue (2026-10-24)

- El dump usa `--result-file` con la ruta en un argumento separado y `--set-gtid-purged=OFF`; esto evita la interpretacion defectuosa de rutas de Windows y la operacion `FLUSH TABLES` que requeria un privilegio global no concedido al usuario de pruebas.
- No se incluyen rutinas ni eventos: las bases permitidas por `MysqlDumpService` fueron verificadas y no contienen esos objetos. Se conservan tablas, datos, vistas y triggers.
- El dump de DEMO fue una operacion de solo lectura con identidad root local despues de validar aparte `importsystemgo_demo@localhost`; se comprimio y protegió con DPAPI CurrentUser fuera del repositorio. SHA-256 del SQL: `892de19ae8faebad8f51af02304d5ec02acb7af241eeba8d236460be27cb3504`. El SQL temporal en claro se elimino tras verificar el round-trip.
- Las tres migraciones pendientes pasaron en MySQL aislado y el dump de prueba se restauro en una base desechable. En DEMO no se ejecuto ninguna migracion ni seeder; la sincronizacion de permisos y el despliegue permanecen pendientes de autorizacion.
- Para crear triggers en este MySQL con binary logging activo, el usuario DEMO necesita temporalmente `TRIGGER` limitado al esquema y una ventana administrativa que permita habilitar `log_bin_trust_function_creators`; ambas condiciones se verificaron solo temporalmente en `importsystemgo_testing` y se restauraron. No se otorgaron permisos ni se cambio la variable global para DEMO.
- Se descubrio que el `down()` historico de `2026_10_22_020001` puede fallar al quitar un indice requerido por una FK. No se modifico ni ejecuto contra bases persistentes; la reversa de despliegue debe basarse en restauracion del respaldo, no en ese `down()`.

## ADR E1-009 — Presentación profesional de demanda ML (2026-11-18)

- `/inteligencia/demanda` presenta el modelo activo real, sus métricas reales, el territorio activo y datos académicos DEMO; no genera resultados estáticos en JavaScript ni reemplaza el backend por reglas falsas.
- WAPE almacenado como decimal se convierte una sola vez a porcentaje para presentación. MAE se expresa en unidades, el sesgo conserva su signo y la mejora compara WAPE de modelo y línea base.
- El simulador es una consulta GET hipotética basada en pronóstico y stock visibles. Permite factor de demanda, seguridad, plazo y disponibilidad, pero no ejecuta INSERT, UPDATE ni DELETE.
- Las recomendaciones conservan su estado hasta una decisión humana autorizada. Aprobar o rechazar solo cambia la recomendación y registra auditoría; no crea una compra automática.
- Los detalles y gráficos aplican los mismos conjuntos filtrados por sucursal y permisos territoriales del controlador. No se usa `Gate::before` para ampliar el acceso.
- La interfaz se verificó autenticada con Administrador DEMO en LP01 y con 390 px/1024 px, sin desbordamiento horizontal. La sesión también puede restringirse a La Paz o El Alto mediante la lógica territorial existente.
- El modelo y las 45.360 observaciones sintéticas existentes no se reentrenaron ni resembraron durante la revisión. En LP01 se verificó el producto AUD-MIC-200 como candidato de demostración.

## ADR E1-009 — Pronóstico de demanda local, versionado y explicable (2026-11-18)

- Se mantiene el monolito Laravel y se usa un proceso Python local sin acceso directo a MySQL; Laravel entrega exclusivamente un JSON agregado sin secretos.
- El primer caso ML es demanda y reposición, no riesgo crediticio, porque tiene utilidad operativa directa y menor impacto ético.
- El modelo `HistGradientBoostingRegressor` se valida contra un promedio móvil mediante separación temporal con purga del horizonte. Un resultado que no supera la base queda como candidato.
- El historial académico se separa de ventas, caja y kardex mediante observaciones `synthetic_demo` reproducibles y declaradas como ficticias.
- Las recomendaciones nunca crean órdenes de compra; requieren autorización territorial, decisión humana y auditoría.
- La migración, el historial y los permisos quedan preparados pero no desplegados en bases persistentes hasta contar con respaldo y autorización concreta.
- Con autorización posterior se desplegó únicamente el bloque ML en DEMO. El respaldo cifrado fue verificado antes de migrar; se conservaron pendientes las tres migraciones de inventario y no se modificó la base principal.

## ADR QA CENTER — Aislamiento y ejecución verificable (2026-11-24)

Se adopta el aislamiento descrito en [`docs/adr/0002-qa-center-execution-isolation.md`](docs/adr/0002-qa-center-execution-isolation.md). En particular, el runner no usa bases DEMO/principal, la cuenta QA no se crea con contraseña fija, SMTP local no se confunde con `MAIL_MAILER=log`, y los casos sin ejecutor o precondiciones permanecen `NOT RUN` o `BLOCKED`. El arranque idempotente verifica y reutiliza Mailpit, servidor y worker, inicia solo los servicios faltantes y falla sin tocar procesos desconocidos. El login real, la ejecución UI integral, Ollama y la publicación GitHub siguen pendientes de credenciales/autorizaciones locales.

## ADR QA CENTER — Credencial efímera para exposición (2026-11-24)

- La única cuenta admitida por el comando de exposición es `qa.admin@example.test`, y debe conservar usuario activo, rol global activo y permiso administrativo activo.
- La contraseña se aporta únicamente mediante prompt oculto local, se valida por fortaleza, se almacena con el cast `hashed`, se verifica después de escribir y no se incluye en argumentos, documentación, logs ni auditoría.
- El comando se niega a operar salvo que la conexión efectiva sea SQLite y la ruta resuelta sea exactamente `database/qa.sqlite`; invalida las sesiones existentes dentro de la misma transacción.
- Este mecanismo no crea usuarios, no ejecuta migraciones o seeders y no autoriza escrituras en MySQL, DEMO o la base principal.

## ADR QA CENTER · Demostración del asistente

La vista del asistente incorpora un modo separado y rotulado como simulación, con tres preguntas y respuestas preparadas. El POST conserva el permiso `qa.ai.use`. Las respuestas se muestran en la sesión web y quedan fuera del historial de análisis y de los resultados de pruebas. El modo del proveedor sigue su flujo habitual.
