# ADR 0002: aislamiento y ejecución verificable del QA Center

- Estado: aceptada
- Fecha: 2026-11-24

## Contexto

El QA Center registra casos y ejecuciones, pero un catálogo no demuestra que exista un ejecutor real. La interfaz debe mantener la autenticación y permisos empresariales, y los motores de prueba no deben conectarse a las bases DEMO o principal.

## Decisión

- El servidor y el worker de Laravel se ejecutan como procesos locales separados y se configuran explícitamente con `database/qa.sqlite`.
- El job falla cerrado si la conexión efectiva no apunta al archivo SQLite QA esperado.
- PHPUnit se ejecuta mediante `phpunit.xml` y SQLite en memoria; no hereda la base persistente del worker.
- El runner mantiene estados diferentes para `IMPLEMENTED`, `BLOCKED` y `NOT RUN`; el resultado de una ejecución se registra por separado.
- Selenium usa Edge y el formulario real con CSRF, cookie y sesión. La contraseña del usuario QA solo se obtiene mediante entorno local o prompt oculto; no se crea un administrador con una clave fija.
- FAIL CONTROLADO solo puede seleccionar el caso de validación HTML5 y falla por una expectativa de demostración invertida; no se clasifica como defecto del ERP.
- Locust reserva el máximo agregado de solicitudes para evitar sobrepasos con usuarios concurrentes y comprueba el CSV original. La línea base histórica registra 14 solicitudes agregadas.
- Los informes PDF y HTML se generan desde el mismo JSON de resultados, incorporan datos de caso, pasos y capturas disponibles y se guardan con SHA-256.
- El envío QA utiliza SMTP local Mailpit. No se considera `log` como entrega ni se configura SMTP externo sin autorización.
- El arranque QA verifica y reutiliza Mailpit si ambos puertos responden desde el proceso esperado; si falta el ejecutable, invoca el instalador oficial con SHA-256 fijado. El servidor y worker se reutilizan solo cuando coinciden PID, ruta y argumentos registrados; ante procesos desconocidos, el script falla sin detenerlos ni duplicarlos.
- El servidor de pruebas se enlaza a `127.0.0.1`; los datos de Mailpit y las evidencias permanecen en almacenamiento privado local.
- El arranque del QA Center no ejecuta migraciones, seeders ni comandos de creación de administrador.

## Consecuencias

- La ejecución real desde la interfaz requiere una cuenta QA autorizada y `QA_E2E_PASSWORD` configurada localmente.
- Los flujos de venta y almacén permanecen bloqueados mientras no existan usuarios operativos y datos aislados adecuados.
- Mailpit prueba correo local; no demuestra recepción externa.
- Repetir el arranque verifica los procesos existentes y solo inicia los que faltan. Una contraseña E2E no se aplica a un worker ya activo; requiere detenerlo selectivamente tras comprobar en modo de solo lectura que la cola esté vacía, e ingresar la contraseña en el prompt oculto local. Servidor y Mailpit se conservan.
- La IA y publicación GitHub requieren configuración y autorización propias.
- Se prioriza reportar evidencia incompleta como pendiente/bloqueada en vez de presentar resultados simulados.
