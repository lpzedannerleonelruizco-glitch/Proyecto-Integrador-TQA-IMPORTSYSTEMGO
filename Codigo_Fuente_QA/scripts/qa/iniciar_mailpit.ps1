param(
    [ValidateRange(1, 65535)]
    [int]$SmtpPort = 1025,
    [ValidateRange(1, 65535)]
    [int]$UiPort = 8025
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path "$PSScriptRoot\..\..").Path
$executable = Join-Path $root 'storage\app\private\qa\tools\mailpit-1.34.0\mailpit.exe'
if (-not (Test-Path -LiteralPath $executable -PathType Leaf)) {
    throw 'Mailpit no esta instalado; ejecute scripts\qa\instalar_mailpit.ps1 primero.'
}
if ($SmtpPort -eq $UiPort) {
    throw 'Los puertos SMTP y UI deben ser distintos.'
}

$expectedExecutable = [IO.Path]::GetFullPath($executable)

function Test-MailpitHttp {
    param([int]$Port)

    try {
        $response = Invoke-WebRequest -Uri "http://127.0.0.1:$Port/" -UseBasicParsing -TimeoutSec 2
        return $response.StatusCode -eq 200
    }
    catch {
        return $false
    }
}

function Test-SmtpGreeting {
    param([int]$Port)

    $client = New-Object System.Net.Sockets.TcpClient
    $reader = $null
    try {
        $client.Connect('127.0.0.1', $Port)
        $stream = $client.GetStream()
        $stream.ReadTimeout = 1500
        $reader = New-Object -TypeName System.IO.StreamReader -ArgumentList $stream
        $greeting = $reader.ReadLine()
        return $greeting -match '^220(?:\s|-)'
    }
    catch [System.Net.Sockets.SocketException] {
        return $false
    }
    catch [System.IO.IOException] {
        return $false
    }
    finally {
        if ($reader) {
            $reader.Dispose()
        }
        $client.Close()
    }
}

$existingListeners = @()
foreach ($port in @($SmtpPort, $UiPort)) {
    $existingListeners += @(Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction SilentlyContinue)
}

if ($existingListeners.Count -gt 0) {
    $smtpOwners = @($existingListeners | Where-Object { $_.LocalPort -eq $SmtpPort } | Select-Object -ExpandProperty OwningProcess -Unique)
    $uiOwners = @($existingListeners | Where-Object { $_.LocalPort -eq $UiPort } | Select-Object -ExpandProperty OwningProcess -Unique)
    if ($smtpOwners.Count -eq 1 -and $uiOwners.Count -eq 1 -and $smtpOwners[0] -eq $uiOwners[0]) {
        $existingProcess = Get-CimInstance -ClassName Win32_Process -Filter "ProcessId = $($smtpOwners[0])" -ErrorAction SilentlyContinue
        $commandLine = if ($existingProcess) { [string]$existingProcess.CommandLine } else { '' }
        $sameExecutable = $existingProcess -and $existingProcess.ExecutablePath -and
            [string]::Equals([IO.Path]::GetFullPath($existingProcess.ExecutablePath), $expectedExecutable, [StringComparison]::OrdinalIgnoreCase)
        $sameArguments = $commandLine.Contains("--smtp 127.0.0.1:$SmtpPort") -and
            $commandLine.Contains("--listen 127.0.0.1:$UiPort")
        $uiReady = $false
        try {
            $response = Invoke-WebRequest -Uri "http://127.0.0.1:$UiPort/" -UseBasicParsing -TimeoutSec 2
            $uiReady = $response.StatusCode -eq 200
        }
        catch {
            $uiReady = $false
        }
        $smtpReady = Test-SmtpGreeting -Port $SmtpPort
        if ($sameExecutable -and $sameArguments -and $uiReady -and $smtpReady) {
            $services = Join-Path $root 'storage\app\private\qa\services'
            New-Item -ItemType Directory -Path $services -Force | Out-Null
            Set-Content -LiteralPath (Join-Path $services 'mailpit.pid') -Value $existingProcess.ProcessId -Encoding ASCII
            Write-Host "Mailpit ya esta activo en 127.0.0.1:$SmtpPort y 127.0.0.1:$UiPort; no se inicio otra instancia."
            return
        }
    }

    throw "Uno o ambos puertos Mailpit ($SmtpPort/$UiPort) estan ocupados por una instancia no verificada; no se inicio otro proceso."
}

$runningMailpit = @(Get-CimInstance -ClassName Win32_Process -Filter "Name = 'mailpit.exe'" -ErrorAction SilentlyContinue |
    Where-Object {
        $_.ExecutablePath -and [string]::Equals([IO.Path]::GetFullPath($_.ExecutablePath), $expectedExecutable, [StringComparison]::OrdinalIgnoreCase)
    })
if ($runningMailpit.Count -gt 0) {
    throw 'Mailpit ya tiene un proceso activo sin los listeners esperados; no se inicio otra instancia.'
}

$privateQa = Join-Path $root 'storage\app\private\qa'
$services = Join-Path $privateQa 'services'
New-Item -ItemType Directory -Path $services -Force | Out-Null
$database = Join-Path $privateQa 'mailpit.db'
$arguments = '--database "' + $database + '" --listen 127.0.0.1:' + $UiPort +
    ' --smtp 127.0.0.1:' + $SmtpPort + ' --disable-version-check --hide-delete-all-button'
$process = Start-Process -FilePath $executable -ArgumentList $arguments -WorkingDirectory $root `
    -RedirectStandardOutput (Join-Path $services 'mailpit.log') `
    -RedirectStandardError (Join-Path $services 'mailpit-error.log') -PassThru

$ready = $false
try {
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        Start-Sleep -Milliseconds 500
        if ($process.HasExited) {
            throw 'Mailpit termino durante el inicio; revise los logs locales.'
        }
        $smtpListener = Get-NetTCPConnection -State Listen -LocalPort $SmtpPort -OwningProcess $process.Id -ErrorAction SilentlyContinue
        $uiListener = Get-NetTCPConnection -State Listen -LocalPort $UiPort -OwningProcess $process.Id -ErrorAction SilentlyContinue
        if ($smtpListener -and $uiListener -and (Test-MailpitHttp -Port $UiPort) -and (Test-SmtpGreeting -Port $SmtpPort)) {
            $ready = $true
            break
        }
    }
    if (-not $ready) {
        throw 'Mailpit no respondio correctamente en SMTP y en su interfaz local.'
    }

    Set-Content -LiteralPath (Join-Path $services 'mailpit.pid') -Value $process.Id -Encoding ASCII
    Write-Host "SMTP local: 127.0.0.1:$SmtpPort"
    Write-Host "Bandeja Mailpit: http://127.0.0.1:$UiPort"
}
catch {
    if (-not $process.HasExited) {
        Stop-Process -Id $process.Id -ErrorAction SilentlyContinue
    }
    throw
}
