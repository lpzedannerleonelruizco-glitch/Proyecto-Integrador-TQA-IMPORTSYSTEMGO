# Matriz de cumplimiento — IMPORTSYSTEMGO QA CENTER

Actualizada: 2026-11-24. `IMPLEMENTADO` describe código existente; solo `VERIFICADO` indica evidencia ejecutada. La trazabilidad detallada está en [Matriz_Trazabilidad.md](Matriz_Trazabilidad.md).

| Requisito | Estado | Evidencia actual |
|---|---|---|
| Catálogo de 23 casos | 17 `IMPLEMENTED`, 2 `BLOCKED`, 4 `NOT RUN` | La clasificación corresponde a la disponibilidad del ejecutor y sus precondiciones; no equivale a resultados PASS. |
| Formulario y selección de ejecución | Código implementado; recorrido autenticado pendiente | Se inspeccionó `/login` en el navegador; falta una sesión QA válida para entrar al dashboard y enviar una ejecución desde la web. |
| Login QA | Pendiente por contraseña local | El navegador confirmó `POST /login`, campo CSRF, `type=email`, correo QA literal válido y ausencia de enlace `mailto`. No se envió el formulario. |
| Cola independiente e inicio idempotente | Verificado | Reejecutar el script reutilizó servidor, worker y Mailpit activos sin duplicarlos; el job comprueba `database/qa.sqlite`. No se ejecutaron migraciones. |
| PHPUnit | Verificado, alcance dirigido | 8 pruebas y 31 aserciones, con SQLite en memoria. |
| Selenium / Edge | Validación HTML5 real verificada; login E2E pendiente | `TC-SAN-003` genera FAIL CONTROLADO por expectativa deliberadamente invertida; falta registrar una ejecución desde la UI. |
| Robot Framework | Verificado | La ruta `/dashboard` redirige a `/login`; captura disponible en los artefactos de la suite. |
| Locust | Verificado | CSV de referencia: 14 solicitudes agregadas; perfiles automatizados 14, 30, 60 y 20 aprobados sin fallos. |
| PDF y HTML de ejecuciones finales | Pendiente | El generador y sus pruebas con fixtures están listos; faltan informes derivados de ejecuciones web autenticadas y revisión visual final. |
| SMTP / Mailpit | Activo; correo E2E pendiente | SMTP saluda `220`, UI responde HTTP 200 y la bandeja tiene 0 mensajes. Falta verificar destinatario, asunto, cuerpo y ambos adjuntos en una ejecución QA real. |
| IA | Pendiente | Ollama y un proveedor no están configurados; no hay respuesta real que documentar. |
| GitHub | Repositorio identificado; publicación pendiente | URL indicada en [Enlace_GitHub.txt](Enlace_GitHub.txt); no se configuró el remoto ni se realizó push. |
| Informe y manual PDF | No finales | Los PDF existentes son anteriores y no se consideran entregables finales con evidencia suficiente. |
| ZIP reproducible | Pendiente | No se regeneró mientras falten las evidencias finales, la sincronización de fuente y la revisión de secretos. |

No presentar los estados pendientes como cumplidos. No usar `MAIL_MAILER=log` como prueba de entrega, ni ejecutar migraciones o seeders contra bases empresariales o DEMO.
