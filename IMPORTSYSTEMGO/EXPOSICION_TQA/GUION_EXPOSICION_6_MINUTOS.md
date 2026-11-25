# Guion de exposición - 6 minutos

## 00:00-00:40 - Presentación (pestaña 2, Dashboard QA)

**Mostrar:** `IMPORTSYSTEMGO QA CENTER`, el catálogo de 23 casos y las suites activas.

**Decir:** “Buenos días. Soy Danner Leonel Ruiz Condori, estudiante de Ingeniería de Sistemas en UNIFRANZ. Mi proyecto empresarial es IMPORTSYSTEMGO y hoy presentaré su módulo de automatización, IMPORTSYSTEMGO QA CENTER. Su objetivo es centralizar pruebas funcionales y no funcionales, ejecutar motores reales, conservar evidencias y generar reportes trazables. Todo lo que mostraré trabaja en un ambiente QA local e independiente de las bases empresariales.”

**Si tarda:** pasar a la pestaña 4, que contiene una ejecución real de respaldo.

## 00:40-01:15 - Clasificación y trazabilidad (pestaña 2)

**Mostrar:** tarjetas Casos, Aprobados, Fallidos, Bloqueados y No ejecutados; luego “Nueva ejecución”.

**Decir:** “El centro clasifica Smoke, Sanity, Regression, Integration, End to End y pruebas no funcionales. Un caso sin automatización no se marca como aprobado: permanece NOT RUN o BLOCKED. Las métricas se calculan desde ejecuciones registradas, no desde valores simulados.”

**Acción:** pulsar **Nueva ejecución** y cambiar a la pestaña 3.

## 01:15-02:30 - Prueba funcional (pestaña 3 y pestaña 4)

**Mostrar:** tipo Funcional, suite Smoke, correo de demostración y los casos implementados. Si el worker fue iniciado con credencial local, seleccionar los casos Smoke implementados y pulsar **Ejecutar pruebas**.

**Decir:** “Esta suite valida acceso, catálogo e inventario. Selenium utiliza un navegador real para el acceso, mientras PHPUnit ejecuta reglas aisladas con SQLite en memoria. El servidor mantiene CSRF, sesión y permisos; no desactivo controles de seguridad para hacer pasar las pruebas.”

**Resultado esperado:** estado `COMPLETED`, progreso 100 %, casos `PASS` y pasos ejecutados. La ejecución de respaldo verificada en la pestaña 4 contiene dos PASS: 11 y 6 aserciones, respectivamente.

**Si tarda o la credencial no fue configurada:** no iniciar una ejecución nueva. Cambiar a la pestaña 4 y decir: “Para respetar el tiempo, muestro una ejecución real previa del mismo motor; el acceso Selenium queda bloqueado cuando no recibe la credencial local.”

## 02:30-03:15 - FAIL controlado, retesting y regresión (pestaña 5)

**Mostrar:** `TC-SAN-003`, estado FAIL, resultado obtenido y captura.

**Decir:** “Este es un FAIL controlado. Edge comprobó realmente que un correo inválido produce `typeMismatch=true` y que el formulario no es válido. Después invertí deliberadamente la expectativa para demostrar que el sistema detecta una diferencia. No es un defecto del ERP. Retesting significa repetir una prueba después de corregir un defecto; Regression Testing significa ejecutar pruebas existentes para comprobar que el cambio no dañó funciones previamente operativas. En esta sesión la regresión completa aprobó 120 pruebas y 800 aserciones, con seis omisiones exclusivas de MySQL.”

## 03:15-04:00 - Rendimiento (pestaña 9)

**Mostrar:** reporte no funcional y métricas Locust.

**Decir:** “Locust realizó una carga corta exclusivamente contra el entorno QA local: un usuario, 14 solicitudes, cero fallos, promedio de 447 milisegundos y percentil 95 de 520 milisegundos, durante 16,5 segundos. Esta medición sirve para observar comportamiento y detectar regresiones de rendimiento; no certifica la capacidad productiva completa.”

## 04:00-05:10 - Reportes y correo (pestañas 6, 7 y 8)

**Mostrar:** PDF y HTML de la misma ejecución; identificador, duración, validaciones, esperado, obtenido y estados. Luego Mailpit.

**Decir:** “El PDF y el HTML se generan automáticamente desde el mismo JSON, por eso conservan los mismos resultados. Cada archivo tiene SHA-256 y el correo rechaza adjuntos alterados. Mailpit verifica SMTP local en los puertos 1025 y 8025. Si la bandeja muestra un mensaje, abro el correo y sus dos adjuntos. Si está vacía, indico con transparencia que SMTP está disponible, pero el envío integral desde la interfaz aún no fue ejecutado.”

## 05:10-05:40 - Inteligencia artificial (pestaña 10)

**Mostrar:** estado del proveedor.

**Decir:** “El asistente está diseñado para proponer escenarios positivos, negativos, límites y Gherkin a partir de un requerimiento. En este equipo Ollama no está configurado, por lo que el sistema registra el intento como fallido y no presenta una respuesta estática como si fuera inteligencia artificial. La IA ayuda a diseñar pruebas, pero nunca determina el resultado de una ejecución.”

## 05:40-06:00 - Cierre

**Decir:** “Mi aporte de ingeniería es integrar trazabilidad, aislamiento, automatización, evidencias, rendimiento y reportes en un flujo reproducible. El QA Center no reemplaza el criterio humano: hace visible qué se ejecutó, qué pasó, qué falló y qué sigue bloqueado. Muchas gracias.”
