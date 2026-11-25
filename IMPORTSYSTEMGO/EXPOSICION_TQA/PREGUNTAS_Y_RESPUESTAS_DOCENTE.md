# Preguntas y respuestas para el docente

1. **¿Qué es Testing Quality Assurance?** Es el conjunto de prácticas para prevenir defectos y demostrar, con evidencia, que el proceso y el producto cumplen criterios de calidad.
2. **¿Qué problema resuelve tu QA CENTER?** Centraliza casos, ejecución, evidencias, estados, reportes y correo sin mezclar el ambiente QA con las bases empresariales.
3. **¿Qué diferencia existe entre QA y Testing?** QA mejora y controla el proceso completo; Testing ejecuta comprobaciones concretas sobre el producto.
4. **¿Qué es Selenium WebDriver?** Es una API que controla un navegador real. Aquí usa Edge para validar el formulario y el flujo de sesión.
5. **¿Qué es Robot Framework?** Es un framework por palabras clave; aquí valida rutas web y genera `output.xml`, `log.html` y `report.html`.
6. **¿Qué es PHPUnit?** Es el framework de pruebas de PHP usado para verificar rutas, permisos, servicios e invariantes de Laravel.
7. **¿Qué es Locust?** Es una herramienta Python de carga; aquí produce CSV con solicitudes, errores y tiempos de respuesta.
8. **¿Funcionales y no funcionales?** Las funcionales validan qué hace el sistema; las no funcionales miden atributos como rendimiento o seguridad.
9. **¿Qué es un Assert?** Es una comparación entre el resultado esperado y el obtenido; si no coincide, la prueba falla.
10. **¿Qué es Smoke Testing?** Una selección corta de funciones críticas para saber si el sistema está estable para continuar.
11. **¿Qué es Sanity Testing?** Una comprobación enfocada de una función o cambio específico.
12. **¿Qué es Regression Testing?** Volver a ejecutar pruebas existentes para comprobar que un cambio no dañó funciones operativas.
13. **¿Qué es Retesting?** Repetir la prueba relacionada con un defecto después de aplicar su corrección.
14. **¿Qué es End-to-End?** Validar un flujo completo a través de sus capas, por ejemplo acceso, venta, cobro e inventario.
15. **¿Por qué aparece FAIL?** Porque una aserción real no coincide. En la demostración, la expectativa se invierte deliberadamente y se etiqueta como FAIL controlado.
16. **¿Cómo se generan los reportes?** El runner guarda un JSON y un único generador produce PDF y HTML con los mismos datos y hashes.
17. **¿Cómo se envían los correos?** Laravel usa SMTP local en `127.0.0.1:1025`; Mailpit permite inspeccionarlos en `:8025`. El E2E sigue pendiente mientras la bandeja esté vacía.
18. **¿Cómo interviene la IA?** Puede proponer escenarios, límites y Gherkin, pero no decide PASS o FAIL. Ollama no está configurado en este equipo.
19. **¿Cómo proteges la base empresarial?** El runner usa SQLite QA o SQLite en memoria; las pruebas verifican la conexión antes de `RefreshDatabase` y no aceptan la principal.
20. **¿Cuál es tu aporte de ingeniería?** Integrar aislamiento, trazabilidad, automatización, evidencia, rendimiento y reportes reproducibles sin inventar resultados.
