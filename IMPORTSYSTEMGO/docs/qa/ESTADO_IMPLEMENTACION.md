# Estado de implementación — IMPORTSYSTEMGO QA CENTER

Fecha: 2026-11-24

## Estado general

| Componente | Estado actual |
|---|---|
| Auditoría previa | IMPLEMENTADO Y VERIFICADO |
| Migración de persistencia QA | IMPLEMENTADO SIN VERIFICAR |
| Modelos Eloquent QA | IMPLEMENTADO SIN VERIFICAR |
| Catálogo de 23 casos | IMPLEMENTADO SIN VERIFICAR |
| Permisos QA | IMPLEMENTADO SIN VERIFICAR |
| Rutas protegidas QA | IMPLEMENTADO SIN VERIFICAR |
| Dashboard QA | IMPLEMENTADO SIN VERIFICAR |
| Nueva ejecución | IMPLEMENTADO SIN VERIFICAR |
| Historial y progreso | IMPLEMENTADO SIN VERIFICAR |
| Motor Python runner | IMPLEMENTADO SIN VERIFICAR |
| Selenium/Edge | INSTALADO SIN VERIFICAR |
| Robot Framework | IMPLEMENTADO SIN VERIFICAR |
| Locust | INSTALADO SIN VERIFICAR |
| PDF/HTML | IMPLEMENTADO SIN VERIFICAR |
| Correo SMTP | PENDIENTE DE CONFIGURACIÓN |
| IA Ollama/API | PENDIENTE DE CONFIGURACIÓN |
| Base persistente QA | PENDIENTE DE CREAR/AUTORIZAR |

## Evidencia verificada 2026-11-24

- PHPUnit QA: 6 pruebas y 21 aserciones aprobadas con SQLite `:memory:`.
- Laravel Pint aprobado.
- Vistas Blade compiladas.
- 10 rutas QA registradas.
- Entorno `.venv-qa` creado con las dependencias declaradas.
- Robot dry run: 2 pruebas, 2 aprobadas.
- Runner Python: ejecución real `demo-validation`, 5 resultados producidos.
- PDF generado y hash confirmado: `de67e88b45e13f270797851e6f36f67d83defbbdb6d2b5f3d4fb4b7d121f51ee`.
- HTML generado y hash confirmado: `3380197380e9299274e872230ffb587483099a6373b6a46ae63ad3fe4e8ceaec`.
- Captura real Edge: `storage/app/private/qa/login-real.png`.

## Evidencia adicional verificada

- Suite completa SQLite: 116 pruebas, 784 aserciones, cero fallos y seis omisiones exclusivas de MySQL.
- Robot Edge: dos pruebas aprobadas y reportes reales HTML/XML.
- Locust breve: 14 solicitudes agregadas según el CSV final, cero fallos, mediana agregada 470 ms y promedio agregado 536.229 ms.
- PDF académico generado: `ENTREGA_PROYECTO_INTEGRADOR_TQA/Informe_Proyecto_Integrador_TQA_IMPORTSYSTEMGO.pdf`.
- Manual PDF generado: `ENTREGA_PROYECTO_INTEGRADOR_TQA/Manual_Usuario_QA_Center.pdf`.
- ZIP generado: `Entrega_Proyecto_Integrador_TQA_IMPORTSYSTEMGO.zip`, 58 entradas, cero archivos prohibidos.
- ZIP SHA-256: `0F1BCF25EE5BF560E63D0639BA60001CB2211897F4D0C8950C2AF2A498A2BEA3`.
- Base SQLite QA creada y migrada con las 18 migraciones disponibles.
- `/login` respondió 200, `/up` respondió 200 y `/qa` respondió 302 al login sin autenticación.
- El login con autenticador Laravel devolvió verdadero para la cuenta ficticia local.

## Fallos reales corregidos

- Servidor iniciado incorrectamente con `server.php` reemplazado por `artisan serve`.
- Falta de SQLite CLI corregida cargando PDO SQLite explícitamente.
- Archivo Robot sin pruebas completado con dos smoke cases.
- Pruebas QA corregidas para asignar rol global mediante `global_user_roles`.
- Cola QA aislada corregida con `Queue::fake()` en pruebas.
- Módulo AI y correo separados de resultados PASS/FAIL.

## Hecho

- Se creó una migración versionada con suites, casos, ejecuciones, resultados, pasos, evidencias, reportes, correos y sesiones IA.
- Se añadieron modelos Eloquent independientes.
- Se registraron 7 suites y 23 casos propuestos.
- Se añadieron permisos globales QA al catálogo.
- Se añadieron rutas protegidas, vistas dashboard, catálogo, nueva ejecución, detalle e historial.
- Se añadió un entorno virtual `.venv-qa` con Selenium, Pytest, ReportLab, Pillow, Robot Framework, SeleniumLibrary, Locust y Jinja2.
- Se creó `qa_engine/runner.py` con límites, medición HTTP y ejecución de PHPUnit aislado.
- Se prepararon pruebas `.robot` y un generador PDF/HTML independiente.
- Se añadieron pruebas del propio QA Center.

## Próximas tareas ejecutables

1. Verificar migración, seeders y pruebas QA en SQLite.
2. Importar resultados Python a la persistencia Laravel.
3. Completar generación y registro automático de PDF/HTML.
4. Implementar correo con estados reales y adjuntos.
5. Implementar proveedor IA Ollama/API configurable.
6. Ejecutar Selenium real y Robot real.
7. Ejecutar Locust breve contra `/login` y `/up`.
8. Preparar scripts Windows, entrega académica y ZIP.
9. Preparar Git local sin publicación.

## Bloqueos externos reales

- MySQL QA persistente no ha sido creado ni autorizado.
- SMTP requiere credenciales o Mailpit local.
- Ollama/API requiere modelo o credencial cuando se configure.
- GitHub requiere autenticación específica para publicar.

Ningún estado PASS, FAIL, PDF, correo o IA debe marcarse como verificado sin evidencia obtenida por ejecución real.
