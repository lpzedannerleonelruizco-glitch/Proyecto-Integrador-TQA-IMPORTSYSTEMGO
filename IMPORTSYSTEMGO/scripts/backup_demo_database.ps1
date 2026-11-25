param([string] $Label = 'manual')

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$credentialPath = Join-Path $env:LOCALAPPDATA 'IMPORTSYSTEMGO\mysql-demo-credentials.dpapi.json'
if (-not (Test-Path -LiteralPath $credentialPath)) { throw 'No se encontro la credencial DEMO protegida.' }
$credentialData = Get-Content -Raw -LiteralPath $credentialPath | ConvertFrom-Json
if ($credentialData.database -ne 'importsystemgo_demo' -or $credentialData.demo_user -ne 'importsystemgo_demo') { throw 'El almacen protegido no corresponde a DEMO.' }
$securePassword = ConvertTo-SecureString $credentialData.demo_password_dpapi
$credential = [System.Management.Automation.PSCredential]::new($credentialData.demo_user, $securePassword)
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupRoot = [IO.Path]::GetFullPath((Join-Path $env:LOCALAPPDATA 'IMPORTSYSTEMGO\backups'))
$backupDirectory = [IO.Path]::GetFullPath((Join-Path $backupRoot "$Label-$stamp"))
if (-not $backupDirectory.StartsWith($backupRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Ruta de respaldo no autorizada.' }
New-Item -ItemType Directory -Path $backupDirectory -Force | Out-Null
$sqlPath = Join-Path $backupDirectory 'importsystemgo_demo.sql'
$gzipPath = "$sqlPath.gz"
$encryptedPath = "$gzipPath.dpapi"
$metadataPath = Join-Path $backupDirectory 'metadata.json'

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
    Push-Location $projectRoot
    try {
        $dumpResult = php scripts/create_demo_backup.php "--destination=$sqlPath"
        if ($LASTEXITCODE -ne 0) { throw 'Fallo la creacion del respaldo DEMO.' }
    } finally { Pop-Location }
    $dumpMetadata = $dumpResult | ConvertFrom-Json
    $input = [IO.File]::OpenRead($sqlPath)
    $output = [IO.File]::Create($gzipPath)
    try {
        $gzip = [IO.Compression.GZipStream]::new($output, [IO.Compression.CompressionLevel]::Optimal, $true)
        try { $input.CopyTo($gzip) } finally { $gzip.Dispose() }
    } finally { $input.Dispose(); $output.Dispose() }
    $protected = [Security.Cryptography.ProtectedData]::Protect([IO.File]::ReadAllBytes($gzipPath), $null, [Security.Cryptography.DataProtectionScope]::CurrentUser)
    [IO.File]::WriteAllBytes($encryptedPath, $protected)

    $roundTripGzip = [Security.Cryptography.ProtectedData]::Unprotect([IO.File]::ReadAllBytes($encryptedPath), $null, [Security.Cryptography.DataProtectionScope]::CurrentUser)
    $memory = [IO.MemoryStream]::new($roundTripGzip)
    $decompressed = [IO.MemoryStream]::new()
    try {
        $reader = [IO.Compression.GZipStream]::new($memory, [IO.Compression.CompressionMode]::Decompress)
        try { $reader.CopyTo($decompressed) } finally { $reader.Dispose() }
        $sha = [Security.Cryptography.SHA256]::Create()
        try { $roundTripHash = -join ($sha.ComputeHash($decompressed.ToArray()) | ForEach-Object { $_.ToString('x2') }) } finally { $sha.Dispose() }
    } finally { $memory.Dispose(); $decompressed.Dispose() }
    if ($roundTripHash -ne $dumpMetadata.sha256) { throw 'El respaldo cifrado no supero la verificacion SHA-256.' }
    [ordered]@{
        database = $dumpMetadata.database
        account = $dumpMetadata.account
        structure_verified = $dumpMetadata.structure_verified
        source_bytes = $dumpMetadata.bytes
        sha256 = $dumpMetadata.sha256
        encrypted_file = $encryptedPath
        verified_at = (Get-Date).ToString('o')
    } | ConvertTo-Json | Set-Content -LiteralPath $metadataPath -Encoding UTF8
    "BACKUP_VERIFIED=yes"
    "BACKUP_FILE=$encryptedPath"
    "BACKUP_SHA256=$($dumpMetadata.sha256)"
} finally {
    foreach ($temporary in @($sqlPath, $gzipPath)) {
        $resolved = [IO.Path]::GetFullPath($temporary)
        if ($resolved.StartsWith($backupRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $resolved)) { Remove-Item -LiteralPath $resolved -Force }
    }
    Remove-Item Env:DB_PASSWORD -ErrorAction SilentlyContinue
    $credential = $null
    $securePassword = $null
}
