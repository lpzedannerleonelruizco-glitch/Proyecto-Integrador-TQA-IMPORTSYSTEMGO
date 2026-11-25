# Checklist antes de exponer

- [ ] Ejecutar `powershell -ExecutionPolicy Bypass -File .\INICIAR_EXPOSICION_QA.ps1 -ConfigurarCredencial` en una terminal local.
- [ ] Ingresar y repetir la contraseña sólo en el prompt oculto. No pegarla en chat ni mostrarla en pantalla.
- [ ] Confirmar HTTP 200 en `http://127.0.0.1:8001/up` y Mailpit en `http://127.0.0.1:8025`.
- [ ] Iniciar sesión como `qa.admin@example.test`.
- [ ] Verificar que `/qa` muestre el QA Center.
- [ ] Si hay tiempo, ejecutar Smoke desde la interfaz y comprobar `COMPLETED`.
- [ ] Abrir el PDF y HTML funcionales.
- [ ] Abrir el FAIL controlado y su captura.
- [ ] Abrir el reporte Locust.
- [ ] Comprobar Mailpit. No afirmar envío si la bandeja continúa vacía.
- [ ] Mantener cerrados archivos `.env`, terminales con variables y cualquier gestor de secretos.
- [ ] Usar el guion de 6 minutos; tener el de 5 minutos como respaldo.
