# Guion de emergencia - 5 minutos

- **00:00-00:35, pestaña 2:** presentar IMPORTSYSTEMGO QA CENTER y su aislamiento en SQLite QA.
- **00:35-01:05, pestaña 2:** mostrar 23 casos y explicar PASS, FAIL, BLOCKED y NOT RUN.
- **01:05-02:05, pestaña 4:** mostrar los dos PASS funcionales reales. Decir que PHPUnit usa `phpunit.xml` y SQLite en memoria.
- **02:05-02:45, pestaña 5:** mostrar el FAIL controlado de Edge: `typeMismatch=true`, formulario inválido y captura. Aclarar que no es un defecto del ERP.
- **02:45-03:25, pestaña 9:** mostrar Locust: 14 solicitudes, cero fallos, promedio 447 ms, p95 520 ms, 16,5 s. Aclarar que no certifica producción.
- **03:25-04:25, pestañas 6 y 7:** comparar PDF y HTML generados desde el mismo JSON; señalar esperado, obtenido y evidencia.
- **04:25-04:45, pestaña 8:** mostrar Mailpit. Si está vacío, decir que SMTP local está disponible pero el envío E2E sigue pendiente.
- **04:45-05:00:** cerrar: “El aporte es un flujo reproducible que no inventa resultados y protege las bases empresariales.”
