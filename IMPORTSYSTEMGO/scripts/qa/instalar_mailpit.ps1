$ErrorActionPreference = 'Stop'
$root = (Resolve-Path "$PSScriptRoot\..\..").Path
$privateQa = Join-Path $root 'storage\app\private\qa'
$version = '1.34.0'
$archive = Join-Path $privateQa "mailpit-windows-amd64-v$version.zip"
$installDirectory = Join-Path $privateQa "tools\mailpit-$version"
$expectedHash = '8f080d41374bf22686606374a0a483c8563c1bf29ae078179792392ff8c911d7'
$downloadUrl = "https://github.com/axllent/mailpit/releases/download/v$version/mailpit-windows-amd64.zip"

if (Test-Path -LiteralPath (Join-Path $installDirectory 'mailpit.exe')) {
    & (Join-Path $installDirectory 'mailpit.exe') version
    if ($LASTEXITCODE -ne 0) {
        throw "La instalación existente de Mailpit v$version no inicia correctamente."
    }
    return
}
if (Test-Path -LiteralPath $installDirectory) {
    throw "El directorio de Mailpit v$version existe incompleto; no se reemplazó."
}
New-Item -ItemType Directory -Path $privateQa -Force | Out-Null
Invoke-WebRequest -Uri $downloadUrl -OutFile $archive

$actualHash = (Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash.ToLowerInvariant()
if ($actualHash -ne $expectedHash) {
    Remove-Item -LiteralPath $archive
    throw 'La huella SHA-256 oficial de Mailpit no coincide; no se instaló el archivo.'
}

New-Item -ItemType Directory -Path $installDirectory -Force | Out-Null
Expand-Archive -LiteralPath $archive -DestinationPath $installDirectory
Remove-Item -LiteralPath $archive

$executable = Join-Path $installDirectory 'mailpit.exe'
if (-not (Test-Path -LiteralPath $executable)) {
    throw 'El paquete verificado no contiene mailpit.exe.'
}
& $executable version
if ($LASTEXITCODE -ne 0) {
    throw 'No fue posible verificar la versión instalada de Mailpit.'
}
