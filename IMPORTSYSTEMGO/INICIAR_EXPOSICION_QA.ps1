param(
    [switch]$ConfigurarCredencial,
    [switch]$NoAbrirPestanas
)

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
Set-Location $root
$qaDatabase = Join-Path $root 'database\qa.sqlite'
$php = (Get-Command php -ErrorAction Stop).Source
$extensionDirectory = Join-Path (Split-Path $php) 'ext'
$plainPassword = $null

try {
    if ($ConfigurarCredencial) {
        $first = Read-Host 'Contraseña local nueva para qa.admin@example.test (entrada oculta)' -AsSecureString
        $second = Read-Host 'Repite la contraseña local (entrada oculta)' -AsSecureString
        $firstPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($first)
        $secondPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($second)
        try {
            $plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($firstPointer)
            $confirmation = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($secondPointer)
        }
        finally {
            [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($firstPointer)
            [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($secondPointer)
        }
        if (-not [string]::Equals($plainPassword, $confirmation, [StringComparison]::Ordinal)) {
            throw 'Las contraseñas no coinciden; no se modificó la cuenta QA.'
        }
        $confirmation = $null

        & (Join-Path $root 'scripts\qa\detener_qa_seguro.ps1') -WorkerOnly
        $env:APP_ENV = 'local'
        $env:DB_CONNECTION = 'sqlite'
        $env:DB_DATABASE = $qaDatabase
        $env:DB_URL = 'null'
        $env:QA_E2E_PASSWORD = $plainPassword
        & $php -d "extension_dir=$extensionDirectory" -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll artisan qa:reset-demo-password --from-environment
        if ($LASTEXITCODE -ne 0) {
            throw 'No se pudo actualizar la credencial QA.'
        }
    }

    & (Join-Path $root 'scripts\qa\iniciar_qa.ps1') -Port 8001
    if ($LASTEXITCODE -ne 0) {
        throw 'El arranque QA terminó con error.'
    }

    foreach ($uri in @('http://127.0.0.1:8001/up', 'http://127.0.0.1:8001/login', 'http://127.0.0.1:8025/api/v1/messages')) {
        $response = Invoke-WebRequest -Uri $uri -UseBasicParsing -TimeoutSec 5
        if ($response.StatusCode -ne 200) {
            throw "Disponibilidad fallida: $uri respondió $($response.StatusCode)."
        }
    }

    Write-Host 'Servicios obligatorios disponibles: Laravel 8001, worker QA, SMTP 1025 y Mailpit 8025.' -ForegroundColor Green
    Write-Host 'El motor Python se invoca bajo demanda por cada ejecución. Ollama permanece opcional y no está declarado como disponible.'
    if (-not $NoAbrirPestanas) {
        & (Join-Path $root 'ABRIR_PESTANAS_EXPOSICION.ps1')
    }
}
finally {
    $plainPassword = $null
    $confirmation = $null
    Remove-Item Env:\QA_E2E_PASSWORD -ErrorAction SilentlyContinue
}
