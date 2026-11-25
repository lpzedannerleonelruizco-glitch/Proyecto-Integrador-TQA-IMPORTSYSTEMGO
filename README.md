# IMPORTSYSTEMGO QA Center

Entrega académica de Testing y Quality Assurance. QA Center reúne casos funcionales y de rendimiento, ejecuta pruebas automatizadas y conserva resultados y evidencias.

El [proyecto completo IMPORTSYSTEMGO](IMPORTSYSTEMGO/) incluye el ERP Laravel, el QA Center, los motores Python, pruebas, configuración de ejemplo y migraciones versionadas. Consulta su [guía de instalación](IMPORTSYSTEMGO/README.md) para preparar un entorno local.

## Contenido

- [Catálogo de casos](Catalogo_Casos_Prueba.md) y [matriz de trazabilidad](Matriz_Trazabilidad.md).
- [Código del módulo QA](Codigo_Fuente_QA/) y [manual de instalación](Manual_Instalacion.md).
- Reportes en PDF, HTML y JSON de [pruebas funcionales](Reportes/Funcional/), [FAIL controlado](Reportes/Fail-Controlado/) y [rendimiento](Reportes/Rendimiento/).
- [Guía de defensa](GUIA_DEFENSA_PROYECTO_TQA.md).
- [Informe y manual de usuario originales](Versiones_anteriores/). Son versiones archivadas. Para el puerto, el correo y las cifras vigentes, consulta el manual de instalación y los reportes enlazados arriba.

La carpeta `Codigo_Fuente_QA` conserva la selección de código de la entrega académica. Para ejecutar Laravel, usa `IMPORTSYSTEMGO/` como raíz de la aplicación. Los scripts QA arrancan un servidor local, un worker y Mailpit.

Los reportes funcionales muestran dos casos aprobados. El FAIL controlado documenta la respuesta de Edge ante un correo inválido y usa una expectativa invertida a propósito. El reporte de Locust registra una carga breve de 14 solicitudes, con cero fallos. El asistente en modo local muestra respuestas preparadas sobre inicio de sesión, formato de correo y stock insuficiente.
