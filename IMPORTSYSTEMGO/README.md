# IMPORTSYSTEMGO

ERP Laravel con módulos de organización, usuarios y permisos, catálogo, inventario, ventas, caja, créditos, compras, reportes, pronóstico de demanda y QA Center. El módulo QA Center selecciona suites y casos, ejecuta motores de prueba en una cola independiente y conserva resultados e informes. Su base local es `database/qa.sqlite`.

## Inicio local en Windows

Requisitos: PHP 8.4 con `pdo_sqlite` y `sqlite3`, Composer, Node.js/npm, Python 3.13 y Microsoft Edge.

Ejecuta los comandos desde esta carpeta. Una copia nueva requiere un `.env` local basado en `.env.example`, una clave de aplicación propia y una base QA preparada antes de iniciar el servidor.

```powershell
Copy-Item .env.example .env
.\scripts\qa\instalar_dependencias.ps1
php artisan key:generate
.\scripts\qa\verificar_entorno.ps1
```

Para una instalación nueva, prepara exclusivamente una base SQLite local vacía. Estos comandos abortan si ya existe `database\qa.sqlite`.

```powershell
$qaDb = Join-Path (Resolve-Path .).Path 'database\qa.sqlite'
if (Test-Path -LiteralPath $qaDb) { throw 'La base QA ya existe' }
New-Item -ItemType File -Path $qaDb -ErrorAction Stop | Out-Null
$env:APP_ENV = 'local'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $qaDb
$env:DB_URL = 'null'
$php = (Get-Command php).Source
$qaExt = Join-Path (Split-Path $php) 'ext'
$qaPhpArgs = @('-d', "extension_dir=$qaExt", '-d', 'extension=php_pdo_sqlite.dll', '-d', 'extension=php_sqlite3.dll')
& $php @qaPhpArgs artisan migrate --database=sqlite
```

Una vez que terminen las migraciones, inicia manualmente el comando interactivo `& $php @qaPhpArgs artisan app:create-initial-administrator`. Confirma la ruta de SQLite en el prompt y registra `qa.admin@example.test` con datos ficticios y una contraseña local. El comando inicial crea también los roles. Después agrega el catálogo QA e inicia los servicios.

```powershell
& $php @qaPhpArgs artisan db:seed --class=QaCenterSeeder
.\scripts\qa\iniciar_qa.ps1 -Port 8001
```

- QA Center: `http://127.0.0.1:8001/qa`
- Bandeja SMTP local: `http://127.0.0.1:8025`
- SMTP local: `127.0.0.1:1025`

El inicio comprueba y levanta Mailpit automáticamente; si falta el ejecutable, lo instala desde la publicación oficial verificada por SHA-256. Repetir el comando reutiliza los procesos QA/Mailpit verificados y no crea duplicados. El inicio no ejecuta migraciones ni seeders. No habilite Selenium de login hasta ingresar `QA_E2E_PASSWORD` mediante la entrada oculta local del script. No comparta contraseñas en el repositorio, comandos o reportes.
Para cargar esa contraseña sin reiniciar el servidor web ni Mailpit, use `.\scripts\qa\detener_qa_seguro.ps1 -WorkerOnly` y vuelva a iniciar con `.\scripts\qa\iniciar_qa.ps1 -Port 8001 -PromptForE2EPassword`. El primer comando solo detiene el worker si la cola QA está vacía.
Use el puerto QA `8001`, no `php artisan serve` en `8000`: en este equipo el proceso hijo de Artisan no carga SQLite, y el limitador de login puede fallar en la caché de base de datos antes del controlador. El script QA configura SQLite, sesión y caché de archivos sin desactivar CSRF ni throttling.

## Motores

- PHPUnit ejecuta casos aislados mediante `phpunit.xml` y SQLite en memoria.
- Selenium WebDriver y Robot Framework verifican el navegador y las rutas protegidas.
- Locust genera CSV de estadísticas agregadas para perfiles locales limitados.
- Laravel Queue ejecuta el runner en un worker independiente del servidor HTTP.
- PDF y HTML detallan datos de caso, validaciones, pasos, resultados y capturas disponibles.

El catálogo contiene 23 casos; el registro en base de datos no implica automatización. La implementación actual clasifica casos como `IMPLEMENTED`, `BLOCKED` o `NOT RUN`. Los dos flujos empresariales E2E permanecen bloqueados mientras falten usuarios operativos y datos aislados. La prueba de validación FAIL controlado solo invierte una expectativa HTML5 y no se presenta como defecto empresarial.

El CSV fuente `qa_engine/performance/locust-short_stats.csv` contiene **14 solicitudes agregadas** y cero fallos. El runner verifica el total del CSV, no una cifra transcrita manualmente.

## Verificaciones ejecutadas

- `QaCenterWorkflowTest`: 12 pruebas y 49 aserciones.
- Pruebas Python del runner e informes: 3 aprobadas.
- Robot Framework: ruta protegida verificada contra el servidor QA local.
- Locust: perfiles de 14, 30, 60 y 20 solicitudes agregadas aprobados.
- Selenium: validación HTML5 real verificada en Edge; la automatización del acceso queda bloqueada hasta configurar la contraseña local.
- Mailpit local recibió reportes PDF y HTML de una ejecución verificada.

## Configuración pendiente

- Introducir la contraseña QA con el prompt seguro local para realizar login Selenium desde la web.
- Ollama es opcional. El modo local del asistente muestra respuestas preparadas.

## Documentación de entrega

- [Manual de instalación](../Manual_Instalacion.md)
- [Catálogo de casos](../Catalogo_Casos_Prueba.md)
- [Matriz de trazabilidad](../Matriz_Trazabilidad.md)

No ejecutar `preparar_datos_qa.ps1` sobre una base existente sin autorización y respaldo verificado. Nunca apuntar el entorno QA a bases DEMO o empresariales.
