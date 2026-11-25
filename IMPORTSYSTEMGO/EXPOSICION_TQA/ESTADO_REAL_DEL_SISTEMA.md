# Estado real del sistema - 24 de noviembre de 2026

## Verificado

- Laravel QA responde en `127.0.0.1:8001`; `/up` y `/login` devolvieron HTTP 200.
- Mailpit respondió en `127.0.0.1:8025`; la bandeja tenía 0 mensajes durante la verificación.
- Base aislada `database/qa.sqlite`: 1 cuenta QA, 7 suites, 23 casos, 0 ejecuciones registradas y 0 jobs pendientes.
- PHPUnit completo: 120 pruebas, 800 aserciones, 0 fallos y 6 omisiones MySQL.
- QA Center enfocado: 10 pruebas, 37 aserciones y 0 fallos.
- Motor Python: 3 pruebas y 0 fallos.
- Vite: 119 módulos transformados.
- Smoke de respaldo: 2 casos PASS, con 11 y 6 aserciones.
- FAIL controlado: Edge verificó `typeMismatch=true`, `form.checkValidity()=false` y produjo captura.
- Locust: 14 solicitudes, 0 fallos, promedio 447 ms, p95 520 ms, duración 16,5 s.
- PDF y HTML reales generados para funcional, FAIL controlado y rendimiento.
- Robot Framework ya dispone de `output.xml`, `log.html` y `report.html`; la prueba protegida aprobó en la validación previa registrada.
- Restablecimiento seguro implementado: sólo admite `database/qa.sqlite`, exige contraseña fuerte, invalida sesiones y audita sin guardar el secreto.

## Pendiente o bloqueado

- El login por navegador y una ejecución completa iniciada desde la interfaz requieren que el usuario introduzca la contraseña local mediante el prompt oculto.
- La bandeja Mailpit sigue vacía; no se verificó todavía un correo E2E con PDF y HTML desde la interfaz.
- Ollama no está instalado/configurado; no se presenta respuesta de IA.
- La base QA no contiene sucursales, productos, clientes, proveedores, ventas ni pagos. No se inventaron esos indicadores ni se tocó `importsystemgo` o `importsystemgo_demo`.
- Los reportes de respaldo fueron ejecutados directamente por el mismo motor; no deben describirse como ejecuciones lanzadas desde la interfaz.
- No se ejecutaron migraciones, seeders ni escrituras en bases MySQL empresariales.
