param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('inspect', 'migrate-status', 'migrate', 'seed', 'authorization-seed', 'verify', 'credentials', 'ml-migrate', 'ml-permissions', 'ml-seed', 'ml-train', 'ml-verify', 'serve')]
    [string] $Action
)

$ErrorActionPreference = 'Stop'
$credentialPath = Join-Path $env:LOCALAPPDATA 'IMPORTSYSTEMGO\mysql-demo-credentials.dpapi.json'
if (-not (Test-Path -LiteralPath $credentialPath)) {
    throw 'No se encontro la credencial DEMO protegida.'
}
$data = Get-Content -Raw -LiteralPath $credentialPath | ConvertFrom-Json
if ($data.database -ne 'importsystemgo_demo' -or $data.demo_user -ne 'importsystemgo_demo') {
    throw 'El almacen protegido no corresponde al entorno DEMO autorizado.'
}
$securePassword = ConvertTo-SecureString $data.demo_password_dpapi
$credential = [System.Management.Automation.PSCredential]::new($data.demo_user, $securePassword)

$env:APP_ENV = 'local'
$env:DEMO_RETAIL_ENABLED = 'true'
$env:DEMO_DATABASE = 'importsystemgo_demo'
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_DATABASE = 'importsystemgo_demo'
$env:DB_USERNAME = 'importsystemgo_demo'
$env:DB_PASSWORD = $credential.GetNetworkCredential().Password

try {
    php scripts/inspect_mysql_demo.php
    if ($LASTEXITCODE -ne 0) {
        throw 'La inspeccion previa DEMO fallo.'
    }
    if ($Action -eq 'migrate') {
        php artisan migrate --force
    } elseif ($Action -eq 'migrate-status') {
        php artisan migrate:status
    } elseif ($Action -eq 'seed') {
        php artisan demo:seed --force
    } elseif ($Action -eq 'authorization-seed') {
        php artisan db:seed --class=Database\Seeders\AuthorizationSeeder --force
    } elseif ($Action -eq 'verify') {
        php artisan demo:verify
    } elseif ($Action -eq 'credentials') {
        php artisan demo:credentials
    } elseif ($Action -eq 'ml-migrate') {
        php artisan migrate --path=database/migrations/2026_11_18_010001_create_machine_learning_forecast_tables.php --force
    } elseif ($Action -eq 'ml-permissions') {
        php artisan db:seed --class=Database\Seeders\MachineLearningAuthorizationSeeder --force
    } elseif ($Action -eq 'ml-seed') {
        php artisan demo:ml-history
    } elseif ($Action -eq 'ml-train') {
        php artisan ml:demand:train
    } elseif ($Action -eq 'ml-verify') {
        php scripts/verify_ml_demo.php
    } elseif ($Action -eq 'serve') {
        $listener = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
        if ($listener) {
            throw "El puerto 8000 ya esta ocupado por el proceso $($listener[0].OwningProcess). Detenga ese servidor antes de iniciar el entorno DEMO."
        }
        # `artisan serve` filters custom variables before launching its child process on Windows.
        # The PHP development server inherits this process environment without persisting secrets.
        $router = Join-Path (Get-Location) 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
        Push-Location public
        try {
            php -S 127.0.0.1:8000 $router
        } finally {
            Pop-Location
        }
    }
    if ($LASTEXITCODE -ne 0) {
        throw 'La operacion Laravel DEMO fallo.'
    }
} finally {
    Remove-Item Env:DB_PASSWORD -ErrorAction SilentlyContinue
    $credential = $null
    $securePassword = $null
}
