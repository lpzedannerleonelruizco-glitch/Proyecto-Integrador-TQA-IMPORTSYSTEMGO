# Manual de instalación — IMPORTSYSTEMGO QA CENTER

## Requisitos

- Windows PowerShell.
- PHP 8.4 con las extensiones `pdo_sqlite` y `sqlite3`.
- Composer y Node.js/npm para compilar dependencias web.
- Python 3.13 y Microsoft Edge.
- La base aislada existente `database/qa.sqlite`.

## Preparación

Desde la raíz del proyecto:

```powershell
.\scripts\qa\instalar_dependencias.ps1
.\scripts\qa\verificar_entorno.ps1
```

La aplicación obtiene su `APP_KEY` local desde el entorno o `.env`; nunca lo copie a documentación ni al repositorio. El script de inicio no migra ni siembra datos y falla si faltan las tablas QA. No ejecute `preparar_datos_qa.ps1` contra una base existente sin autorización específica y respaldo verificado.

## SMTP local

El arranque del QA Center inicia Mailpit automáticamente. Si no está instalado, ejecuta el instalador de la versión oficial fijada y verifica el archivo mediante SHA-256:

```powershell
.\scripts\qa\instalar_mailpit.ps1
```

- SMTP: `127.0.0.1:1025`
- Bandeja: `http://127.0.0.1:8025`

Mailpit se enlaza únicamente a `127.0.0.1`. `iniciar_mailpit.ps1` también puede ejecutarse por separado: valida que SMTP y UI pertenezcan a la instancia esperada, verifica ambos endpoints y reutiliza el proceso activo en vez de duplicarlo. Si algún puerto está ocupado por un proceso no verificado, se detiene con error sin cerrarlo. No configure relay o credenciales externas para esta demostración.

## Iniciar QA Center

```powershell
.\scripts\qa\iniciar_qa.ps1 -Port 8001
```

URL local: `http://127.0.0.1:8001/qa`. El servidor HTTP y el worker de Laravel se ejecutan como procesos independientes. Ambos usan exclusivamente `database/qa.sqlite`, sesión en archivos y caché en archivos.

En este equipo, no inicie QA con `php artisan serve` en `:8000`: el proceso HTTP hijo no carga las DLL SQLite y la caché de base de datos usada por el limitador de solicitudes puede fallar con `could not find driver` antes de entrar al controlador de login. El script QA inicia PHP con SQLite habilitado y configura caché/sesión en archivos en `:8001`. Mantenga CSRF y el limitador activos; no reutilice cookies entre puertos.

Para habilitar Selenium de acceso, detenga los procesos QA registrados y vuelva a iniciarlos con contraseña ingresada de forma oculta desde una terminal local:

```powershell
.\scripts\qa\detener_qa_seguro.ps1 -WorkerOnly
.\scripts\qa\iniciar_qa.ps1 -Port 8001 -PromptForE2EPassword
```

`-WorkerOnly` comprueba en modo de solo lectura que la cola QA esté vacía y detiene únicamente el worker validado; el servidor HTTP y Mailpit permanecen activos. Si hay jobs en cola, no detiene el worker. No escriba la contraseña en comandos, chat, logs o archivos versionados. La cuenta existente no es creada ni reiniciada por los seeders.

## Pruebas y estado verificable

```powershell
.\scripts\qa\ejecutar_pruebas.ps1
```

El catálogo contiene 23 casos. La automatización declarada y el resultado de cada ejecución son estados separados: los casos sin implementación quedan `NOT RUN`; los que necesitan credenciales o datos de negocio aislados quedan `BLOCKED`. Los flujos de venta y almacén no se reportan como PASS mientras falten esas precondiciones.

El CSV original de Locust registra **14 solicitudes agregadas**, no 15: `qa_engine/performance/locust-short_stats.csv`. Los perfiles nuevos validan el total agregado indicado en el manifiesto.

## Alcance no configurado

- `QA_E2E_PASSWORD` no se incluye en el repositorio y debe introducirse localmente.
- SMTP externo, Ollama y publicación en GitHub requieren configuración y autorización propias.
- El repositorio académico identificado es [Proyecto-Integrador-TQA](https://github.com/DannerCM/Proyecto-Integrador-TQA); la publicación de esta entrega está pendiente.

## Detener procesos QA

Use solo el script de cierre que valida PID, ejecutable y comando de los servicios QA:

```powershell
.\scripts\qa\detener_qa_seguro.ps1
```

Sin parámetros detiene worker y servidor QA; Mailpit permanece activo. Use `-WorkerOnly` para conservar servidor y correo durante una renovación local de `QA_E2E_PASSWORD`. No use scripts de detención global ni finalice procesos por nombre.
