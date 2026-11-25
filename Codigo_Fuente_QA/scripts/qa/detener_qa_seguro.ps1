param(
    [switch]$WorkerOnly
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path "$PSScriptRoot\..\..").Path
$php = [IO.Path]::GetFullPath((Get-Command php -ErrorAction Stop).Source)
$services = Join-Path $root 'storage\app\private\qa\services'

$serviceDefinitions = @(
    @{ Name = 'worker'; ExpectedCommand = 'artisan["]?\s+queue:work\s+database(?:\s|$)' },
    @{ Name = 'server'; ExpectedCommand = 'router\.php' }
)
if ($WorkerOnly) {
    $qaDatabase = Join-Path $root 'database\qa.sqlite'
    $python = Join-Path $root '.venv-qa\Scripts\python.exe'
    if (-not (Test-Path -LiteralPath $qaDatabase -PathType Leaf) -or -not (Test-Path -LiteralPath $python -PathType Leaf)) {
        throw 'No se pudo verificar la cola QA; no se detuvo el worker.'
    }
    $env:QA_STOP_DB = $qaDatabase
    $queueCheck = "import os,sqlite3,sys; p='file:'+os.environ['QA_STOP_DB'].replace('\\','/')+'?mode=ro'; c=sqlite3.connect(p,uri=True); n={r[0] for r in c.execute('SELECT name FROM sqlite_master WHERE type=?',('table',))}; sys.exit(0 if 'jobs' in n and c.execute('SELECT COUNT(*) FROM jobs').fetchone()[0]==0 else 1)"
    try {
        & $python -c $queueCheck
        $queueCheckExitCode = $LASTEXITCODE
    }
    finally {
        Remove-Item Env:\QA_STOP_DB -ErrorAction SilentlyContinue
    }
    if ($queueCheckExitCode -ne 0) {
        throw 'La cola QA no está vacía o no pudo verificarse; no se detuvo el worker.'
    }
    $serviceDefinitions = @($serviceDefinitions | Where-Object { $_.Name -eq 'worker' })
}

foreach ($service in $serviceDefinitions) {
    $pidPath = Join-Path $services ($service.Name + '.pid')
    if (-not (Test-Path -LiteralPath $pidPath)) {
        continue
    }

    $processId = 0
    if (-not [int]::TryParse((Get-Content -LiteralPath $pidPath -Raw).Trim(), [ref]$processId) -or $processId -le 0) {
        throw "El PID registrado para $($service.Name) no es válido; no se detuvo ningún proceso."
    }

    $processInfo = Get-CimInstance -ClassName Win32_Process -Filter "ProcessId = $processId" -ErrorAction SilentlyContinue
    if (-not $processInfo) {
        Remove-Item -LiteralPath $pidPath
        continue
    }

    $matchesPhp = [string]::Equals([IO.Path]::GetFullPath($processInfo.ExecutablePath), $php, [StringComparison]::OrdinalIgnoreCase)
    $matchesCommand = $processInfo.CommandLine -and [regex]::IsMatch($processInfo.CommandLine, $service.ExpectedCommand, [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
    $processObject = Get-Process -Id $processId -ErrorAction Stop
    $pidFileTime = (Get-Item -LiteralPath $pidPath).LastWriteTimeUtc
    $ageSinceStart = ($pidFileTime - $processObject.StartTime.ToUniversalTime()).TotalSeconds
    if (-not ($matchesPhp -and $matchesCommand) -or $ageSinceStart -lt -2 -or $ageSinceStart -gt 120) {
        throw "El PID registrado ya no corresponde al proceso QA esperado para $($service.Name); no se detuvo."
    }
    if ($service.Name -eq 'server') {
        $portMatch = [regex]::Match($processInfo.CommandLine, '-S\s+127\.0\.0\.1:(\d+)')
        $listener = if ($portMatch.Success) {
            Get-NetTCPConnection -State Listen -LocalPort ([int]$portMatch.Groups[1].Value) -OwningProcess $processId -ErrorAction SilentlyContinue
        } else {
            $null
        }
        if (-not $listener) {
            throw 'El servidor no posee el listener local indicado; no se detuvo.'
        }
    }

    Stop-Process -Id $processId -ErrorAction Stop
    Remove-Item -LiteralPath $pidPath
    Write-Host "Proceso QA detenido: $($service.Name) (PID $processId)."
}
