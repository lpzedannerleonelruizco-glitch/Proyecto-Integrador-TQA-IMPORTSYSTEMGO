# Matriz de trazabilidad — IMPORTSYSTEMGO QA CENTER

Actualizada: 24-11-2026. `IMPLEMENTADO` describe código existente; `VERIFICADO` requiere evidencia ejecutada; `PENDIENTE` o `BLOQUEADO` indica que no debe presentarse como completado.

| Requisito | Artefactos principales | Estado actual | Evidencia / pendiente |
|---|---|---|---|
| Acceso con CSRF, cookie y sesión | Formulario `resources/views/auth/login.blade.php`, `app/Http/Controllers/SessionController.php` | PARCIAL | GET `/login` responde 200; Selenium valida el campo HTML5. Login con la contraseña real QA aún bloqueado por credencial local no configurada. |
| Catálogo y selección por tipo/suite/caso | `QaCenterSeeder`, `QaCenterController`, `resources/views/qa/create.blade.php` | IMPLEMENTADO; PRUEBAS DIRIGIDAS | Catálogo de 23 casos, selección validada en servidor y casos fuera de tipo/suite rechazados por PHPUnit. |
| Cola independiente e aislamiento QA | `RunQaExecution`, `scripts/qa/iniciar_qa.ps1` | ARRANQUE IDEMPOTENTE VERIFICADO | Servidor `:8001`, worker y Mailpit reutilizados sin cambiar sus PID; el job exige `database/qa.sqlite`. El script solo inicia servicios faltantes y rechaza listeners desconocidos. |
| Runner de motores | `qa_engine/runner.py`, `qa_engine/performance/locustfile.py`, suite Robot | PARCIALMENTE VERIFICADO | PHPUnit dirigido aprobado; Robot protegido aprobado; perfiles Locust con CSV agregado. El login UI requiere secreto local. |
| Casos `BLOCKED` / `NOT RUN` | `config/qa.php`, runner y vistas | IMPLEMENTADO | 17 `IMPLEMENTED`, 2 `BLOCKED`, 4 `NOT RUN`; pruebas Python comprueban que ambos estados no se confunden. |
| FAIL CONTROLADO | Selenium `TC-SAN-003`, formulario de login | VALIDACIÓN REAL; EJECUCIÓN WEB PENDIENTE | Edge confirma `typeMismatch=true` y envío bloqueado; el runner devuelve FAIL con etiqueta explícita. Falta persistirlo desde la interfaz con reporte final. |
| Informes PDF y HTML | `qa_engine/generate_reports.py`, `QaReportMail` | GENERADOR Y PRUEBAS DIRIGIDAS | La prueba temporal valida campos y evidencia; faltan informes finales ligados a una ejecución web real y revisión visual completa. |
| Mailpit local | `scripts/qa/instalar_mailpit.ps1`, `scripts/qa/iniciar_mailpit.ps1` | ACTIVO; ARRANQUE AUTOMÁTICO | v1.34.0 instalado desde paquete oficial verificado por SHA-256; SMTP `:1025` devuelve `220`, UI `:8025` HTTP 200. Arranque repetido no duplica el proceso. Bandeja con 0 mensajes; correo E2E pendiente. |
| IA local | `config/qa.php`, servicio QA de IA | PENDIENTE | No hay Ollama configurado ni respuesta real verificada. |
| Publicación académica | Repositorio `Proyecto-Integrador-TQA` | PENDIENTE DE AUTORIZACIÓN | Repositorio identificado; no se configuró remoto ni se publicó contenido. |
| Informe y manual académicos | `Informe_Proyecto_Integrador_TQA_IMPORTSYSTEMGO.pdf`, `Manual_Usuario_QA_Center.pdf` | PENDIENTE DE ACTUALIZACIÓN | Los PDF existentes no se consideran entregables finales; requieren capturas autenticadas y resultados web finales. |
| ZIP reproducible y libre de secretos | `Entrega_Proyecto_Integrador_TQA_IMPORTSYSTEMGO.zip` | PENDIENTE | No regenerar hasta sincronizar fuente, evidencias, reportes y revisar su contenido por secretos. |

## Evidencia técnica ejecutada

- PHPUnit focalizado: 8 pruebas, 31 aserciones.
- Python: 3 pruebas de runner, clasificación e informes aprobadas.
- Robot Framework: ruta protegida verificada por SeleniumLibrary.
- Locust: CSV original `qa_engine/performance/locust-short_stats.csv`: 14 solicitudes agregadas y 0 fallos; el runner no transcribe 15.
- Selenium Edge: validación HTML5 real; no se completó el inicio de sesión porque la contraseña no está disponible en el entorno del agente.
- Mailpit: SMTP/UI saludables y proceso único; la bandeja tiene 0 mensajes, falta el correo QA de extremo a extremo.

No usar `MAIL_MAILER=log` como evidencia de recepción. No ejecutar migraciones ni seeders sobre bases empresariales o DEMO.
