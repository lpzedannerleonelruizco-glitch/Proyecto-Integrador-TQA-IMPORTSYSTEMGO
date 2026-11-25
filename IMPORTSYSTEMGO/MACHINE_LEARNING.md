# IMPORTSYSTEMGO Intelligence — pronóstico de demanda

## Objetivo

Pronosticar demanda a 30 días por producto y sucursal, detectar riesgo de agotamiento y proponer reposición. Las propuestas requieren decisión humana y nunca crean compras automáticamente.

## Diseño

- Laravel agrega datos sin entregar credenciales MySQL al proceso Python.
- Python 3.13 ejecuta `HistGradientBoostingRegressor` con scikit-learn 1.9.1.
- Las variables usan rezagos de 1, 7, 14 y 28 días, medias y desviaciones móviles, calendario, producto, categoría, marca y sucursal.
- La validación es temporal y deja un intervalo de purga de 30 días entre entrenamiento y validación para impedir fuga de información futura.
- El modelo se compara con un promedio móvil de 28 días. Solo queda `active` si su WAPE es menor; de lo contrario queda `candidate`.
- Cada artefacto se guarda fuera del área pública y se verifica con SHA-256.
- WAPE, MAE, sesgo, procedencia, rango temporal y filas de validación quedan versionados y auditados.

## Datos académicos

`DemoMachineLearningHistorySeeder` prepara 540 días para 7 sucursales y 12 productos: 45.360 observaciones reproducibles. Se almacenan en `ml_demand_observations` con `source=synthetic_demo`; no son ventas, pagos, caja ni kardex y no alteran balances comerciales.

En un entorno real, si no existe historia sintética DEMO, el conjunto se construye desde ventas con estado `paid` o `credit`.

## Seguridad y alcance

- `ml.models.manage`: entrenamiento global, reservado al administrador general.
- `ml.forecasts.view`: consulta dentro de sucursales autorizadas.
- `ml.replenishment.decide`: aprobación o rechazo dentro del ámbito territorial.
- Jefes de operaciones conservan el límite de su área; jefes de tienda ven su sucursal; vendedores no acceden al módulo.
- No se confía en `branch_id` enviado por formularios.

## Instalación local preparada

El entorno virtual está en `.venv-ml` y está excluido del control de versiones. Para reconstruirlo:

```powershell
& "$env:LOCALAPPDATA\Programs\Python\Python313\python.exe" -m venv .venv-ml
.\.venv-ml\Scripts\python.exe -m pip install -r ml\requirements-lock.txt
```

## Despliegue DEMO

El 2026-11-18 se aplicó exclusivamente `2026_11_18_010001_create_machine_learning_forecast_tables.php` en `importsystemgo_demo`, después de verificar identidad y respaldo cifrado. Las tres migraciones pendientes de inventario no se ejecutaron y la base principal no fue consultada ni modificada.

Estado: 45 tablas, 15 migraciones, 47 permisos, 45.360 observaciones, 84 pronósticos y 84 recomendaciones. El primer modelo quedó activo con WAPE 6,85%, frente a 8,75% de la línea base, y 8.148 filas de validación.

Comandos de mantenimiento manual:

```powershell
.\scripts\with_demo_environment.ps1 -Action ml-migrate
.\scripts\with_demo_environment.ps1 -Action ml-permissions
.\scripts\with_demo_environment.ps1 -Action ml-seed
.\scripts\with_demo_environment.ps1 -Action ml-train
.\scripts\with_demo_environment.ps1 -Action serve
```

Abra `http://127.0.0.1:8000/inteligencia/demanda` después de iniciar sesión y seleccionar una sucursal.

## Pruebas

```powershell
.\.venv-ml\Scripts\python.exe -m unittest discover -s ml\tests -v
$php=(Get-Command php).Source
$ext=Join-Path (Split-Path $php) 'ext'
& $php -d "extension_dir=$ext" -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll vendor/bin/phpunit --configuration phpunit.xml
php vendor/bin/pint --test
npm.cmd run build
```
