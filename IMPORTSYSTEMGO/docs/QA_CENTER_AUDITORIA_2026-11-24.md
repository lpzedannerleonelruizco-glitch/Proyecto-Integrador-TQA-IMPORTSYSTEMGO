# Auditoría inicial — IMPORTSYSTEMGO QA CENTER

Fecha: 2026-11-24

## Resultado ejecutivo

IMPORTSYSTEMGO tiene cobertura Laravel amplia, pero todavía no contiene un módulo autónomo denominado IMPORTSYSTEMGO QA CENTER ni un marco integrado de Selenium, Robot Framework, JMeter/K6 y reportes académicos.

La línea base de calidad se verificó sin escribir en bases persistentes:

| Comprobación | Resultado |
|---|---|
| PHPUnit SQLite `:memory:` | 110 pruebas, 763 aserciones, 0 fallos, 6 omitidas |
| Laravel Pint | Aprobado |
| Vite | Aprobado; 119 módulos transformados |
| Preparación principal | Solo lectura; 20 tablas, 11 migraciones y 7 migraciones pendientes |
| Suite MySQL | No iniciada: no se restauró el servicio ni se utilizaron credenciales |
| DEMO | No consultada ni modificada |
| Base principal | No consultada ni modificada durante esta auditoría |

## Capacidades existentes

- Autenticación, sesiones activas, selección de sucursal y recuperación protegida.
- RBAC por sucursal y global, aislamiento territorial y revocación de accesos.
- Administración de organización, usuarios y roles.
- Clientes, ventas, caja, pagos, créditos, compras y reportes comerciales.
- Inventario preparado con catálogo, recepciones, transferencias, ajustes y reportes.
- Respaldos ZIP con integridad y alcance territorial.
- Pronóstico de demanda DEMO, métricas reales, simulador de solo lectura y decisiones humanas.
- Guardas en `Tests\TestCase::createApplication` para exigir SQLite `:memory:` o `importsystemgo_testing`.

## Cobertura QA observada

Existen 110 pruebas registradas en las suites Laravel, principalmente funcionales e integradas.

La cobertura principal ya incluye:

- autenticación y bloqueo por intentos;
- membresías, roles, revocación y alcance de sucursal;
- denegación entre sucursales y ciudades;
- operaciones de caja, idempotencia y segregación de funciones;
- inventario, recepciones, transferencias y ajustes;
- ML: autorización, territorio, decisiones humanas y lectura simulada;
- respaldos e integridad.

Las seis omisiones son exclusivas de capacidades que requieren MySQL. Se distribuyen en respaldo SQL, validación de esquema MySQL y triggers.

## Brechas principales

1. No existe catálogo central de casos de QA ni asociación entre requisito, caso automatizado y resultado.
2. No existe Selenium WebDriver implementado como suite de aceptación del sistema.
3. No existe Robot Framework ni estructura reproducible de pruebas funcionales externas.
4. No existe ejecución de rendimiento ni perfiles de carga configurados.
5. No existe un tablero QA con tendencias, defectos, trazabilidad y evidencia.
6. La visualización autenticada real no está cubierta de forma estable por pruebas de navegador.
7. Los resultados académicos no tienen todavía formato PDF/HTML generado por un flujo oficial del proyecto.
8. Falta evidencia MySQL actual; la última evidencia completa aprobada corresponde al 24-10-2026.

## Riesgos bloqueantes

- El servidor MySQL no está disponible para reanudar pruebas MySQL o inspeccionar DEMO.
- El despliegue de inventario ampliado en DEMO y la sincronización de permisos requieren autorización concreta y respaldo previo.
- Las migraciones posteriores no deben aplicarse a la base principal.
- Las credenciales DEMO deben conservarse sin rotación.
- No se debe emitir facturación real ni crear recursos externos sin autorización separada.

## Plan QA por fases

### Fase 1 — Modelo de trazabilidad

Crear el catálogo maestro de requisitos, riesgos, casos y evidencia.

Criterios de aceptación:

- cada caso tiene identificador, requisito, módulo, precondición, datos, resultado y riesgo;
- cada resultado conserva fecha, entorno, versión y archivo de evidencia;
- los casos críticos incluyen permitido, denegado y aislamiento territorial.

### Fase 2 — QA web automatizado

Preparar un módulo complementario o aplicación auxiliar sin escribir datos reales.

Criterios de aceptación:

- ejecuta primero contra SQLite o MySQL de pruebas;
- identifica vulnerabilidades de selección de sucursal y autorización;
- usa localizadores mantenibles;
- genera capturas y reportes sin credenciales;
- las cuentas DEMO se usan solo con autorización y configuración aislada.

### Fase 3 — Pruebas no funcionales

Definir carga por escenarios representativos y baselines medibles.

Criterios de aceptación:

- login, dashboard, búsqueda, venta, cobro e inventario tienen umbrales;
- la carga ocurre solo contra un entorno desechable o DEMO autorizado;
- los resultados incluyen latencia, error, volumen y saturación;
- no se prueban datos fiscales reales.

### Fase 4 — Integración continua local

Crear tareas reproducibles para lint, PHPUnit, Vite y pruebas de navegador.

Criterios de aceptación:

- no admite una conexión distinta de SQLite `:memory:` o `importsystemgo_testing`;
- imprime identidad efectiva antes de ejecutar pruebas;
- conserva artefactos fuera del repositorio o con exclusión documentada;
- falla si se detecta un ambiente persistente.

### Fase 5 — Evidencia académica

Generar reportes HTML/PDF con:

- resumen ejecutivo;
- trazabilidad;
- resultados;
- defectos;
- capturas;
- entorno;
- limitaciones;
- conclusiones.

## Próxima operación aprobada

La siguiente operación segura es implementar la fase de trazabilidad y el esqueleto reproducible del QA Center sin acceso a bases persistentes. Cualquier migración, despliegue DEMO, inicio de MySQL, seeder o uso de credenciales requiere una autorización específica adicional.
