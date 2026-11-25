# Catálogo de casos de prueba — IMPORTSYSTEMGO QA CENTER

El catálogo de base de datos contiene 23 casos en 7 suites. La columna **Automatización** describe si existe un ejecutor específico en `config/qa.php`; no representa el resultado de una ejecución. Cada ejecución puede producir `PASS`, `FAIL`, `BLOCKED` o `NOT RUN`.

| Código | Suite | Caso | Motor / cobertura ejecutable | Automatización |
|---|---|---|---|---|
| TC-SMK-001 | Smoke | Inicio de sesión correcto | Selenium: formulario, sesión autenticada y acceso a QA | IMPLEMENTED; requiere contraseña QA local |
| TC-SMK-002 | Smoke | Acceso al dashboard QA | Selenium: login y dashboard protegido | IMPLEMENTED; requiere contraseña QA local |
| TC-SMK-003 | Smoke | Consulta de productos | PHPUnit: acceso a espacios de trabajo según permisos | IMPLEMENTED |
| TC-SMK-004 | Smoke | Consulta de inventario | PHPUnit: inventario/reportes dentro de la sucursal | IMPLEMENTED |
| TC-SAN-001 | Sanity | Búsqueda de productos | Sin prueba específica de búsqueda | NOT RUN |
| TC-SAN-002 | Sanity | Cálculo automático de totales | Sin prueba específica de cálculo de totales | NOT RUN |
| TC-SAN-003 | Sanity | Validación de correo | Selenium: `type=email`, `validity.typeMismatch` y bloqueo del formulario | IMPLEMENTED; opción FAIL CONTROLADO |
| TC-REG-001 | Regression | Registro de productos | PHPUnit: creación de catálogo/producto y control de costo | IMPLEMENTED |
| TC-REG-002 | Regression | Entrada de inventario | PHPUnit: recepción, saldo, kardex, auditoría e idempotencia | IMPLEMENTED |
| TC-REG-003 | Regression | Salida de inventario | Sin prueba específica de salida de stock | NOT RUN |
| TC-REG-004 | Regression | Registro de ventas | PHPUnit: identificador único en recepción, venta y pago | IMPLEMENTED |
| TC-REG-005 | Regression | Permisos de usuarios | PHPUnit: rol de sucursal no se filtra a otra | IMPLEMENTED |
| TC-INT-001 | Integration | Ventas e inventario | PHPUnit: identificadores y operaciones enlazadas | IMPLEMENTED |
| TC-INT-002 | Integration | Productos y categorías | PHPUnit: catálogo y producto bajo restricciones de costo | IMPLEMENTED |
| TC-INT-003 | Integration | Ventas y reportes | Sin prueba específica que vincule venta con reporte | NOT RUN |
| TC-E2E-001 | End to End | Flujo completo de venta | Falta usuario operativo y caja aislada | BLOCKED |
| TC-E2E-002 | End to End | Flujo completo de inventario | Falta usuario de almacén y datos de recepción aislados | BLOCKED |
| TC-NF-001 | No funcional | Tiempo de respuesta | Locust: 1 usuario, 14 solicitudes; CSV original: 14 agregadas | IMPLEMENTED |
| TC-NF-002 | No funcional | Carga concurrente | Locust: 2 usuarios, 30 solicitudes agregadas | IMPLEMENTED |
| TC-NF-003 | No funcional | Estrés controlado | Locust: 4 usuarios, 60 solicitudes agregadas | IMPLEMENTED |
| TC-NF-004 | No funcional | Resistencia breve | Locust: 1 usuario, 20 solicitudes agregadas | IMPLEMENTED |
| TC-NF-005 | No funcional | Acceso anónimo a ruta protegida | Robot Framework/SeleniumLibrary: `/dashboard` redirige a `/login` | IMPLEMENTED |
| TC-NF-006 | No funcional | Restricciones por roles | PHPUnit: aislamiento de roles entre sucursales | IMPLEMENTED |

## Evidencia de ejecución disponible

- El CSV original [`locust-short_stats.csv`](../Codigo_Fuente_QA/qa_engine/performance/locust-short_stats.csv) contiene una fila agregada con **14 solicitudes y 0 fallos**. No son 15.
- Selenium verificó en Edge la validación HTML5 real del correo inválido. En modalidad FAIL CONTROLADO se conserva `FAIL CONTROLADO` como etiqueta, el mensaje del navegador, el resultado esperado/obtenido y el paso deliberadamente invertido. Este caso debe ejecutarse desde el formulario web para asociarlo a una ejecución y sus reportes finales.
- Robot Framework verificó la redirección real de `/dashboard` al acceso.
- Los perfiles Locust emiten CSV original y el runner valida su contador agregado contra el límite del caso.
- Las pruebas PHPUnit dirigidas usan `phpunit.xml` con SQLite en memoria; no apuntan a las bases DEMO ni empresariales.

## Límites

Los casos `BLOCKED` y `NOT RUN` no se convierten en aprobados por estar registrados. Los dos E2E requieren datos y roles aislados. El login Selenium requiere `QA_E2E_PASSWORD` ingresada localmente por prompt oculto; no se incluye contraseña en el catálogo, seeders, capturas ni reportes.
