# Guía de defensa — IMPORTSYSTEMGO QA CENTER

## Secuencia sugerida

### 1. Contexto y aislamiento

Explicar que QA CENTER es un módulo Laravel del ERP y que sus ejecuciones usan `database/qa.sqlite`. PHPUnit usa `phpunit.xml` con SQLite en memoria. Ninguna evidencia de esta demostración autoriza el uso de bases DEMO o empresariales.

### 2. Interfaz y catálogo

Mostrar el acceso, el dashboard, las siete suites y los 23 casos. Distinguir los estados de automatización:

- 17 `IMPLEMENTED`: hay un ejecutor asignado.
- 2 `BLOCKED`: faltan roles/datos de sucursal, caja o almacén aislados.
- 4 `NOT RUN`: no hay una prueba específica que valide el caso registrado.

No presentar los casos bloqueados o no ejecutados como aprobados.

### 3. Ejecución técnica

Mostrar el worker de Laravel separado del servidor. Explicar que PHPUnit, Selenium, Robot Framework y Locust ejecutan motores reales, y que resultados y CSV proceden de cada motor. El CSV original de Locust registra 14 solicitudes agregadas y cero fallos; no son 15.

### 4. Validación y fallo controlado

En Edge, el campo `type=email` rechaza `not-an-email` (`validity.typeMismatch=true`). `FAIL CONTROLADO` invierte deliberadamente una expectativa para demostrar que el runner registra un FAIL con captura, mensaje, paso y resultado esperado/obtenido. Aclarar que no es un defecto empresarial.

### 5. Informes y correo

Mostrar los botones PDF/HTML, hashes de integridad, pasos y evidencias asociados a una ejecución web final. Mailpit está disponible localmente en `http://127.0.0.1:8025`; solo afirmar que se recibió un correo después de comprobar el mensaje, destinatario y los dos adjuntos PDF/HTML.

### 6. IA y publicación

Ollama no tiene proveedor ni modelo verificados; no mostrar respuestas simuladas. El repositorio académico identificado es [Proyecto-Integrador-TQA](https://github.com/DannerCM/Proyecto-Integrador-TQA), pero el remoto y el push están pendientes de autorización.

## Preguntas frecuentes

**¿Por qué no se prueba en producción?**
Para preservar datos empresariales y mantener el aislamiento de QA.

**¿Qué significa `NOT RUN`?**
El caso existe en el catálogo, pero no hay una automatización específica ejecutable.

**¿Qué significa `BLOCKED`?**
La prueba requiere credenciales o datos operativos aislados aún no configurados.

**¿Qué mide el perfil Locust?**
Solicitudes GET de solo lectura contra el QA local, cantidad agregada, fallos y percentiles. Los perfiles breves no son certificación de capacidad.

**¿La IA determina el resultado PASS/FAIL?**
No. El motor de IA propuesto produce análisis para revisión humana; no se habilita ni se declara una respuesta real hasta configurar y verificar un proveedor.

## Estado a declarar con honestidad

Las verificaciones automatizadas de código y motores no sustituyen la ejecución completa desde navegador. Si faltan credenciales, recepción SMTP, respuesta de IA o autorización GitHub, indicarlo como pendiente y no mostrar capturas o informes de prueba como evidencia final.
