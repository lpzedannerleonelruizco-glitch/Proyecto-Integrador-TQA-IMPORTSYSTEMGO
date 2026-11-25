param(
    [ValidateRange(1, 65535)]
    [int]$Port = 8001,
    [switch]$PromptForE2EPassword
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path "$PSScriptRoot\..\..").Path
Set-Location $root

$php = (Get-Command php -ErrorAction Stop).Source
$extensionDirectory = Join-Path (Split-Path $php) 'ext'
$sqliteExtensions = @('php_pdo_sqlite.dll', 'php_sqlite3.dll')
foreach ($extension in $sqliteExtensions) {
    if (-not (Test-Path -LiteralPath (Join-Path $extensionDirectory $extension))) {
        throw "Falta la extension PHP requerida: $extension"
    }
}
$sqliteCheck = "exit(extension_loaded('pdo_sqlite') && extension_loaded('sqlite3') ? 0 : 1);"
$null = & $php -d "extension_dir=$extensionDirectory" -d 'extension=php_pdo_sqlite.dll' -d 'extension=php_sqlite3.dll' -r $sqliteCheck
if ($LASTEXITCODE -ne 0) {
    throw 'PHP no pudo cargar pdo_sqlite y sqlite3 para el entorno QA.'
}

$qaDatabase = Join-Path $root 'database\qa.sqlite'
if (-not (Test-Path -LiteralPath $qaDatabase)) {
    throw 'No existe database\qa.sqlite; no se creo ni inicializo una base automaticamente.'
}
if (-not $env:APP_KEY -and -not (Select-String -Path (Join-Path $root '.env') -Pattern '^\s*APP_KEY\s*=\s*.+\S' -Quiet -ErrorAction SilentlyContinue)) {
    throw 'Falta APP_KEY en el entorno local; configure .env sin mostrar el secreto.'
}

$python = Join-Path $root '.venv-qa\Scripts\python.exe'
if (-not (Test-Path -LiteralPath $python)) {
    throw 'Falta .venv-qa; instale unicamente las dependencias fijadas de qa_engine.'
}
$env:QA_PREFLIGHT_DB = $qaDatabase
$schemaCheck = & $python -c "import os,sqlite3,sys; c=sqlite3.connect(os.environ['QA_PREFLIGHT_DB']); names={r[0] for r in c.execute('SELECT name FROM sqlite_master WHERE type=?', ('table',))}; c.close(); sys.exit(0 if {'users','jobs','qa_executions','qa_test_cases'}.issubset(names) else 1)"
Remove-Item Env:\QA_PREFLIGHT_DB
if ($LASTEXITCODE -ne 0) {
    throw 'database\qa.sqlite no contiene las tablas QA y de cola requeridas; no se ejecutaron migraciones.'
}

$servicesDirectory = Join-Path $root 'storage\app\private\qa\services'
New-Item -ItemType Directory -Path $servicesDirectory -Force | Out-Null
$expectedPhp = [IO.Path]::GetFullPath($php)
$serviceProcesses = @{}

foreach ($serviceName in @('server', 'worker')) {
    $pidPath = Join-Path $servicesDirectory ($serviceName + '.pid')
    if (-not (Test-Path -LiteralPath $pidPath)) {
        continue
    }
    $recordedPid = 0
    if (-not [int]::TryParse((Get-Content -LiteralPath $pidPath -Raw).Trim(), [ref]$recordedPid) -or $recordedPid -le 0) {
        throw "El PID registrado para $serviceName no es valido; no se inicio otra instancia."
    }
    if (Get-Process -Id $recordedPid -ErrorAction SilentlyContinue) {
        $processInfo = Get-CimInstance -ClassName Win32_Process -Filter "ProcessId = $recordedPid" -ErrorAction SilentlyContinue
        $matchesExecutable = $processInfo -and $processInfo.ExecutablePath -and
            [string]::Equals([IO.Path]::GetFullPath($processInfo.ExecutablePath), $expectedPhp, [StringComparison]::OrdinalIgnoreCase)
        if ($serviceName -eq 'server') {
            $matchesCommand = $processInfo.CommandLine -and
                $processInfo.CommandLine.Contains("-S 127.0.0.1:$Port") -and
                $processInfo.CommandLine.Contains((Join-Path $root 'scripts\qa\router.php'))
        }
        else {
            $matchesCommand = $processInfo.CommandLine -and
                $processInfo.CommandLine.Contains((Join-Path $root 'artisan')) -and
                $processInfo.CommandLine.Contains('queue:work database') -and
                $processInfo.CommandLine.Contains('--queue=default') -and
                $processInfo.CommandLine.Contains('--timeout=900')
        }
        if (-not ($matchesExecutable -and $matchesCommand)) {
            throw "El PID registrado para QA $serviceName no coincide con el proceso esperado; no se inicio otro."
        }
        $serviceProcesses[$serviceName] = $processInfo
        continue
    }
    Remove-Item -LiteralPath $pidPath
}

if ($serviceProcesses.ContainsKey('server')) {
    $serverPid = [int]$serviceProcesses['server'].ProcessId
    $serverListener = Get-NetTCPConnection -State Listen -LocalPort $Port -OwningProcess $serverPid -ErrorAction SilentlyContinue
    if (-not $serverListener) {
        throw 'El servidor QA registrado no posee el listener esperado; no se inicio otro proceso.'
    }
}
else {
    $occupiedListener = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue
    if ($occupiedListener) {
        throw "El puerto $Port esta ocupado por un proceso no registrado como QA; no se inicio otro."
    }
    $orphanServer = @(Get-CimInstance -ClassName Win32_Process -ErrorAction SilentlyContinue | Where-Object {
        $_.ExecutablePath -and
        [string]::Equals([IO.Path]::GetFullPath($_.ExecutablePath), $expectedPhp, [StringComparison]::OrdinalIgnoreCase) -and
        $_.CommandLine -and
        $_.CommandLine.Contains("-S 127.0.0.1:$Port") -and
        $_.CommandLine.Contains((Join-Path $root 'scripts\qa\router.php'))
    })
    if ($orphanServer.Count -gt 0) {
        throw 'Existe un servidor QA sin PID registrado; no se inicio otra instancia.'
    }
}

$projectWorkers = @(Get-CimInstance -ClassName Win32_Process -ErrorAction SilentlyContinue | Where-Object {
    $_.ExecutablePath -and
    [string]::Equals([IO.Path]::GetFullPath($_.ExecutablePath), $expectedPhp, [StringComparison]::OrdinalIgnoreCase) -and
    $_.CommandLine -and
    $_.CommandLine.Contains((Join-Path $root 'artisan')) -and
    $_.CommandLine.Contains('queue:work database')
})
if ($projectWorkers.Count -gt 1) {
    throw 'Se detectaron varios workers PHP de este proyecto; no se inicio otro proceso.'
}
if ($serviceProcesses.ContainsKey('worker')) {
    if ($projectWorkers.Count -ne 1 -or $projectWorkers[0].ProcessId -ne $serviceProcesses['worker'].ProcessId) {
        throw 'El worker registrado no coincide con el unico worker activo; no se inicio otro.'
    }
}
elseif ($projectWorkers.Count -gt 0) {
    throw 'Existe un worker del proyecto sin PID QA registrado; no se inicio otro proceso.'
}

$startServer = -not $serviceProcesses.ContainsKey('server')
$startWorker = -not $serviceProcesses.ContainsKey('worker')

$mailpitExecutable = Join-Path $root 'storage\app\private\qa\tools\mailpit-1.34.0\mailpit.exe'
if (-not (Test-Path -LiteralPath $mailpitExecutable -PathType Leaf)) {
    & (Join-Path $PSScriptRoot 'instalar_mailpit.ps1')
}
if (-not (Test-Path -LiteralPath $mailpitExecutable -PathType Leaf)) {
    throw 'La instalacion oficial de Mailpit termino sin dejar el ejecutable esperado.'
}
& (Join-Path $PSScriptRoot 'iniciar_mailpit.ps1') -SmtpPort 1025 -UiPort 8025

if (-not $env:QA_E2E_PASSWORD -and $PromptForE2EPassword -and $startWorker) {
    $securePassword = Read-Host 'Contrasena local de la cuenta QA para Selenium (entrada oculta)' -AsSecureString
    $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
    try {
        $env:QA_E2E_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
    }
    finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    }
}
elseif (-not $env:QA_E2E_PASSWORD -and $PromptForE2EPassword) {
    Write-Host 'El worker QA ya esta activo; no se reinicio ni se aplico una nueva contrasena a ese proceso. Use detener_qa_seguro.ps1 y vuelva a ejecutar con -PromptForE2EPassword para habilitar Selenium.'
}

$serverLog = Join-Path $servicesDirectory 'server.log'
$workerLog = Join-Path $servicesDirectory 'worker.log'
$serverErrorLog = Join-Path $servicesDirectory 'server-error.log'
$workerErrorLog = Join-Path $servicesDirectory 'worker-error.log'

$env:APP_ENV = 'local'
$env:APP_DEBUG = 'false'
$env:APP_URL = "http://127.0.0.1:$Port"
$relativeServicesDirectory = 'storage\app\private\qa\services'
$env:APP_CONFIG_CACHE = Join-Path $relativeServicesDirectory 'config.php'
$env:APP_ROUTES_CACHE = Join-Path $relativeServicesDirectory 'routes.php'
$env:APP_SERVICES_CACHE = Join-Path $relativeServicesDirectory 'services.php'
$env:APP_EVENTS_CACHE = Join-Path $relativeServicesDirectory 'events.php'
$env:APP_PACKAGES_CACHE = Join-Path $relativeServicesDirectory 'packages.php'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $qaDatabase
$env:DB_URL = 'null'
$env:QUEUE_CONNECTION = 'database'
$env:DB_QUEUE = 'default'
$env:DB_QUEUE_RETRY_AFTER = '960'
$env:QUEUE_FAILED_DRIVER = 'database-uuids'
$env:SESSION_DRIVER = 'file'
$env:SESSION_COOKIE = "importsystemgo_qa_${Port}_session"
$env:SESSION_SECURE_COOKIE = 'false'
$env:SESSION_SAME_SITE = 'lax'
$env:CACHE_STORE = 'file'
$env:MAIL_MAILER = 'smtp'
$env:MAIL_URL = 'null'
$env:MAIL_SCHEME = 'null'
$env:MAIL_HOST = '127.0.0.1'
$env:MAIL_PORT = '1025'
$env:MAIL_USERNAME = ''
$env:MAIL_PASSWORD = ''
$env:MAIL_FROM_ADDRESS = 'qa-center@example.test'
$env:MAIL_FROM_NAME = 'IMPORTSYSTEMGO QA CENTER'
$env:QA_BASE_URL = $env:APP_URL
$env:QA_E2E_EMAIL = 'qa.admin@example.test'
$env:QA_AI_PROVIDER = 'none'
$env:QA_AI_API_URL = 'null'
$env:QA_AI_API_KEY = 'null'
$env:QA_AI_MODEL = 'null'
$env:QA_AI_OLLAMA_URL = 'http://127.0.0.1:11434'

$sqliteArguments = '-d extension_dir="' + $extensionDirectory + '" -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll'
$serverArguments = "$sqliteArguments -S 127.0.0.1:$Port -t `"$root\public`" `"$root\scripts\qa\router.php`""
$workerArguments = "$sqliteArguments `"$root\artisan`" queue:work database --queue=default --sleep=1 --tries=1 --timeout=900"

$server = $null
$worker = $null
$serverId = if ($serviceProcesses.ContainsKey('server')) { [int]$serviceProcesses['server'].ProcessId } else { 0 }
$workerId = if ($serviceProcesses.ContainsKey('worker')) { [int]$serviceProcesses['worker'].ProcessId } else { 0 }
try {
    if ($startServer) {
        $server = Start-Process -FilePath $php -ArgumentList $serverArguments -WorkingDirectory $root -RedirectStandardOutput $serverLog -RedirectStandardError $serverErrorLog -PassThru
        $serverId = $server.Id
    }
    else {
        Write-Host "Servidor QA ya activo en 127.0.0.1:$Port; no se inicio otra instancia."
    }
    if ($startWorker) {
        $worker = Start-Process -FilePath $php -ArgumentList $workerArguments -WorkingDirectory $root -RedirectStandardOutput $workerLog -RedirectStandardError $workerErrorLog -PassThru
        $workerId = $worker.Id
    }
    else {
        Write-Host "Worker QA ya activo (PID $workerId); no se inicio otra instancia."
    }

    $ready = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        Start-Sleep -Milliseconds 500
        if (($server -and $server.HasExited) -or ($worker -and $worker.HasExited) -or
            -not (Get-Process -Id $serverId -ErrorAction SilentlyContinue) -or
            -not (Get-Process -Id $workerId -ErrorAction SilentlyContinue)) {
            throw 'El servidor HTTP o el worker termino durante el inicio; revise los logs locales.'
        }
        try {
            $response = Invoke-WebRequest -Uri "$($env:APP_URL)/up" -UseBasicParsing -TimeoutSec 2
            if ($response.StatusCode -eq 200) {
                $ready = $true
                break
            }
        }
        catch {
            Start-Sleep -Milliseconds 250
        }
    }
    if (-not $ready) {
        throw 'El servidor QA no respondio al endpoint local /up.'
    }

    if ($startServer) {
        Set-Content -LiteralPath (Join-Path $servicesDirectory 'server.pid') -Value $serverId -Encoding ASCII
    }
    if ($startWorker) {
        Set-Content -LiteralPath (Join-Path $servicesDirectory 'worker.pid') -Value $workerId -Encoding ASCII
    }
    Write-Host "QA Center: $($env:APP_URL)/qa"
    Write-Host "Worker independiente: PID $workerId"
    Write-Host 'SMTP local y Mailpit: 127.0.0.1:1025 y http://127.0.0.1:8025'
    if ($startWorker -and -not $env:QA_E2E_PASSWORD) {
        Write-Host 'Selenium de acceso: BLOCKED hasta configurar QA_E2E_PASSWORD localmente; el servidor puede iniciar sin credenciales.'
    }
    elseif (-not $startWorker -and -not $env:QA_E2E_PASSWORD -and -not $PromptForE2EPassword) {
        Write-Host 'Worker QA reutilizado; este proceso de inicio no puede verificar si el worker tiene QA_E2E_PASSWORD configurada.'
    }
}
catch {
    if ($worker -and -not $worker.HasExited) {
        Stop-Process -Id $workerId -ErrorAction SilentlyContinue
    }
    if ($server -and -not $server.HasExited) {
        Stop-Process -Id $serverId -ErrorAction SilentlyContinue
    }
    throw
}
