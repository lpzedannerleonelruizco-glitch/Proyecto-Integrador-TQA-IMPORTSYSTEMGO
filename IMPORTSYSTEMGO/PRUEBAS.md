# Evidencia de pruebas — IMPORTSYSTEMGO

Fecha de actualizacion: 2026-10-22.

## Revalidacion del 2026-11-18

| Comprobacion | Resultado |
|---|---|
| PHPUnit SQLite `:memory:` | 101 pruebas, 721 aserciones, 0 fallos, 6 omitidas por requerir MySQL |
| PHPUnit MySQL aislado, ultima ejecucion completa | 101 pruebas, 756 aserciones, 0 fallos (2026-10-24) |
| Reintento MySQL actual | No iniciado: conexion rechazada en `127.0.0.1:3306`; no se inicio ni reinicio MySQL |
| Laravel Pint | Aprobado |
| Vite | Aprobado; 119 modulos transformados |
| Rutas de clientes | 7 rutas registradas |
| Rutas de caja | 8 rutas registradas |
| Laravel / PHP / Composer | 13.38.0 / 8.4.25 / 2.10.3 |
| Escrituras persistentes | Ninguna en principal o DEMO; sin migraciones, seeders ni rotacion de credenciales |

La imposibilidad actual de conectar a MySQL no invalida la evidencia aislada aprobada el 2026-10-24, pero debe repetirse el preflight y la suite MySQL antes de cualquier despliegue nuevo en DEMO.

## Resultados actuales

| Comprobacion | Resultado |
|---|---|
| Ultima inspeccion principal anterior a esta entrega | 20 tablas, 11 migraciones, 2 usuarios, 2 sucursales, 8 roles, 12 permisos, 1 administrador global activo, 0 empleados |
| Principal | No se consulto ni modifico en esta entrega |
| PHPUnit SQLite `:memory:` | 88 pruebas, 668 aserciones, 0 fallos, 4 omitidas por requerir MySQL (validacion final) |
| PHPUnit MySQL aislado | 88 pruebas, 683 aserciones, 0 fallos (validacion final) |
| Inspeccion de MySQL aislado previa | 38 tablas, 13 migraciones, 55 FK, 4 CHECK, 3 columnas generadas; evidencia anterior |
| Entorno `importsystemgo_demo` despues de la migracion autorizada | 41 tablas, 14 migraciones, 65 FK, 4 CHECK y 3 columnas generadas; identidad y aislamiento aprobados |
| Vista previa DEMO | 2 areas, 7 sucursales, 7 almacenes, 37 empleados y 41 cuentas; cero escrituras |
| Insercion DEMO real | Conteos y relaciones completos; segunda ejecucion con conteos identicos |
| Seeder DEMO enfocado | 4 pruebas, 324 aserciones, 0 fallos |
| Laravel Pint | Aprobado despues de aplicar formato al archivo de pruebas |
| Vite | Aprobado; 119 modulos transformados |
| HTTP local | `/login` responde; `/caja` sin autenticar redirige al login DEMO |
| Inspeccion visual | `/login` verificado en 1365 px y 390 px CSS; sin desbordamiento horizontal. No se autentico ni se inspeccionaron vistas privadas |

## Validacion complementaria — clientes, caja y dashboards

| Comprobacion | Resultado |
|---|---|
| Suite completa PHPUnit, SQLite en memoria | 87 pruebas, 663 aserciones, 0 fallos, 4 omitidas por requerir MySQL |
| Laravel Pint | Aprobado; el formato pendiente de `CustomerCashDashboardTest.php` fue corregido |
| Vite | Aprobado; 119 modulos transformados |
| Navegador, escritorio | `/login`, viewport CSS 1365 × 900; composicion de dos paneles y sin overflow horizontal |
| Navegador, movil | `/login`, viewport CSS 390 × 843; panel promocional oculto, formulario visible y sin overflow horizontal |
| Datos en la validacion complementaria anterior | No se ejecutaron migraciones, seeders, comandos de credenciales ni operaciones de escritura en MySQL |

## Migracion y sincronizacion de autorizacion en DEMO

| Comprobacion | Resultado |
|---|---|
| Identidad antes/despues | `importsystemgo_demo` / `importsystemgo_demo@localhost`; privilegio global solo `USAGE`; acceso a la principal denegado |
| Pendientes antes de migrar | Una: `2026_10_22_020001_enhance_customers_dashboards_and_cash` |
| Inspeccion de `up()` | Sin `DROP`, truncado ni borrado de registros; agrega campos/indices, recalcula importes esperados y crea tres tablas |
| Respaldo SQL previo | Creado fuera del repositorio, gzip + DPAPI CurrentUser; SHA-256 verificado; SQL temporal eliminado |
| Aplicacion | Una migracion autorizada aplicada mediante `with_demo_environment.ps1 -Action migrate` |
| Esquema posterior | 41 tablas, 14 migraciones, 65 FK; `cash_movements`, `cash_count_details` y `sales_goals` presentes |
| Conteos conservados | 41 usuarios, 7 sucursales, 37 empleados, 21 clientes, 7 cajas, 7 sesiones, 7 pagos y 84 balances |
| PHPUnit SQLite completo posterior | 88 pruebas, 668 aserciones, 0 fallos, 4 omitidas por requerir MySQL |
| PHPUnit MySQL completo final | 88 pruebas, 683 aserciones, 0 fallos |
| Pint / Vite | Aprobados; Vite transformo 119 modulos |
| Caja HTTP autenticada | `CommercialWorkflowTest` comprueba `cash.index` con usuario cajero y respuesta 200; el navegador sin autenticar fue redirigido al login |
| Caja: servicio | Suite enfocada cubre cobro mixto/idempotente, movimiento, arqueo, cierre y aprobacion en la base aislada |
| Autorizacion posterior | `AuthorizationSeeder` ejecutado una vez tras aprobacion; 28 permisos, cajero y jefe verificados, 3 permisos nuevos y 8 plantillas sincronizadas |
| Auditoria del seeder | 3 permisos creados y 5 cambios de plantillas registrados en `audit_logs`; prueba dedicada de transaccion/idempotencia aprobada |

### Incidencias de datos y permisos encontradas sin corregir

El comando de solo lectura `demo:verify` sigue reportando tres discrepancias: hay 16 ventas DEMO frente a las 15 codificadas como esperado y 108 movimientos de inventario frente a 107; además el verificador suma todos los medios de pago en el efectivo esperado. Los datos de ventas y movimientos no fueron modificados.

La discrepancia de permisos fue resuelta con autorizacion expresa y una sola ejecucion de `AuthorizationSeeder` en DEMO. El cajero de LP01 conserva `cash.view`, `cash.manage` y `payments.collect`; el jefe de tienda de LP01 tiene `cash.view` y `cash.approve`. La sincronizacion actualizo las plantillas segun `AuthorizationCatalog` y registro sus cambios en auditoria.

El efectivo esperado se reconcilia correctamente como fondo inicial + pagos en efectivo + movimientos netos; esta consulta tiene cero discrepancias. El verificador existente suma pagos de todos los medios y por eso da un falso positivo. No se modifico dicho comando en esta entrega.

Las pruebas cubren la proteccion de saldos/historiales de credito, autorizacion del registro rapido, aislamiento de KPI y dashboards, operaciones de caja inactiva, payload distinto con clave idempotente repetida, referencias de pago seguras y aprobacion del cierre. No se utilizaron credenciales para la inspeccion visual.

La suite completa MySQL se ejecuto al final sobre `importsystemgo_testing`; los tests usan `RefreshDatabase` solo en esa cuenta. La unica migracion real fue la autorizada en DEMO; ninguna prueba ni migracion se ejecuto contra `importsystemgo`.

## Cobertura DEMO agregada

- Catalogo exacto de siete sucursales y dos areas territoriales.
- 41 cuentas marcadas DEMO y exactamente 37 empleados ficticios.
- Idempotencia del seeder y bloqueo de colisiones con datos no DEMO.
- Rotacion de 41 contrasenas fuertes y diferentes, verificadas contra sus hashes; la cuenta real conserva su hash.
- Sesiones DEMO invalidadas al rotar credenciales y auditoria sin secretos.
- El comando no interactivo se rechaza antes de escribir.
- Jefes de operaciones limitados a las sucursales asignadas a su area.
- Roles locales distintos por sucursal y comprobaciones actuales de membresia.
- Los ocho perfiles funcionales representativos alcanzan su dashboard esperado.

## Seguridad de las suites

`Tests\TestCase` verifica la identidad efectiva antes de cualquier `RefreshDatabase`. Las pruebas solo aceptan SQLite en memoria o `importsystemgo_testing` con `importsystemgo_test@localhost`. La ejecucion MySQL cargo la credencial protegida por DPAPI en el entorno temporal del proceso y no la mostro ni registro.

No se ejecuto `RefreshDatabase`, migraciones ni seeders contra `importsystemgo`. `RefreshDatabase` se ejecuto exclusivamente en `importsystemgo_testing`; la unica migracion real fue la autorizada sobre `importsystemgo_demo`.

## Comandos reproducibles

```powershell
$php = (Get-Command php).Source
$extensionDirectory = Join-Path (Split-Path $php) 'ext'
& $php -d "extension_dir=$extensionDirectory" -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll vendor/bin/phpunit --configuration phpunit.xml
php vendor/bin/pint --test
npm.cmd run build
php scripts/inspect_delivery_readiness.php
```

La suite MySQL debe ejecutarse solo con la credencial protegida del usuario exclusivo de pruebas. No copie secretos en argumentos ni archivos.

## Limites de evidencia

Existen pruebas funcionales de productos, kardex, ventas, cobros, caja, créditos, compras y reportes. `importsystemgo_demo` está migrada, poblada y conserva las 41 credenciales previamente rotadas.

## Validación del núcleo comercial preparado

| Comprobación | Resultado |
|---|---|
| PHPUnit SQLite en memoria | 71 pruebas, 621 aserciones, 0 fallos, 4 omitidas por requerir MySQL |
| Flujo operativo enfocado | 5 pruebas, 27 aserciones, 0 fallos |
| Laravel Pint | Aprobado |
| Vite | Aprobado; 116 módulos transformados |
| MySQL `importsystemgo_testing` | 71 pruebas, 636 aserciones, 0 fallos |
| MySQL `importsystemgo_demo` | 38 tablas, 13 migraciones, 55 FK, 25 permisos; verificación integral aprobada |
| Seeder comercial | Ejecutado dos veces con conteos idénticos; idempotencia aprobada |
| HTTP local | 200; insignia DEMO y campos de login presentes |

El flujo enfocado crea el esquema desde cero y prueba idempotencia de datos ficticios, saldos por las siete sucursales, venta y reserva, cobro y caja, crédito y cuotas, compra, acceso por rol y aislamiento entre ciudades. El verificador DEMO confirmó además que no existen saldos negativos y que ventas, pagos, cajas, créditos y órdenes cuadran con sus detalles. No se tocó `importsystemgo`.

El respaldo previo a la migración terminó correctamente, contiene las 22 tablas originales y datos de usuarios y sucursales, y permanece fuera del repositorio. Su integridad se registró mediante SHA-256 sin guardar ni mostrar credenciales.

## Correccion de inicio de sesion DEMO

Se detectaron tres procesos antiguos de `artisan serve` de este proyecto compitiendo por el puerto 8000. Además, Laravel 13 en Windows filtra las variables personalizadas al crear el proceso hijo del servidor, por lo que el navegador terminaba usando la configuración principal aunque el envoltorio hubiera verificado DEMO. Se detuvieron únicamente esas instancias de IMPORTSYSTEMGO y se cambió el lanzador al servidor PHP local directo. La respuesta HTTP actual muestra `Entorno académico DEMO`; no se renovaron credenciales ni se modificaron datos.

## Validacion del modulo de inventario (2026-10-23)

| Comprobacion | Resultado |
|---|---|
| PHPUnit `InventoryManagementTest.php`, incluido en suite completa | 10 pruebas; 0 fallos |
| Suite completa SQLite `phpunit.xml` | 98 pruebas, 718 aserciones, 0 fallos, 4 omitidas por requerir MySQL |
| Laravel Pint global | Aprobado (`php vendor/bin/pint --test`) |
| Vite | Aprobado; 119 modulos transformados |
| `php -l` en servicio, controlador, middleware, migracion de triggers y pruebas | Sin errores de sintaxis |
| MySQL aislado / triggers | No ejecutado; el bootstrap de `phpunit.mysql.xml` detuvo la ejecucion porque la credencial protegida no estaba disponible |
| Inspeccion visual autenticada | No realizada; `/inventario` redirigio a `/login` y no se usaron credenciales |
| Bases persistentes | Sin migraciones, seeders ni escrituras sobre `importsystemgo` o `importsystemgo_demo` |

La cobertura agrega creacion/edicion de catalogo, denegacion por rol, costo no asignable por servicio, recepcion idempotente y kardex, serial en venta/cobro, transferencia parcial, aprobacion separada de ajustes, no-negatividad, aislamiento de sucursal/ciudad y cierre con HTTP 503 cuando faltan migraciones. La prueba de guardia elimina una tabla solo en SQLite `:memory:`. Los triggers omiten SQLite por diseño; su ejecucion real en MySQL queda pendiente.

## Validacion de migraciones, triggers y backup (2026-10-24)

| Comprobacion | Resultado |
|---|---|
| PHPUnit completo MySQL, `phpunit.mysql.xml` | 101 pruebas, 756 aserciones, 0 fallos |
| PHPUnit completo SQLite, `phpunit.xml` | 101 pruebas, 721 aserciones, 0 fallos, 6 omitidas por requerir MySQL |
| Laravel Pint | Aprobado (`php vendor/bin/pint --test`) |
| Vite | Aprobado; 119 modulos transformados |
| Backup ZIP con MySQL | 1 prueba, 9 aserciones; SHA-256 y trigger en el SQL verificados |
| Restauracion SQL en base desechable | 52 tablas, 92 FK, 16 triggers, 17 migraciones; 41 usuarios, 7 sucursales y 12 productos |
| Limpieza del test de restauracion | Se elimino solo el esquema desechable creado para el test y su SQL/ZIP temporal |
| Ajustes de privilegios MySQL para pruebas | `log_bin_trust_function_creators` restaurado a `0`; grant `TRIGGER` temporal revocado |
| DEMO | Solo lectura: 41 tablas, 14 migraciones; las tres nuevas migraciones siguen pendientes |
| Base principal | No consultada ni modificada |

El ZIP de prueba se genero desde MySQL aislado, se verifico por SHA-256 y se importo en `importsystemgo_restore_20261024_2324`; el esquema temporal se elimino al concluir. La restauracion incluyo datos de prueba. No se conservaron artefactos de prueba en `storage`.

Se creo para DEMO un respaldo SQL comprimido y cifrado con DPAPI CurrentUser fuera del repositorio. Ruta: `%LOCALAPPDATA%\IMPORTSYSTEMGO\backups\inventory-prompt-006-preflight-20261024-233750\importsystemgo_demo-20261024-233750.sql.gz.dpapi`. Hash SHA-256 del SQL original y del contenido descifrado: `892de19ae8faebad8f51af02304d5ec02acb7af241eeba8d236460be27cb3504`. El usuario de aplicacion y el esquema fuente se identificaron antes del dump; rutinas y eventos almacenados: cero.

La inspeccion de solo lectura registro 12 productos, 5 categorias, 4 marcas, 7 almacenes, 84 balances, 108 movimientos, 21 clientes, 16 ventas y 7 pagos; 0 grupos de SKU repetidos y 0 balances con cantidades negativas o reservas superiores al stock fisico. Hay 0 productos con `requires_serial=true`.

## Validación de aprendizaje automático (2026-11-18)

| Comprobación | Resultado |
|---|---|
| Motor Python real | 1 prueba aprobada; entrenamiento temporal, pronósticos y SHA-256 |
| PHPUnit enfocado ML | 6 pruebas, 26 aserciones, 0 fallos |
| PHPUnit completo SQLite | 107 pruebas, 747 aserciones, 0 fallos, 6 omitidas MySQL |
| Historial DEMO aislado | 45.360 observaciones; segunda ejecución mantiene el conteo |
| Integridad comercial en prueba | 15 ventas y 84 saldos permanecen sin cambios |
| Aislamiento | Jefe LP no ve EA; vendedor denegado; compras no decide otra sucursal |
| Decisión humana | Aprobación auditada y segundo intento rechazado |
| Laravel Pint | Aprobado |
| Vite | Aprobado, 119 módulos |

Durante la validación previa no se ejecutaron migraciones, seeders ni entrenamiento contra bases persistentes; en esa etapa la migración se aplicó únicamente por `RefreshDatabase` en SQLite `:memory:`. El despliegue DEMO posterior se documenta a continuación.

### Despliegue ML controlado en DEMO

- Identidad previa y posterior: `importsystemgo_demo@localhost`; acceso a `importsystemgo` denegado.
- Respaldo SQL comprimido y cifrado con DPAPI verificado por round-trip y SHA-256 antes de migrar.
- Se aplicó por ruta exacta solo `2026_11_18_010001_create_machine_learning_forecast_tables`; las tres migraciones de inventario siguen pendientes.
- Seeder dedicado agregó únicamente 3 permisos ML: total DEMO 47. Vendedor y cajero conservan 0 permisos ML.
- Historial: 45.360 observaciones `synthetic_demo`; ventas 16, saldos 84 y movimientos 108 permanecen sin cambios.
- Resultado: 1 modelo activo, 84 pronósticos, 84 recomendaciones, 7 sucursales y 12 productos.
- Integridad del artefacto comprobada contra el SHA-256 almacenado.
- Dashboard autenticado verificado visualmente: estado `ACTIVE`, métricas, gráfico, filtros y tabla presentes.

El catalogo `AuthorizationCatalog` contiene 44 permisos; la base DEMO tiene 28 y faltan 16: `inventory.movements.view`, `inventory.adjustments.request`, `inventory.adjustments.approve`, `inventory.transfers.request`, `inventory.transfers.approve`, `inventory.transfers.dispatch`, `inventory.transfers.receive`, `inventory.transfers.cross_area`, `inventory.transfers.same_area`, `reports.inventory.view`, `reports.export`, `backup.view`, `backup.create`, `backup.territorial_export`, `backup.verify` y `backup.download`. No se ejecutaron migraciones ni `AuthorizationSeeder` en DEMO. El test `AuthorizationSeederAuditTest::test_demo_catalog_synchronization_is_audited_and_idempotent` confirma que la segunda ejecucion no agrega eventos de auditoria.

Incidencia de prueba aislada: una ejecucion exploratoria con `DatabaseMigrations` completó el cuerpo del test ZIP, pero el rollback automatico de `2026_10_22_020001` fallo al quitar `payments_session_method_date_index`, que MySQL requiere para una FK. No se modifico la migracion historica. Se restauro el test a `RefreshDatabase` y la suite MySQL completa final paso; no se ejecuto rollback en DEMO ni en principal.

## Presentacion de demanda ML (2026-11-18)

| Comprobación | Resultado |
|---|---|
| PHPUnit ML focalizado SQLite | 9 pruebas, 42 aserciones, 0 fallos |
| PHPUnit completo SQLite | 110 pruebas, 763 aserciones, 0 fallos, 6 omitidas MySQL |
| PHPUnit completo MySQL aislado | 110 pruebas, 798 aserciones, 0 fallos |
| Laravel Pint | Aprobado |
| Vite | Aprobado; 119 módulos transformados |
| Navegador autenticado, escritorio/tablet | Módulo `/inteligencia/demanda` cargó con LP01, 7 canvases y sin desbordamiento a 1024 px |
| Navegador móvil | 390 px CSS, scrollWidth 375, sin desbordamiento horizontal |
| Detalle explicable | Modal abrió con producto, sucursal, stock, pronóstico, intervalo, seguridad, riesgo y modelo |
| Simulador | GET hipotético devolvió demanda 40.96, disponible 20.00, stock proyectado 0.89 y sugerencia 5 |
| Mutaciones durante visualización | 84 recomendaciones conservadas, 0 aprobadas, 0 rechazadas; 84 pronósticos; 45.360 observaciones; 1 modelo activo |

El modelo activo real es `HistGradientBoostingRegressor`, entrenado el 18/11/2026 14:46, WAPE 6.8535%, WAPE base 8.75%, MAE 1.53 y sesgo -0.60. El producto recomendado seleccionado para demostración fue `AUD-MIC-200`, “Micrófono inalámbrico dual”: pronóstico 32.28, intervalo 28.91–35.66 y recomendación pendiente de 26 unidades con riesgo crítico.

No se modificaron datos ML durante el simulador ni durante la inspección visual. No se volvió a ejecutar el entrenamiento, no se generaron nuevas observaciones y no se aprobó ni rechazó ninguna recomendación.

## Mejora ML del 2026-11-18

- Pruebas ML focalizadas: 8 pruebas, 30 aserciones, 0 fallos en SQLite en memoria.
- Casos incorporados: redirección al selector sin sucursal activa y 403 sin membresía válida.
- Laravel Pint aprobado para los archivos PHP modificados.
- Vite aprobado: 119 módulos transformados y compilación de producción completada.
- No se ejecutaron migraciones, seeders ni escrituras en `importsystemgo` o `importsystemgo_demo`.

## Auditoría inicial QA CENTER (2026-11-24)

| Comprobación | Resultado |
|---|---|
| PHPUnit SQLite `:memory:` | 110 pruebas, 763 aserciones, 0 fallos, 6 omitidas |
| Laravel Pint | Aprobado |
| Vite | Aprobado; 119 módulos transformados |
| Escrituras persistentes | Ninguna en principal o DEMO |
| Base principal | No consultada ni modificada por esta auditoría |
| DEMO | No consultada ni modificada por esta auditoría |
| Suite MySQL | No iniciada; el servicio no fue restaurado |

La auditoría está documentada en `docs/QA_CENTER_AUDITORIA_2026-11-24.md`. Identifica como próxima tarea segura el catálogo de trazabilidad y el esqueleto reproducible del QA Center. El despliegue en DEMO, el uso de credenciales y las migraciones permanecen bloqueados hasta una autorización específica respaldada.

## Validación de cierre QA CENTER en curso (2026-11-24)

| Comprobación | Resultado |
|---|---|
| `QaCenterWorkflowTest`, PHPUnit + SQLite en memoria | 10 pruebas, 37 aserciones, 0 fallos |
| Runner e informes, `python -m unittest discover -s qa_engine\tests -v` | 3 pruebas aprobadas |
| Integridad Mailable | 2 adjuntos requeridos, hashes verificados; se prueba rechazo de un PDF alterado |
| Login público y salud | `GET /login` y `GET /up`: HTTP 200 en `127.0.0.1:8001` |
| Formulario login en navegador | Confirmados `POST /login`, token CSRF oculto, correo `type=email`, autocomplete `username`, validez HTML5 y 0 enlaces con el correo QA; formulario no enviado sin contraseña |
| Diagnóstico POST `/login` reportado | El fallo era en `:8000`, dentro de `throttle`/caché de base de datos antes del controlador; el proceso HTTP hijo carecía del driver SQLite. No era fallo de email, CSRF, contraseña ni permisos. Usar `:8001` con el script QA |
| Servidor / cola | Servidor PHP local y worker `queue:work database` como procesos independientes |
| Inicio idempotente QA | Reejecutar `iniciar_qa.ps1 -Port 8001` reutilizó servidor PID 21140 y worker PID 24484; no creó procesos adicionales |
| Cola para cambio seguro de secreto | Consulta SQLite `mode=ro`: `jobs` presente, 0 jobs; no se detuvo el worker para conservar los servicios activos mientras falta el prompt local |
| Cierre selectivo del worker | `detener_qa_seguro.ps1 -WorkerOnly` ahora valida cola vacía y PID/ejecutable/comando. Se validó sintaxis; no se ejecutó hasta que el usuario ingrese el secreto en terminal interactiva |
| PHP SQLite | `pdo_sqlite` y `sqlite3` cargan con las DLL de la instalación PHP 8.4 |
| Selenium Edge | El caso `TC-SAN-003` observó `typeMismatch=true`, `form.checkValidity()=false` y mensaje nativo; el FAIL CONTROLADO contiene etiqueta y captura. Se ejecutó directamente en el motor, aún no desde UI autenticada |
| Robot Framework | PASS; `/dashboard` sin sesión redirige a `/login`; captura generada por la suite |
| Locust | PASS: CSV agregado 14/14, 30/30, 60/60 y 20/20; 0 fallos |
| CSV histórico | `locust-short_stats.csv`: fila `Aggregated` con 14 solicitudes y 0 fallos; el historial termina en 14 |
| Mailpit | v1.34.0 instalado previamente desde paquete oficial con SHA-256 verificado; listener SMTP/UI del mismo PID 27336, SMTP `220`, UI HTTP 200. Reejecutar `iniciar_mailpit.ps1` no duplicó proceso |
| Correo QA E2E | Mailpit API informa 0 mensajes; no se ha enviado ni recibido un reporte PDF/HTML desde la interfaz |
| Inicio automático Mailpit | `iniciar_qa.ps1` invoca instalación oficial solo si falta el ejecutable y asegura `iniciar_mailpit.ps1`; rama de reinstalación no ejecutada porque el binario ya estaba instalado |
| Login con cuenta QA | Bloqueado hasta que la contraseña se ingrese localmente mediante prompt oculto; dashboard y ejecución autenticados aún no comprobados |
| Fuente académica | Se sincronizaron fuentes QA permitidas; 0 coincidencias de nombres para `.env`, SQLite, claves/certificados, `vendor`, `node_modules` o entornos virtuales |
| GitHub | API confirmó que `https://github.com/DannerCM/Proyecto-Integrador-TQA` existe y es público; commit local `803420c` confirmado. Git no tiene remoto; `gh` sin autenticación; no hubo push |
| Archivo ZIP | El ZIP presente en la raíz es un artefacto previo (58 entradas) y no contiene la sincronización reciente; no es entrega final ni fue regenerado |
| Bases persistentes | Sin migraciones, seeders ni operaciones sobre principal o DEMO |

No se ejecutó la suite completa en esta fase: se usaron pruebas dirigidas y motores locales que cubren los cambios del runner, clasificación, informes, SMTP local disponible y límite concurrente de Locust. Falta ejecutar el flujo completo autenticado desde la web, verificar el mensaje Mailable en Mailpit, generar las capturas autenticadas y revisar visualmente los PDF finales.

## Validación para exposición QA CENTER (2026-11-24)

| Comprobación | Resultado |
|---|---|
| Regresión `phpunit.xml`, SQLite `:memory:` | 120 pruebas, 800 aserciones, 0 fallos, 6 omitidas MySQL |
| `QaCenterWorkflowTest` | 10 pruebas, 37 aserciones, 0 fallos |
| Laravel Pint sobre el cambio | Aprobado |
| Respaldo funcional | 2 casos PASS; 11 y 6 aserciones |
| FAIL CONTROLADO | Edge: `typeMismatch=true`, formulario inválido y captura conservada |
| Rendimiento corto | 14 solicitudes, 0 fallos, promedio 447 ms, p95 520 ms, 16,5 s |
| Guion PDF | 2 páginas revisadas visualmente en Edge; sin recortes ni solapamientos |
| MySQL / DEMO / principal | No consultados ni modificados; sin migraciones ni seeders |

El primer intento de PHPUnit con el intérprete global falló antes de ejecutar casos porque no cargó `pdo_sqlite`. La repetición válida usó el mismo PHP con `php_pdo_sqlite.dll` y `php_sqlite3.dll`, la configuración `phpunit.xml` y SQLite en memoria. Permanecen pendientes la autenticación UI con secreto ingresado localmente, el correo E2E en Mailpit y Ollama.

### Regresión del worker QA (2026-11-24)

- `QaCenterWorkflowTest` mediante `phpunit.xml`: 11 pruebas, 39 aserciones y cero fallos. El callback de fallo conserva el aislamiento fuera de `database/qa.sqlite`.
- Verificación operativa en la cola QA aislada: `TC-SMK-003` terminó en `PASS` y progreso 100 %. La ejecución 3 generó HTML y PDF. La API de Mailpit confirmó ambos adjuntos. Esta verificación comenzó en el servicio de ejecución, con un worker nuevo de un solo trabajo.
- Verificación posterior con el worker permanente y su cola predeterminada: ejecución 7 `COMPLETED` al 100 % en aproximadamente 8 s, con 2 PASS, 2 BLOCKED por credencial de Selenium y 0 casos FAIL. La bandeja Mailpit confirmó los dos adjuntos de esa ejecución. El envío a la cola se hizo desde el servicio de ejecución.
- Con la demostración del asistente, `QaCenterWorkflowTest` aprobó 12 pruebas y 49 aserciones en SQLite en memoria. Cubre permiso denegado, las tres preguntas, variaciones de escritura, una pregunta desconocida y ausencia de registros de análisis para la simulación.
