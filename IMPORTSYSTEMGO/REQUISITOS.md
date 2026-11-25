# Requisitos de IMPORTSYSTEMGO

## 1. Propósito y alcance

IMPORTSYSTEMGO será un ERP para una importadora y cadena de tiendas retail, construido como monolito modular sobre Laravel 13, PHP 8.4 y MySQL. La administración será centralizada y la operación diaria estará aislada por sucursal, con una base de datos común, almacén central y almacenes asociados a sucursales.

Este documento usa tres estados:

- **Confirmado:** decisión expresamente aprobada y obligatoria.
- **Recomendación:** criterio técnico propuesto, sujeto a validación durante el desarrollo.
- **Pendiente:** decisión empresarial, contable, legal o técnica que requiere autorización.

La Fase C fue documental. PROMPT MAESTRO 003 autoriza actualmente implementar el ERP por entregas y preparar migraciones; su ejecución nueva en principal sigue requiriendo aprobación explícita. El estado verificable se mantiene en `PROGRESO.md`.

## 2. Requisitos confirmados

### 2.1 Organización y acceso

- Una persona empleada puede tener varios roles y el alcance de cada asignación puede ser global o limitarse a una sucursal.
- Toda autorización se valida en el servidor. Ocultar controles en la interfaz no constituye autorización.
- El administrador general administra la plataforma.
- El jefe de operaciones supervisa todas las sucursales, ventas y facturación, y autoriza descuentos especiales.
- El jefe de tienda supervisa empleados, movimientos, ventas, caja y rendimiento de su sucursal.
- El jefe de tienda puede consultar indicadores consolidados de otras sucursales, pero no modificar sus operaciones.
- El vendedor registra ventas y el cajero registra el cobro.
- Toda operación crítica debe quedar auditada.

### 2.2 Inventario

- El catálogo manejará SKU, cantidades y, cuando corresponda, número de serie o IMEI.
- Las existencias serán por almacén; cada almacén pertenecerá al almacén central o a una sucursal.
- Las recepciones podrán ser parciales o completas.
- Se admitirán transferencias entre almacenes y sucursales.
- Los umbrales de importe, cantidad o tipo de producto determinarán autorizaciones configurables.
- Todo movimiento será trazable y el stock negativo no estará permitido salvo una autorización explícita definida posteriormente.
- Los movimientos físicos, las reservas y el stock disponible serán conceptos separados.

### 2.3 Ventas, reservas y entregas

- Cada producto tendrá un precio de lista único vigente.
- Los descuentos especiales requieren autorización del jefe de operaciones.
- El vendedor registra la venta y el cajero cobra.
- Las reservas solamente se liberan manualmente por personal autorizado.
- Se permiten entregas en tienda, desde almacén y programadas.
- Las devoluciones requieren autorización del jefe de tienda.

### 2.4 Caja y pagos

- Cada cajero opera una caja individual.
- La caja soportará apertura, ingresos, egresos, arqueo y cierre.
- Los métodos confirmados son efectivo, QR, tarjeta y transferencia.
- Un cobro puede combinar varios métodos de pago.
- El sistema debe impedir pagos duplicados mediante claves de idempotencia y restricciones persistentes.

### 2.5 Créditos y cobranzas

- Las aprobaciones serán configurables por monto y riesgo.
- Se registrarán anticipos, garantes y documentos.
- Los planes admitirán cuotas semanales, quincenales y mensuales.
- Intereses y mora serán configurables.
- Se conservarán el historial de cobranzas y el saldo reproducible del crédito.

### 2.6 Compras

- El jefe de tienda solicita compras y el jefe de operaciones las autoriza.
- Las recepciones parciales están permitidas.
- La recepción autorizada genera los movimientos físicos correspondientes sin alterar el pedido histórico.

### 2.7 Garantías, metas y comisiones

- El servicio posventa manejará reparaciones, cambios, garantías y devoluciones.
- Existirán metas por sucursal y seguimiento individual de vendedores.
- Las comisiones serán proporcionales a ventas individuales y estarán sujetas al cumplimiento de metas.

### 2.8 Facturación electrónica

- La modalidad objetivo es Facturación Electrónica en Línea de Bolivia.
- La integración con el SIN estará desacoplada del núcleo de ventas.
- Se contemplarán firma digital, CUIS, CUFD, CUF, validaciones, contingencias, reintentos y trazabilidad.
- En esta fase no se emiten facturas reales ni se almacenan credenciales fiscales.
- Ninguna regla fiscal se implementará por suposición: versiones de esquemas, catálogos, endpoints, códigos de eventos, plazos y procedimientos se verificarán con documentación oficial vigente del SIN antes de desarrollar.

## 3. Módulos funcionales

| Módulo | Responsabilidad principal | Dependencias relevantes |
|---|---|---|
| Autenticación y seguridad | Inicio/cierre de sesión, recuperación, sesiones, políticas y controles de seguridad | Usuarios, auditoría |
| Usuarios, empleados y roles | Identidad, vínculo laboral, roles, permisos y ámbitos | Sucursales |
| Sucursales | Organización comercial, puntos operativos y parámetros | Empleados, almacenes, cajas |
| Almacenes | Ubicaciones lógicas de stock | Sucursales, inventario |
| Productos | SKU, marcas, categorías, unidades, serialización y precio de lista | Inventario, ventas, compras |
| Inventarios | Saldos, kardex, series/IMEI, ajustes y transferencias | Productos, almacenes, auditoría |
| Compras | Solicitudes, autorizaciones, órdenes y recepciones | Proveedores, inventario |
| Ventas y cotizaciones | Cotización, pedido/venta, descuentos, totales y estados | Clientes, productos, inventario |
| Reservas | Asignación temporal de stock y liberación autorizada | Ventas, inventario |
| Caja | Turnos individuales, movimientos, arqueo y cierre | Sucursales, pagos |
| Pagos | Cobros, pagos combinados, referencias e idempotencia | Ventas, caja, créditos |
| Créditos | Evaluación, aprobación, contrato, plan y saldo | Clientes, garantes, cobranzas |
| Garantías y servicio | Casos, diagnóstico, reparaciones, cambios y devoluciones | Ventas, series, inventario |
| Metas y comisiones | Metas, cálculo reproducible y liquidación | Ventas, empleados |
| Reportes | Indicadores operativos según alcance del usuario | Todos los módulos, preferentemente lectura |
| Facturación SIN | Documento fiscal, firma, envío, eventos y contingencia | Ventas, pagos, integración externa |

## 4. Requisitos no funcionales

### 4.1 Seguridad

- Aplicar mínimo privilegio, denegación por defecto, protección CSRF, regeneración de sesión y limitación de intentos.
- Cifrar secretos y documentos sensibles en reposo cuando corresponda; nunca almacenarlos en Git ni en logs.
- Evitar acceso directo a archivos privados; toda descarga debe pasar por autorización.
- Auditar accesos fallidos, cambios de permisos, autorizaciones, ajustes de inventario, descuentos, devoluciones, caja, créditos y facturación.

### 4.2 Integridad y concurrencia

- Las operaciones que afecten saldos usarán transacciones de base de datos.
- Los agregados mutables usarán bloqueo pesimista (`SELECT ... FOR UPDATE`) o control optimista con versión, según el caso documentado.
- Importes monetarios usarán `DECIMAL`, nunca punto flotante.
- Fechas se almacenarán con precisión suficiente y se presentarán en `America/La_Paz`.
- Las operaciones externas reintentables usarán idempotencia y una bandeja de salida transaccional cuando corresponda.

### 4.3 Calidad y operación

- Controllers delgados; reglas de negocio en Services/Actions; validación con Form Requests; autorización con Policies.
- Pruebas unitarias, de integración y de autorización para cada funcionalidad nueva.
- Migraciones versionadas y reversibles cuando la naturaleza del cambio lo permita.
- Logs estructurados con identificador de correlación, sin contraseñas, tokens, certificados ni datos completos de pago.
- Índices y consultas se revisarán con volúmenes representativos antes de producción.

## 5. Criterios de aceptación transversales

- Un usuario no puede leer ni modificar datos fuera de su alcance, excepto consultas consolidadas autorizadas.
- Toda mutación crítica registra actor, ámbito, acción, entidad, fecha, correlación y cambios permitidos.
- Un mismo pago o solicitud externa repetida no duplica efectos.
- La suma de pagos aplicados no excede el saldo exigible salvo una regla explícita de anticipo.
- El stock disponible nunca se deriva de una sola cifra editable: se calcula desde existencia física menos reservas activas, considerando movimientos confirmados.
- Las transferencias, ventas, devoluciones, recepciones y cobros conservan su historia; las correcciones se realizan con operaciones compensatorias.

## 6. Decisiones pendientes

- Datos legales de la empresa, NIT, actividades económicas, sucursales y puntos de venta fiscales.
- Modalidad efectivamente asignada por el SIN y proceso de autorización/homologación del sistema.
- Estrategia de autenticación reforzada: MFA, políticas de contraseña y proveedores de identidad.
- Duración de sesiones, política de bloqueo y retención de auditoría.
- Moneda secundaria, impuestos no fiscales, redondeo y precisión de cantidades.
- Regla exacta para autorizar stock negativo, si finalmente se habilita.
- Vigencia y expiración automática de reservas; por ahora la liberación confirmada es manual.
- Matriz de umbrales de descuentos, compras, transferencias y créditos.
- Flujo de anulación de ventas y documentos fiscales.
- Reglas contables, plan de cuentas e integración bancaria.
- Fórmula, devengo, reversión y aprobación de comisiones.
- Política de datos personales, retención documental y copias de seguridad.
- Proveedor y custodia del certificado digital, HSM o almacén seguro de claves.

## 7. Decisiones confirmadas en Fase D, Etapa 1

- Spatie Laravel Permission `^8.3` es la base del catálogo RBAC y usa `guard_name=web`.
- Los roles por sucursal usan Spatie Teams con `branch_id` como clave de equipo.
- Los roles globales usan `global_user_roles` y solo conceden capacidades explícitas.
- El acceso global no usa un `Gate::before` universal; las Policies conservan la comprobación del recurso y su sucursal.
- El centro logístico se representa mediante una sucursal activa de tipo `central`; todo almacén requiere `branch_id`.
- Los empleados pueden existir sin usuario y un usuario puede pertenecer a varias sucursales.
- `users.primary_branch_id` representa una sola sucursal principal y siempre debe corresponder a una membresía vigente.
- Los permisos directos a usuarios están prohibidos inicialmente.
- La revocación de una membresía elimina sus roles locales y la sucursal principal asociada dentro de la misma transacción.
- Sucursales, almacenes y empleados se desactivan por estado; no se borran como flujo ordinario.
- Los roles personalizados quedan reservados a la administración central.

Las siete migraciones de esta etapa ya fueron ejecutadas y verificadas sobre MySQL principal. E1-001 prepara una migración adicional de auditoría, aún no aplicada en principal.

