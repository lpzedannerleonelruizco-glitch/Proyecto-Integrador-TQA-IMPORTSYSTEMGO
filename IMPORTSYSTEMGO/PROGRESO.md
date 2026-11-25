# Progreso verificable — IMPORTSYSTEMGO

Actualizacion: 2026-10-22. PROMPT MAESTRO 005.

## Estado comprobado

- Ultima lectura conocida de `importsystemgo` (anterior a esta entrega): 20 tablas, 11 migraciones, 2 usuarios, 2 sucursales, 8 roles, 12 permisos, un administrador global activo y 0 empleados. No se volvio a consultar.
- La identidad principal conocida era `importsystemgo_app@localhost`; esta entrega no conecto a la base principal.
- La base principal no se inspecciono ni modifico en esta entrega. Las migraciones posteriores siguen fuera de la autorizacion principal.
- Ningun registro principal fue insertado, actualizado o eliminado durante esta entrega.
- Las suites MySQL aisladas aprobaron: 88 pruebas y 683 aserciones completas, y 21 pruebas y 69 aserciones enfocadas antes del ultimo ajuste de auditoria.
- Inspección DEMO anterior a esta migración: 38 tablas, 13 migraciones, 55 claves foráneas, 4 CHECK y 3 columnas generadas; acceso a la principal denegado.
- SQLite en memoria: 88 pruebas, 668 aserciones, cero fallos y cuatro pruebas exclusivas de MySQL omitidas.
- Compilacion Vite: aprobada, 119 modulos transformados.
- Spatie Laravel Permission 8.3.2 instalado, con compatibilidad declarada para Illuminate 13.

## Entrega A — auditoria y credenciales DEMO

- Catalogo declarativo de 41 identidades DEMO, que incluye exactamente 37 empleados ficticios.
- Comando interactivo `php artisan demo:credentials` para aprovisionar y renovar contrasenas aleatorias, fuertes y diferentes.
- El comando requiere entorno local, opcion DEMO habilitada, base efectiva `importsystemgo_demo`, cuenta `importsystemgo_demo@localhost` y confirmacion interactiva.
- Solo modifica cuentas del catalogo marcadas `is_demo`; invalida sus sesiones y registra auditoria sin secretos.
- Las contrasenas solo se muestran una vez en la terminal del usuario. No se ejecutaron ni se mostraron mediante automatizacion.
- La migracion pendiente agrega marcadores `is_demo` a las entidades preparadas. Fue validada en MySQL de pruebas, pero no en principal.

## Entrega B — organizacion ficticia preparada

- Siete sucursales: LP01–LP04 y EA01–EA03.
- Dos areas operativas territoriales, siete almacenes y 37 empleados.
- Un jefe, tres vendedores y un cajero por sucursal, mas dos jefes de operaciones.
- Cuentas especializadas para almacen, creditos y compras, ademas del administrador DEMO.
- Seeder transaccional, idempotente y bloqueado ante colisiones con registros no DEMO.
- La autorizacion territorial de jefes de operaciones fue corregida y cubierta por pruebas.

Completado: se crearon `importsystemgo_demo` e `importsystemgo_demo@localhost`, con secreto DPAPI fuera del repositorio y privilegios exclusivos. Las 13 migraciones se aplicaron solo allí. El esquema contiene 38 tablas, 55 FK, 4 CHECK y 3 columnas generadas; la cuenta DEMO no puede acceder a la principal.

Completado: el catálogo se insertó y se repitió en MySQL DEMO con conteos idénticos, demostrando idempotencia. Existen 2 áreas, 7 sucursales, 7 almacenes, 37 empleados, 41 cuentas, 52 membresías, 38 roles locales, 3 globales y 2 asignaciones territoriales. No se generaron ni mostraron todavía las contraseñas finales.

## Entrega C — frontend empresarial inicial

- Login corporativo renovado.
- Layout Blade con sidebar colapsable, navbar, identidad propia, estados, toast, confirmacion modal y comportamiento responsive.
- Dashboard por rol con datos organizativos reales y accesos sujetos a permisos.
- Las capacidades operativas usan tablas y servicios reales; no se inventan cifras ni acciones.
- Inspeccion visual real del login en `127.0.0.1:8000` completada satisfactoriamente.
- El login muestra una insignia visible `Entorno académico DEMO` únicamente cuando el servidor usa la configuración aislada.
- Se corrigió el lanzador Windows: `artisan serve` filtraba las variables MySQL personalizadas antes de crear su proceso hijo. El envoltorio usa ahora el servidor PHP local directo, que hereda el entorno DPAPI sin guardar secretos.

## Estado de modulos

- Identidad, organizacion y autorizacion: funcionales y probados.
- Productos, inventario, kardex, ventas, caja, cobros, compras, créditos y reportes comerciales: MVP funcional desplegado y probado en `importsystemgo_demo`.
- Facturacion fiscal SIN: fuera del alcance implementado; no existe conexion real.

## Siguiente tarea concreta

No debe volver a ejecutarse el comando de credenciales: las 41 contraseñas existentes permanecen vigentes. La siguiente tarea es realizar el recorrido manual de venta, cobro e inventario desde el navegador.

La base principal permanece fuera de este flujo. Las migraciones territorial y comercial no se ejecutarán allí sin una autorización distinta y respaldo previo.

## Entrega operativa preparada (2026-10-22)

Se implementó y desplegó un MVP comercial real exclusivamente en `importsystemgo_demo`:

- Catálogo de categorías, marcas y productos con SKU, código de barras y precio.
- Inventario por almacén con saldo físico, reservado, disponible y kardex.
- Venta asistida con reserva transaccional de existencias y prevención de stock negativo.
- Caja individual con apertura, cobro idempotente, despacho de inventario y cierre con importe declarado.
- Aprobación de venta a crédito, despacho controlado y calendario de cuotas.
- Creación de órdenes de compra con proveedor, producto, cantidad, costo y entrega estimada.
- Reportes por sucursal: ventas, ingresos, ticket promedio, alertas de stock, tendencia diaria, ranking y medios de pago.
- Navegación y acciones filtradas por permisos reales para todos los roles DEMO.
- Seeder operativo idempotente con 12 productos ficticios, 84 saldos iniciales, clientes, proveedores, cajas y operaciones coherentes.

La migración `2026_10_22_010001_create_commercial_core_tables` agregó 16 tablas. El entorno DEMO contiene 38 tablas, 13 migraciones, 55 claves foráneas y 25 permisos. El seeder fue ejecutado dos veces con conteos idénticos. La base principal continúa intacta.

### Publicación DEMO verificada

- Respaldo previo completo y legible almacenado fuera del repositorio en `%LOCALAPPDATA%\IMPORTSYSTEMGO\backups`.
- 12 productos, 84 saldos, 21 clientes, 7 cajas, 15 ventas, 7 pagos, 1 crédito con 6 cuotas y 1 orden de compra.
- Inventario no negativo y conciliación aprobada de ventas, pagos, cajas, cuotas y compras.
- Servidor local activo en `http://127.0.0.1:8000`; respuesta HTTP 200, etiqueta DEMO y formulario de acceso presentes.
- Las 41 contraseñas existentes no fueron renovadas ni modificadas.

## Validacion complementaria de clientes, caja y dashboard (2026-10-22)

- Se limitaron los saldos e historiales de credito a usuarios autorizados con `credits.view`; el control aplica en consultas y vistas.
- Los KPI y reportes del dashboard se limitan por permisos y sucursales autorizadas. El perfil de caja recibe datos de su turno, no rankings operativos ajenos.
- El registro rapido de clientes se presenta segun la autorizacion efectiva de la sucursal activa.
- Caja rechaza operaciones sobre cajas inactivas, reuso de claves idempotentes con datos diferentes, referencias con numeros completos de tarjeta y autoaprobacion del cierre.
- PHPUnit completo con SQLite `:memory:`: 87 pruebas, 663 aserciones, 0 fallos y 4 omitidas por requerir MySQL.
- Laravel Pint y Vite aprobados; la compilacion transformo 119 modulos.
- Se inspecciono `/login` sin autenticar en navegador con anchos CSS de 1365 px y 390 px. No se observo desbordamiento horizontal; la vista movil oculta el panel promocional. Las pantallas autenticadas no se inspeccionaron visualmente.
- Durante esta validacion no se ejecutaron migraciones ni seeders, no se modificaron contrasenas, y no se escribio en `importsystemgo` ni `importsystemgo_demo`.

La Entrega 1 no se declara completa. El recorrido visual autenticado y el flujo manual de venta, cobro e inventario siguen pendientes.

## Migracion de clientes, caja y dashboards en DEMO (2026-10-22)

- Se inspecciono completa `2026_10_22_020001_enhance_customers_dashboards_and_cash.php`. Su `up()` agrega columnas e indices, recalcula saldos esperados y crea `cash_movements`, `cash_count_details` y `sales_goals`; no contiene eliminacion de tablas ni borrado de registros. Su `down()` elimina estructuras y no fue ejecutado.
- Antes de migrar se verifico `importsystemgo_demo` como `importsystemgo_demo@localhost`, 13 migraciones aplicadas y una sola pendiente: `2026_10_22_020001_enhance_customers_dashboards_and_cash`.
- La migracion se ejecuto una sola vez mediante `scripts/with_demo_environment.ps1 -Action migrate`, con respaldo previo. `importsystemgo` no fue consultada ni modificada.
- Respaldo previo: `%LOCALAPPDATA%\IMPORTSYSTEMGO\backups\cash-migration-20261022-153158\importsystemgo_demo.sql.gz.dpapi`, comprimido y cifrado con DPAPI del usuario Windows actual. El contenido se descifro y descomprimio para verificar SHA-256 `305528eb99c730e2757a30becd90284a7c7f6e56d2e23e52a51a681e96830e89`; el SQL temporal fue eliminado.
- Resultado: DEMO tiene 41 tablas, 14 migraciones aplicadas, 65 claves foraneas y las tres tablas nuevas. Los conteos de usuarios, sucursales, empleados, clientes, cajas, sesiones, pagos e inventario permanecieron en 41, 7, 37, 21, 7, 7, 7 y 84, respectivamente. No se ejecutaron seeders operativos ni se cambiaron contrasenas; la sincronizacion de autorizacion se hizo despues de solicitar y recibir permiso adicional.
- La suite final MySQL aislada aprobo con 88 pruebas y 683 aserciones; SQLite en memoria aprobo con 88 pruebas, 668 aserciones y 4 omitidas por requerir MySQL. Tambien aprobaron Pint y Vite.
- El flujo HTTP autenticado de caja devuelve 200 en `CommercialWorkflowTest`, que comprueba el acceso del cajero a `cash.index` y carga la relacion `movements`. En el navegador, la visita no autenticada a `/caja` redirigio al login DEMO; no se introdujeron credenciales.
- La reconciliacion post-migracion correcta de efectivo (fondo inicial + pagos en efectivo + movimientos netos) tiene cero discrepancias.
- `demo:verify` reporta discrepancias previas que no se corrigieron: el catalogo de permisos en BD tiene 25/28 permisos, hay 16 ventas DEMO frente a las 15 esperadas por el verificador, y 108 movimientos de inventario frente a 107. Su chequeo de caja suma pagos no efectivos como si fueran efectivo, por lo que produce un falso positivo aunque la reconciliacion de efectivo correcta tiene cero discrepancias.
- Tras solicitar autorizacion adicional, se ejecuto una sola vez `AuthorizationSeeder` exclusivamente en DEMO. Se agregaron los tres permisos faltantes y se sincronizaron las plantillas de roles; las creaciones y cambios se auditaron en la misma transaccion mediante el actor administrador DEMO. Se agregaron 3 permisos y quedaron registrados 3 eventos de creacion de permiso y 5 sincronizaciones de rol.
- Verificacion posterior de roles: cajero LP01 tiene `cash.view`, `cash.manage` y `payments.collect`; jefe de tienda LP01 tiene `cash.view` y `cash.approve`. No se cambiaron usuarios, contrasenas, sucursales, empleados ni datos operativos.
- `demo:verify` ahora confirma el catalogo de permisos, roles, usuarios, relaciones y aislamiento territorial. Aun reporta tres discrepancias: conteos rigidos para 16 ventas (espera 15) y 108 movimientos de inventario (espera 107), y su formula de caja suma pagos no efectivos. La reconciliacion correcta de efectivo sigue en cero discrepancias; no se modificaron esos datos ni el verificador.

## Modulo de inventario ampliado (2026-10-23)

- Preparados CRUD de productos, categorias y marcas, busqueda/filtros, precios, imagen privada, detalle de saldos y kardex; los productos vinculados se desactivan, no se borran.
- El servicio de catalogo limita atributos editables, mantiene el costo fuera del formulario y audita el producto en la misma transaccion.
- Recepciones transaccionales con bloqueos, clave idempotente, costo promedio, kardex, auditoria y validacion de series/IMEI.
- Transferencias con aprobacion, despacho, recepcion parcial y movimientos solo en cada etapa confirmada. Ajustes requieren aprobador distinto y validan saldo concurrente.
- Reportes de existencias, disponibilidad, costo, bajo minimo, agotados, ventas, kardex, transferencias y ajustes con exportacion CSV, Excel compatible y vista imprimible para PDF.
- Agregada una guarda de esquema: las rutas nuevas devuelven 503 explicativo mientras falten migraciones o triggers MySQL requeridos; no se ejecuta ninguna migracion de forma automatica.
- Se aplican filtros de sucursal en inventario, reportes y movimientos. Los jefes de operaciones quedan restringidos a su area; un jefe de tienda solo ve transferencias que involucran su sucursal.
- Preparadas migraciones versionadas: `2026_10_22_030001_create_backup_and_database_audit_tables`, `2026_10_23_010001_expand_inventory_catalog_and_operations` y `2026_10_23_020001_add_inventory_database_triggers`. No fueron ejecutadas en `importsystemgo` ni `importsystemgo_demo`.
- No se ejecuto `AuthorizationSeeder`; las nuevas capacidades no apareceran en bases persistentes hasta una sincronizacion autorizada.
- No se insertaron datos, seeders, productos, ventas ni movimientos en bases persistentes. No se alteraron las 41 credenciales DEMO.
- Pendiente autorizacion separada para migrar DEMO y sincronizar permisos. La prueba MySQL no inicio: el bootstrap de `phpunit.mysql.xml` rechazo la ejecucion porque la credencial protegida no estaba disponible en el proceso. XLS es compatible con Excel, no XLSX nativo; PDF se genera mediante impresion del navegador. Los ajustes de stock serializado siguen bloqueados hasta contar con flujo de ajuste por numero de serie. Los umbrales de aprobacion aun requieren definicion empresarial; actualmente se aplica la autorizacion por rol.
- Validacion final en SQLite `:memory:`: 98 pruebas, 718 aserciones, 0 fallos y 4 omitidas por requerir MySQL. Pint y Vite aprobados; el build transformo 119 modulos.

## Validacion MySQL, respaldos y preflight DEMO (2026-10-24)

- La suite completa MySQL aislada paso con 101 pruebas y 756 aserciones. SQLite en memoria paso con 101 pruebas, 721 aserciones y 6 omitidas por requerir MySQL. Pint y Vite tambien aprobaron; Vite transformo 119 modulos.
- Las migraciones y triggers se probaron unicamente en `importsystemgo_testing`. Para las pruebas se habilito temporalmente `log_bin_trust_function_creators` y `TRIGGER` solo en ese esquema; al terminar, el valor global volvio a `0` y el grant temporal se revoco. La cuenta de pruebas conserva solo sus grants permanentes.
- El flujo ZIP completo paso en MySQL: 1 prueba y 9 aserciones, incluyendo integridad SHA-256 y trigger en el SQL. El dump se restaura en un esquema desechable: 52 tablas, 92 claves foraneas, 16 triggers, 17 migraciones, 41 usuarios, 7 sucursales y 12 productos. El esquema temporal, el ZIP de prueba y el SQL temporal se eliminaron.
- `MysqlDumpService` ahora pasa `--result-file` y la ruta como argumentos separados y usa `--set-gtid-purged=OFF`. Se omiten rutinas/eventos: la verificacion de ambos esquemas permitidos confirmo cero rutinas y cero eventos; no se requieren privilegios de exportacion adicionales.
- Preflight de solo lectura DEMO: `importsystemgo_demo@localhost`, 41 tablas, 14 migraciones, 65 FK, 4 CHECK, 3 columnas generadas, 41 usuarios, 7 sucursales, 37 empleados, 8 roles y 28 permisos. Las tres migraciones de Prompt Maestro 006 siguen pendientes.
- Datos DEMO compatibles observados: 12 productos, 5 categorias, 4 marcas, 7 almacenes, 84 saldos, 108 movimientos, 21 clientes, 16 ventas y 7 pagos. No hay grupos de SKU duplicados, saldos fisicos/reservados invalidos ni productos serializados existentes.
- El catalogo actual declara 44 permisos; DEMO conserva 28 y le faltan 16 de inventario, reportes y backups. No se ejecuto `AuthorizationSeeder`. La prueba MySQL `AuthorizationSeederAuditTest` confirma transaccion, auditoria e idempotencia: una segunda ejecucion no agrega auditorias.
- Respaldo previo DEMO creado fuera del repositorio, comprimido y cifrado con DPAPI CurrentUser: `%LOCALAPPDATA%\IMPORTSYSTEMGO\backups\inventory-prompt-006-preflight-20261024-233750\importsystemgo_demo-20261024-233750.sql.gz.dpapi`. SHA-256 del SQL: `892de19ae8faebad8f51af02304d5ec02acb7af241eeba8d236460be27cb3504`; la prueba de descifrado/descompresion reprodujo el mismo hash. No queda SQL en claro.
- No se ejecutaron migraciones ni seeders en DEMO, no se modificaron datos ni credenciales y la base principal no se consulto. El usuario DEMO no tiene el grant `TRIGGER`; crear los triggers requerira una ventana de despliegue autorizada con privilegios temporales y controlados.
- Una prueba exploratoria con `DatabaseMigrations` genero y restauro datos, pero su teardown automatico encontro un fallo preexistente al revertir `2026_10_22_020001`: MySQL no permite quitar `payments_session_method_date_index` mientras lo necesita una FK. El archivo historico no se modifico; la suite final usa `RefreshDatabase`, paso completa y no se ejecuto rollback en DEMO ni principal.

La aplicacion a DEMO y la sincronizacion de permisos siguen pendientes de autorizacion expresa. No se declara completa la Entrega 1; la verificacion visual autenticada y el recorrido operativo en navegador esperan el despliegue aprobado.

## Presentacion profesional de machine learning (2026-11-18)

- Se redisenó la vista `/inteligencia/demanda` con encabezado ejecutivo, insignia DEMO, sucursal activa, estado ACTIVE y fecha real de entrenamiento.
- Las cards muestran WAPE del modelo 6.85%, WAPE base 8.75%, mejora 21.6%, MAE 1.53, sesgo -0.60, productos evaluados y reposición sugerida.
- Se agregaron gráficos reales para pronóstico frente a disponibilidad, distribución de riesgo, modelo frente a línea base, riesgo crítico por sucursal y mayor necesidad de reposición.
- Se agregaron el flujo pedagogico “Datos históricos -> Preparación -> Entrenamiento -> Validación -> Pronóstico -> Recomendación -> Decisión humana”, ayuda de métricas y explicación Champion/Candidate.
- La tabla añade stock físico, reservado, disponible, intervalo, sugerencia, riesgo, estado, detalle modal y orden ascendente/descendente por reposición sugerida.
- El simulador admite variación de demanda, stock de seguridad, plazo y stock disponible. Realiza cálculo GET hipotético y muestra permanentemente que no modifica inventario, compras ni pronósticos.
- El menú lateral presenta ahora el nombre exacto “Inteligencia de demanda”.
- Validación visual autenticada con Administrador DEMO en LP01: encabezado, 7 canvases, tabla y modal cargaron correctamente; el viewport de 1024 px no presentó desbordamiento y 390 px presentó scrollWidth 375 frente a innerWidth 390.
- En 390 px se comprobó que el título, badges, tarjetas y métrica principal permanecen legibles sin desbordamiento horizontal.
- Simulación comprobada: demanda ajustada 40.96, disponible 20.00, stock proyectado 0.89 y reposición sugerida 5. No creó compra ni modificó datos.
- Datos DEMO verificados después de la inspección: 1 modelo activo, 84 pronósticos, 84 recomendaciones, 45.360 observaciones sintéticas, 0 aprobaciones y 0 rechazos. El modelo activo sigue como HistGradientBoostingRegressor con WAPE 6.8535%.
- Producto recomendado para presentar LP01: SKU AUD-MIC-200, “Micrófono inalámbrico dual”, pronóstico 32.28 unidades, intervalo 28.91–35.66 y reposición pendiente 26 unidades, riesgo crítico.
- No se volvió a entrenar el modelo ni a generar las observaciones. No se aprobaron ni rechazaron recomendaciones durante la inspección.

## Reanudacion y control de calidad (2026-11-18)

- Se releyo el estado consolidado antes de continuar. El modulo profesional de clientes expone 7 rutas y el modulo empresarial de caja expone 8 rutas, todas dentro de autenticacion y contexto de sucursal.
- La suite completa SQLite en memoria aprobo con 101 pruebas, 721 aserciones, cero fallos y 6 omisiones exclusivas de MySQL.
- Laravel Pint aprobo sin cambios pendientes. Vite compilo 119 modulos con Chart.js incluido y cero vulnerabilidades reportadas por la instalacion npm anterior.
- Laravel sigue en 13.38.0 sobre PHP 8.4.25, zona `America/La_Paz` e idioma `es`.
- La busqueda de secretos versionables no encontro contrasenas asignadas ni valores de `DB_PASSWORD`. No se rotaron las 41 credenciales DEMO.
- La validacion MySQL aislada historica mas reciente permanece aprobada con 101 pruebas y 756 aserciones (2026-10-24). El reintento del 2026-11-18 no llego a ejecutar pruebas: `127.0.0.1:3306` rechazo la conexion y no aparecio un servicio `MySQL2610`; no se inicio ni reinicio ningun servicio.
- No se consulto ni modifico `importsystemgo` o `importsystemgo_demo` durante esta reanudacion. Tampoco se ejecutaron migraciones, seeders ni comandos de credenciales.

### Proxima operacion sensible pendiente

El despliegue de inventario ampliado en DEMO sigue requiriendo autorizacion concreta. Cuando MySQL vuelva a estar disponible, el plan es: verificar identidad y pendientes; crear y verificar un respaldo DEMO nuevo cifrado fuera del repositorio; aplicar solo las tres migraciones de inventario ya validadas; conceder y revocar `TRIGGER` exclusivamente en DEMO y restaurar `log_bin_trust_function_creators`; sincronizar los 16 permisos faltantes con auditoria; y ejecutar verificaciones y pruebas sin tocar la base principal. No se ejecutara este plan con una autorizacion generica.

## IMPORTSYSTEMGO Intelligence preparado (2026-11-18)

- Implementado pronóstico de demanda a 30 días con `HistGradientBoostingRegressor`, línea base, WAPE, MAE, sesgo, intervalos y bloqueo de activación cuando no supera la base.
- Python 3.13 y entorno virtual `.venv-ml` instalados; scikit-learn 1.9.1 y dependencias fijadas fuera del repositorio.
- Desplegada una migración con cuatro tablas ML exclusivamente en DEMO; principal e inventario pendiente permanecen sin cambios.
- Historial académico reproducible de 45.360 observaciones separadas de ventas/caja/kardex insertado exclusivamente en DEMO.
- Agregados dashboard, gráfico, filtros, aprobación/rechazo humano, auditoría y aislamiento territorial.
- Pruebas nuevas: 6 casos Laravel y 26 aserciones; prueba Python real aprobada. Suite completa SQLite: 107 pruebas, 747 aserciones, 6 omitidas y 0 fallos. Pint y Vite aprobados; Vite transformó 119 módulos.
- Respaldo previo verificado: `%LOCALAPPDATA%\IMPORTSYSTEMGO\backups\ml-preflight-20261118-144520\importsystemgo_demo.sql.gz.dpapi`, SHA-256 `8bed2908f9da20430b7bdde9a64e1b180ee4f7e60e8ec184e9d9aaf26d4832cc`.
- DEMO quedó con 45 tablas, 15 migraciones, 76 FK y 47 permisos. Existen 84 pronósticos y 84 recomendaciones para 7 sucursales y 12 productos.
- Modelo activo: WAPE 6,8535%, línea base 8,7469%, MAE 1,5285, sesgo -0,5998 y 8.148 filas de validación.
- Verificación visual autenticada aprobada en `/inteligencia/demanda`; las tres migraciones de inventario siguen pendientes.

### Mejora profesional del módulo ML (2026-11-18)

- La ausencia de sucursal activa redirige a la selección segura y conserva la URL ML mediante intención de sesión. Sin membresías vigentes se mantiene el 403.
- El dashboard ML incorpora KPIs calculados, comparación modelo/base, distribución de riesgo, filtros territoriales y por búsqueda/categoría/riesgo/estado, historial de versiones y simulación estrictamente de solo lectura.
- Las decisiones permiten aprobar o rechazar únicamente sobre recomendaciones autorizadas y activas; se conserva la auditoría y la prohibición de crear compras automáticamente.
- No se modificó el esquema, no se ejecutaron migraciones ni seeders y no se escribieron datos en las bases principal o DEMO.

### Auditoría inicial de IMPORTSYSTEMGO QA CENTER (2026-11-24)

- Se verificó una línea base de 110 pruebas SQLite, 763 aserciones y cero fallos, con 6 omisiones exclusivas de MySQL.
- Laravel Pint aprobó y Vite transformó 119 módulos sin fallos.
- El script de preparación confirmó que la base principal mantiene 20 tablas, 11 migraciones y 7 migraciones pendientes.
- No se inició MySQL, no se consultó ni modificó DEMO y no se consultó ni modificó la base principal.
- La auditoría identificó que el QA Center todavía no existe como módulo o conjunto integrado; las brechas y fases están registradas en `docs/QA_CENTER_AUDITORIA_2026-11-24.md`.

### Cierre técnico QA CENTER en curso (2026-11-24)

- Se completó la primera corrección del runner: ahora ejecuta PHPUnit, Selenium/Edge, Robot Framework y Locust según una tabla explícita por caso; los casos no implementados o bloqueados no se convierten en PASS.
- El catálogo queda clasificado en 17 `IMPLEMENTED`, 2 `BLOCKED` y 4 `NOT RUN`. La validación FAIL CONTROLADO usa `TC-SAN-003`, comprueba `validity.typeMismatch` real en Edge y etiqueta el fallo como demostración.
- El job exige que el worker use `database/qa.sqlite`; el runner PHPUnit usa SQLite `:memory:`. El servidor y worker local se inician por separado sin migrar, crear administrador ni cambiar credenciales.
- Se implementó el generador PDF/HTML desde el mismo JSON con datos, validaciones, pasos, resultados y capturas disponibles. Las pruebas Python comprueban con fixtures temporales la salida PDF, campos del HTML, imagen y clasificación; no son informes finales de una ejecución web.
- Mailpit oficial v1.34.0 está instalado en almacenamiento privado con SHA-256 del paquete verificado. En esta sesión se inició en `:1025` (SMTP) y `:8025` (UI); SMTP saludó `220`, UI respondió 200 y la bandeja está vacía (0 mensajes).
- `iniciar_qa.ps1` ahora asegura Mailpit automáticamente —invoca el instalador oficial fijado solo si falta el ejecutable— y reutiliza servidor/worker/Mailpit verificados. `iniciar_mailpit.ps1` valida que ambos listeners pertenezcan a la misma instancia esperada y comprueba SMTP/UI. Se volvió a ejecutar el arranque: conservó los PID existentes y no duplicó procesos.
- El correo QA E2E no se ha enviado ni recibido; falta una ejecución autenticada desde la interfaz y validar destinatario, asunto, cuerpo y ambos adjuntos.
- Se añadió `detener_qa_seguro.ps1 -WorkerOnly`: antes de parar el worker consulta en modo de solo lectura que la cola QA esté vacía, dejando servidor y Mailpit activos. La cola se observó vacía; no se detuvo el worker porque el prompt de contraseña debe completarse en una terminal local interactiva.
- Se corrigió el límite de Locust con reserva concurrente y espera de escritura CSV. El CSV histórico `qa_engine/performance/locust-short_stats.csv` contiene 14 solicitudes agregadas; los perfiles automatizados aprobaron los totales 14, 30, 60 y 20, sin fallos.
- Robot Framework confirmó la redirección real de `/dashboard` a `/login`; se corrigió el orden de opciones `--test` para que Robot ejecute la suite filtrada.
- La inspección del formulario en el navegador real de `http://127.0.0.1:8001/login` confirmó `POST /login`, campo oculto CSRF, correo `type=email`, autocomplete `username` y validez HTML5 del correo QA literal. El correo aparece como texto, no como enlace. No se envió el formulario sin la contraseña local.
- En la última comprobación, `:8001` (`/up`, `/login`), Mailpit (`:8025`) y el worker independiente estaban activos. El puerto `:8000` pertenece a otro servidor del workspace y no se usa como URL QA.
- El error POST compartido corresponde a `:8000`: el middleware `throttle` usa la caché configurada como base de datos y el proceso HTTP hijo de `artisan serve` no cargó el driver SQLite, antes de ejecutar `SessionController`. El script QA evita esa ruta al cargar SQLite explícitamente y forzar caché/sesión en archivos en `:8001`; no se deshabilitó CSRF ni throttling.
- La cuenta QA no se crea automáticamente con contraseña fija. El usuario eligió reiniciar con prompt oculto local para aportar la contraseña de E2E; se espera confirmación de ejecución local antes de declarar login/capturas autenticadas.
- El repositorio público `DannerCM/Proyecto-Integrador-TQA` existe y su URL fue confirmada mediante API de lectura. El commit local `803420c` existe, Git tiene nombre/correo configurados, la raíz es `C:\PROYECTOS\IMPORTSYSTEMGO` y no hay remoto configurado. `gh` no tiene sesión autenticada; no se publicó nada.
- Se sincronizó una lista permitida de fuentes QA a `ENTREGA_PROYECTO_INTEGRADOR_TQA\Codigo_Fuente_QA`, incluidos controlador, Mailable, vistas, configuración, dependencias, pruebas, motor y scripts. El chequeo de nombres no encontró `.env`, bases SQLite, claves ni dependencias locales copiadas.
- El ZIP existente está desactualizado y no se regeneró como entrega final: faltan capturas autenticadas y resultados web, IA y correo E2E; no se debe confundir con el paquete definitivo.
- Ollama no está instalado/configurado y no se declaró respuesta de IA.
- No se modificaron ni ejecutaron migraciones, seeders ni operaciones sobre bases empresariales o DEMO.

La entrega no está cerrada. Permanecen pendientes el flujo autenticado de Selenium desde la web, la persistencia del PASS/FAIL controlado desde la interfaz, el correo de ejecución verificado, la IA, la autorización GitHub, la revisión visual completa de PDF y la regeneración del paquete ZIP.

### Preparación académica QA CENTER (2026-11-24)

- Se añadió un cambio local y auditable de la contraseña exclusiva `qa.admin@example.test`: el comando solo acepta `database/qa.sqlite`, exige secreto fuerte por entrada oculta, invalida sesiones y nunca registra la contraseña.
- Se prepararon el iniciador de exposición y el abridor ordenado de pestañas, sin migraciones, seeders ni acceso a MySQL. La credencial sigue pendiente de ingreso interactivo por el usuario.
- Se ejecutaron respaldos reales: funcional con 2 PASS, FAIL CONTROLADO en Edge con evidencia y carga Locust de 14 solicitudes, 0 fallos, promedio 447 ms y p95 520 ms.
- Se generaron guiones de 6 y 5 minutos, checklist, orden de pestañas, preguntas y respuestas, estado real y reportes PDF/HTML. El PDF principal quedó en dos páginas y fue revisado visualmente completo en Edge, sin recortes ni solapamientos.
- La regresión final mediante `phpunit.xml` aprobó 120 pruebas y 800 aserciones, con 6 omisiones exclusivas de MySQL. `QaCenterWorkflowTest` aprobó 10 pruebas y 37 aserciones; Pint aprobó.
- Mailpit continúa disponible pero con 0 mensajes; el flujo autenticado, envío desde interfaz y Ollama continúan pendientes y se documentan sin simular resultados.

### Corrección de ejecuciones web atascadas (2026-11-24)

- Las ejecuciones QA 1 y 2 quedaron en `RUNNING` al 0 % porque Symfony Process no dispone de `inheritEnvironmentVariables()` en la versión instalada. El worker ahora excluye variables heredadas con la API vigente y registra `FAILED` cuando falla un job pendiente o en curso.
- La ejecución 3, creada con el servicio de ejecución y procesada por un worker nuevo en una cola QA de verificación, terminó en `COMPLETED` con `TC-SMK-003` en `PASS`. Mailpit recibió el PDF y el HTML.
- Las dos ejecuciones históricas conservan su estado original. La prueba desde el botón requiere recargar el worker de la cola predeterminada con la credencial local si se usa Selenium autenticado.

### Recuperación del worker QA activo (2026-11-24)

- Se reemplazó el worker obsoleto con la cola vacía y se reutilizaron el servidor y Mailpit. Se conciliaron cinco ejecuciones históricas como `FAILED` tras comprobar el error exacto y su ID en `failed_jobs`.
- La ejecución 7 pasó por la cola predeterminada y terminó al 100 %. `TC-SMK-003` y `TC-SMK-004` pasaron. Los dos casos de acceso quedaron `BLOCKED` porque el worker renovado requiere ingresar la credencial local por prompt oculto. Mailpit recibió el HTML y PDF de la ejecución.

- El asistente QA muestra en formato de conversación tres ejemplos para la exposición: inicio de sesión, correo inválido y stock insuficiente. La sección indica que el modo local usa respuestas preparadas.

### Publicación de la entrega académica QA

- Se creó el [repositorio público de IMPORTSYSTEMGO QA Center](https://github.com/lpzedannerleonelruizco-glitch/Proyecto-Integrador-TQA-IMPORTSYSTEMGO) con autorización del usuario. GitHub confirmó `main` en `0374a2b81e4eb0009116988322b5641717e700a3`.
- La publicación contiene el módulo académico, documentación y reportes revisados. La copia de trabajo `tmp/github-importsystemgo-qa` sigue `origin/main` para próximas publicaciones.
