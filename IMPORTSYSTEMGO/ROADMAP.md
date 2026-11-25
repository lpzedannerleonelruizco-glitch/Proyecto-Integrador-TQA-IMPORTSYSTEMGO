# Roadmap de IMPORTSYSTEMGO

## Actualización E1-003 — Estado de Entrega 1

El desarrollo por entregas está autorizado. La secuencia vigente es núcleo → inventarios → ventas → caja → compras → créditos → garantías → inteligencia comercial → fiscal → estabilización. El detalle histórico siguiente no autoriza ejecutar migraciones principales ni implica que los módulos estén terminados.

Entrega 1 parcial: acceso, recuperación protegida, selección de sucursal, dashboard organizativo, mantenimiento auditado y administración de cuentas/membresías/roles personalizados implementados y probados en SQLite. E1-002 agrega vínculo empleado-cuenta, motivo en revocación, bloqueo de autootorgamiento/delegación excesiva, desactivación con revocación de permisos y baja laboral coordinada; Pint y Vite aprobados. E1-003 desplegó con autorización la migración de auditoría en principal después de verificar un respaldo cifrado. No hay usuario inicial y quedan pruebas MySQL concurrentes. No se declara cerrada Entrega 1. Consultar `PROGRESO.md`, `DECISIONES.md` y `PRUEBAS.md`.

## 1. Principios de entrega

- Construir por capacidades verticales verificables, no por pantallas aisladas.
- Separar requisitos confirmados, recomendaciones y decisiones pendientes.
- No iniciar una fase que altere esquema, infraestructura o integraciones sin autorización.
- Cada fase termina con pruebas, documentación, revisión de seguridad y evidencia de aceptación.
- Los datos históricos se preservan; los cambios correctivos usan migraciones y operaciones compensatorias.

## 2. Estado actual

| Fase | Alcance | Estado |
|---|---|---|
| A | MySQL, usuario limitado y endurecimiento local | Completada |
| B | Laravel 13, MySQL, Bootstrap/Vite, migraciones base y pruebas | Completada |
| C | Arquitectura, requisitos, permisos, modelo de datos y roadmap | Completada al aprobar estos documentos |
| D1 | Organización, identidad y RBAC | Completada: migrada y verificada en MySQL principal |

## 3. Fase D propuesta: fundamentos de datos

La Fase D debe diseñar e implementar migraciones, modelos y pruebas de restricciones, sin construir aún todos los flujos de negocio.

### D1. Organización e identidad

- `branches`, `warehouses`, `employees`.
- Roles, permisos, asignaciones y ámbito.
- Estados, vigencias, claves foráneas e índices.
- Seeders idempotentes para roles/permisos aprobados.
- Pruebas de asignación global/local y restricciones.

### D2. Catálogo e inventario base

- Marcas, categorías, unidades, productos y precios.
- Balances, seriales/IMEI y kardex append-only.
- Restricciones de cantidad, unicidad e integridad.
- Pruebas concurrentes de reserva/salida como condición de aceptación.

### D3. Transacciones comerciales

- Proveedores, compras y recepciones parciales.
- Clientes, cotizaciones, ventas, descuentos, reservas y entregas.
- Caja, pagos, componentes y aplicaciones idempotentes.
- Créditos, cuotas y ledger.

### D4. Soporte transversal

- Aprobaciones, auditoría y outbox.
- Garantías/servicio, metas/comisiones.
- Esqueleto fiscal sin credenciales ni comunicación real, únicamente si se autoriza y ya se validó el modelo oficial.

### Puertas de control de Fase D

- Diagrama y diccionario revisados con responsables del negocio.
- Precisión monetaria, moneda y redondeo aprobados.
- Estados y transiciones aprobados por módulo.
- Matriz de permisos y umbrales aprobada.
- Copia de seguridad y procedimiento de restauración verificados antes de migrar un ambiente con datos.
- `migrate --pretend` revisado; ejecución real requiere autorización explícita.
- Pruebas de `up()`, restricciones y estrategia de reversión en una base desechable.

## 4. Fases funcionales posteriores propuestas

### E. Seguridad, usuarios y organización

- Autenticación productiva, empleados, RBAC, sucursales y cambio de contexto.
- Auditoría de accesos y administración.
- Pruebas de aislamiento entre sucursales.

### F. Catálogo e inventario

- Administración de catálogo, stock, kardex, series/IMEI, ajustes y transferencias.
- Conteos, conciliación y alertas.
- Condición: demostrar ausencia de sobreventa bajo concurrencia.

### G. Compras

- Solicitud, autorización, orden y recepción parcial/completa.
- Integración transaccional con inventario.

### H. Ventas, reservas y entregas

- Cotizaciones, ventas, descuentos especiales, reservas manuales y entregas.
- Devoluciones autorizadas y movimientos compensatorios.

### I. Caja y pagos

- Apertura/cierre individual, arqueo, ingresos/egresos y pagos combinados.
- Idempotencia, reversos autorizados y conciliación.

### J. Créditos y cobranzas

- Evaluación, aprobaciones por riesgo/monto, garantes, documentos y cronogramas.
- Interés, mora, cobranzas, saldo reproducible y reestructuración autorizada.

### K. Garantías, metas y reportes

- Servicio técnico, cambios y garantías.
- Metas, comisiones versionadas y dashboards por alcance.
- Optimización de lecturas y exportaciones auditadas.

### K1. Inteligencia predictiva

- Pronóstico de demanda y reposición preparado con modelo versionado, línea base y validación temporal.
- Pendiente desplegar el esquema y el historial sintético únicamente en DEMO después de respaldo y autorización.
- Evolución posterior: monitoreo de deriva, reentrenamiento programado y comparación de modelos; detección de anomalías se evaluará después de validar demanda.

### L. Facturación Electrónica en Línea

1. Confirmar asignación y normativa vigente con el SIN.
2. Registrar/homologar el sistema y obtener acceso al ambiente oficial de pruebas.
3. Implementar adaptador, catálogos, sincronización, CUIS, CUFD y CUF.
4. Implementar XML, XSD, XMLDSig y custodia segura de claves.
5. Implementar envío, respuestas, representación gráfica y entrega.
6. Implementar eventos significativos, contingencia, paquetes, reintentos e idempotencia.
7. Ejecutar homologación y revisión legal/contable.
8. Activar producción mediante plan de cambio y reversión aprobado.

No emitir documentos fiscales reales antes de completar estas puertas.

### M. Preparación productiva

- Infraestructura, HTTPS, secretos, workers, scheduler y monitoreo.
- Backups cifrados y simulacro de restauración.
- Pruebas de carga, seguridad y recuperación.
- Capacitación, datos maestros, piloto por sucursal y despliegue progresivo.

## 5. Estrategia de versiones y Git

No existe todavía un repositorio Git en `C:\PROYECTOS\IMPORTSYSTEMGO`.

**Propuesta pendiente de autorización:** ejecutar `git init`, definir la rama principal, revisar el inventario inicial y crear un primer commit local. No configurar remotos ni publicar en GitHub hasta recibir autorización específica.

Antes del primer commit se debe verificar:

- `.env`, certificados, claves, tokens, credenciales y archivos temporales ignorados;
- `vendor`, `node_modules`, builds y caches excluidos;
- `.env.example` sin secretos y expresamente permitido;
- ausencia de credenciales en historial, logs y artefactos;
- documentación y lockfiles incluidos.

## 6. Riesgos técnicos y mitigaciones

| Riesgo | Impacto | Mitigación propuesta |
|---|---|---|
| Fuga de datos entre sucursales | Crítico | Contexto servidor, Policies, scopes explícitos y pruebas cruzadas |
| Sobreventa por concurrencia | Crítico | Transacciones, bloqueos ordenados, constraints y pruebas paralelas |
| Pago duplicado | Crítico | Idempotency keys, referencias únicas y ledger inmutable |
| Inconsistencia saldo/kardex | Alto | Movimiento append-only, actualización atómica y conciliación |
| Acumulación peligrosa de roles | Alto | Segregación de funciones y aprobación auditada |
| Cambios normativos del SIN | Alto | Adaptador versionado, fuentes oficiales, catálogos sincronizados y homologación |
| Compromiso de clave fiscal | Crítico | Almacén seguro/HSM, mínimo privilegio, rotación y auditoría |
| Fallo de conectividad con SIN | Alto | Outbox, contingencias oficiales, reintentos idempotentes y monitoreo |
| Cálculos monetarios divergentes | Alto | `DECIMAL`, política única de redondeo y pruebas de borde |
| Reportes degradan operación | Medio/alto | Índices, consultas dedicadas, colas/exportaciones y futura réplica de lectura |
| Pérdida de datos | Crítico | Backups cifrados, retención y restauraciones ensayadas |
| Crecimiento de auditoría/fiscal | Medio/alto | Retención legal, archivado/particionado y capacidad planificada |

## 7. Decisiones necesarias para continuar Fase D

1. Autorizar el alcance detallado de D2 antes de preparar o ejecutar nuevas migraciones.
2. Definir el código y nombre reales de la sucursal central antes de sembrar datos empresariales.
3. Aprobar moneda, escalas decimales, impuestos internos y política de redondeo.
4. Aprobar estados y transiciones de los módulos posteriores.
5. Definir umbrales y autoridades de descuentos, compras, inventario y créditos.
6. Confirmar vencimiento de reservas o mantener liberación exclusivamente manual.
7. Definir quién emite, observa y anula documentos fiscales.
8. Definir retención de auditoría, documentos y datos personales.
9. Autorizar o rechazar la inicialización del repositorio Git local.

## 8. Criterio de finalización global

IMPORTSYSTEMGO estará listo para producción solo cuando los módulos aprobados cumplan sus invariantes, la autorización por sucursal haya sido probada, la recuperación desde backup esté ensayada, la integración fiscal esté homologada, no existan vulnerabilidades críticas conocidas y negocio/contabilidad hayan aceptado los flujos y reportes.
