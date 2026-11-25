# Modelo de permisos y alcance

## 1. Principios

- Denegar por defecto y conceder solo capacidades explícitas.
- Separar cargo empresarial, rol técnico y permiso atómico.
- Asignar roles a empleados con ámbito `global` o `branch`.
- Evaluar siempre permiso, ámbito, estado de la asignación y pertenencia del recurso.
- Separar crear, aprobar, ejecutar, anular y consultar cuando exista conflicto de funciones.
- No inferir permisos administrativos a partir de acceso a reportes.
- Auditar asignaciones, revocaciones, elevaciones y uso de permisos críticos.

## 2. Modelo recomendado

```text
users 1---0..1 employees
users 1---* branch_user_memberships *---1 branches
users 1---* model_has_roles *---1 roles
users 1---* global_user_roles *---1 roles
roles *---* permissions
```

Una asignación local en `model_has_roles` exige `branch_id` y una membresía vigente. Una asignación global existe únicamente en `global_user_roles` y exige que el rol tenga `scope_type=global`, `branch_id=NULL` y `guard_name=web`.

Los roles enumerados son plantillas iniciales. Los permisos son la autoridad técnica; no deben dispersarse comparaciones de nombres de rol como `if ($user->role === ...)`.

Los permisos directos a usuarios están deshabilitados. Un rol local no puede crear ni asignar roles globales aunque tenga una capacidad local con el mismo nombre. La consulta consolidada usa `reports.consolidated.view` y no implica permisos de modificación en otras sucursales.

## 3. Catálogo inicial de permisos

Convención: `<módulo>.<recurso>.<acción>`.

| Dominio | Permisos propuestos |
|---|---|
| Seguridad | `security.users.view`, `security.users.manage`, `security.roles.view`, `security.roles.manage`, `security.audit.view` |
| Organización | `organization.branches.view`, `organization.branches.manage`, `organization.employees.view`, `organization.employees.manage` |
| Catálogo | `catalog.products.view`, `catalog.products.manage`, `catalog.prices.manage` |
| Inventario | `inventory.stock.view`, `inventory.movements.view`, `inventory.adjustments.request`, `inventory.adjustments.approve`, `inventory.transfers.request`, `inventory.transfers.approve`, `inventory.transfers.dispatch`, `inventory.transfers.receive` |
| Compras | `purchasing.requests.create`, `purchasing.requests.approve`, `purchasing.orders.manage`, `purchasing.receipts.create`, `purchasing.suppliers.manage` |
| Ventas | `sales.quotes.manage`, `sales.orders.create`, `sales.orders.view`, `sales.discounts.request`, `sales.discounts.approve`, `sales.returns.request`, `sales.returns.approve`, `sales.deliveries.manage` |
| Reservas | `reservations.create`, `reservations.view`, `reservations.release` |
| Caja | `cash.sessions.open`, `cash.sessions.operate`, `cash.sessions.close`, `cash.sessions.supervise`, `cash.adjustments.approve` |
| Pagos | `payments.collect`, `payments.reverse.request`, `payments.reverse.approve`, `payments.view` |
| Créditos | `credit.applications.create`, `credit.applications.evaluate`, `credit.applications.approve`, `credit.collections.collect`, `credit.portfolio.view` |
| Posventa | `after_sales.cases.create`, `after_sales.cases.manage`, `after_sales.returns.approve` |
| Incentivos | `incentives.goals.manage`, `incentives.commissions.view_own`, `incentives.commissions.manage` |
| Reportes | `reports.branch.view`, `reports.consolidated.view`, `reports.export` |
| Fiscal | `fiscal.documents.view`, `fiscal.documents.issue`, `fiscal.documents.cancel.request`, `fiscal.documents.cancel.approve`, `fiscal.configuration.manage`, `fiscal.contingencies.manage` |

`manage` deberá dividirse en permisos más finos si durante la implementación agrupa acciones con riesgo distinto.

## 4. Matriz base por cargo

Leyenda: **O** opera dentro de su sucursal; **V** consulta; **G** alcance global; **A** aprueba; **—** sin concesión inicial.

| Capacidad | Adm. general | Jefe operaciones | Jefe tienda | Vendedor | Cajero | Asesor crédito | Almacenero | Enc. compras |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Usuarios y roles | G | V | O empleados | — | — | — | — | — |
| Sucursales | G | G | O/V otras | — | — | — | — | V |
| Catálogo | G | G | V | V | V | V | V | O |
| Stock/kardex | G | G | O | V | V | V | O | V |
| Transferencias | G | A/G | solicitar/O | — | — | — | despachar/recibir | solicitar |
| Solicitudes de compra | G | A/G | solicitar/O | — | — | — | recibir | O |
| Proveedores/órdenes | G | G | V | — | — | — | V | O |
| Cotizaciones/ventas | G | G | O | O | V | V | — | — |
| Descuento especial | G | A/G | solicitar | solicitar | — | — | — | — |
| Reservas | G | G | O/liberar | crear | V | V | V | — |
| Cobros | G | V/G | supervisar | V | O | cobranzas | — | — |
| Apertura/cierre caja | G | V/G | supervisar | — | O propia | — | — | — |
| Créditos | G | A/G* | V | solicitar | cobrar* | O/evaluar | — | — |
| Devoluciones | G | V/G | A/O | solicitar | reverso autorizado | V | recibir | — |
| Garantías/servicio | G | G | O | crear | V | V | recibir/entregar | — |
| Metas/comisiones | G | G | V sucursal | V propia | V propia | V propia | V propia | V propia |
| Reporte sucursal | G | G | O | V propio | V caja propia | V cartera | V inventario | V compras |
| Consolidado | G | G | V sin modificar | — | — | — | — | — |
| Facturación | G | G/supervisa | V sucursal | solicitar | emitir asociada al cobro* | — | — | — |

Los asteriscos son **pendientes**: quién aprueba créditos, quién ejecuta formalmente la emisión fiscal y quién cobra cuotas deben confirmarse antes de configurar roles.

## 5. Reglas obligatorias por rol

### Administrador general

- Alcance global administrativo.
- No debe usar su cuenta privilegiada para operaciones cotidianas cuando exista una función operativa específica.
- Las modificaciones de roles, configuración fiscal y accesos requieren auditoría reforzada.

### Jefe de operaciones

- Supervisa y opera globalmente donde la matriz lo permita.
- Es la autoridad confirmada para descuentos especiales y compras solicitadas por tiendas.
- Su aprobación debe registrar importe, regla aplicada y justificación.

### Jefe de tienda

- Opera únicamente su sucursal asignada.
- Supervisa empleados, inventario, ventas y cajas de esa sucursal.
- Puede leer indicadores consolidados autorizados, sin que esa lectura amplíe permisos de modificación.
- Autoriza devoluciones de su sucursal.

### Vendedor

- Crea cotizaciones, ventas y reservas dentro de su sucursal.
- No confirma cobros ni se autoaprueba descuentos especiales.

### Cajero

- Opera solamente su sesión de caja abierta y la sucursal vinculada.
- Cobra ventas válidas; no cambia precios, vendedores ni productos después del control previo.
- No revierte pagos sin flujo de autorización.

### Asesor de créditos

- Registra y evalúa solicitudes y documentos conforme a su alcance.
- No aprueba por encima de su umbral ni altera cuotas ya formalizadas sin operación de reestructuración autorizada.

### Almacenero

- Opera recepciones, despacho, entrega y conteos del almacén asignado.
- No autoriza su propio ajuste cuando la política exige segunda persona.

### Encargado de compras

- Gestiona proveedores y órdenes autorizadas.
- No aprueba solicitudes que por regla correspondan al jefe de operaciones.

## 6. Segregación de funciones

Como mínimo, el sistema debe impedir o alertar:

- solicitar y aprobar el mismo descuento especial;
- solicitar y aprobar la misma compra o transferencia;
- registrar y aprobar el mismo ajuste de inventario;
- cobrar y aprobar la reversión del mismo pago;
- evaluar y aprobar un crédito por encima del umbral propio;
- emitir y anular un documento fiscal sin la autorización requerida;
- abrir, operar, arquear y aprobar diferencias de la misma caja cuando la política exija supervisión.

La acumulación de roles no elimina estos controles. Las excepciones requieren permiso específico, motivo y auditoría.

## 7. Aplicación en Laravel

- Middleware: autenticación, estado activo, selección de sucursal y límites generales.
- Form Request: formato y existencia de identificadores dentro del ámbito accesible.
- Policy: capacidad sobre recurso y sucursal concreta.
- Service/Action: separación de funciones, umbrales y estado actual dentro de la transacción.
- Query objects/reportes: filtros de ámbito obligatorios y pruebas de filtración cruzada.
- Jobs: serializar identificadores, volver a resolver actor/ámbito y no confiar en modelos capturados desactualizados.

Las rutas no deben aceptar autorización mediante campos como `approved_by`, `branch_id` o `role`; esos valores se derivan del contexto autenticado y del flujo de aprobación.

## 8. Pruebas mínimas de autorización

- Cada permiso: permitido y denegado.
- Usuario con dos roles en la misma sucursal.
- Usuario con roles distintos en dos sucursales.
- Jefe de tienda leyendo consolidado y fallando al modificar otra sucursal.
- Recurso movido/cambiado entre validación y ejecución.
- Asignación vencida, revocada o usuario inactivo.
- Parámetros manipulados, enumeración de IDs y exportaciones.
- Jobs y comandos ejecutados sin contexto HTTP.
- Conflictos de segregación de funciones.

## 9. Decisiones pendientes

- Umbrales concretos por monto, riesgo, cantidad y categoría.
- Autoridad final de aprobación de créditos por cada rango.
- Responsable operativo de emisión/anulación fiscal.
- Acceso de soporte técnico a datos y mecanismo de acceso excepcional.
- Necesidad de roles temporales, suplencias y doble aprobación.
- Definir si las futuras suplencias temporales requieren doble aprobación; el RBAC base ya se implementa con Spatie Laravel Permission 8.3.2 y Teams.

## 10. Inteligencia predictiva

| Permiso | Alcance |
|---|---|
| `ml.forecasts.view` | Consulta pronósticos únicamente en sucursales autorizadas |
| `ml.models.manage` | Entrena y versiona modelos; solo administrador general no territorial |
| `ml.replenishment.decide` | Aprueba o rechaza recomendaciones dentro del ámbito autorizado |

El administrador general recibe las tres capacidades. El jefe de operaciones consulta y decide en su área; el jefe de tienda consulta su sucursal; compras consulta y decide su sucursal. Vendedores y cajeros no reciben permisos ML.

