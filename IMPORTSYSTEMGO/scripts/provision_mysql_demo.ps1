$ErrorActionPreference = 'Stop'

$storeDirectory = Join-Path $env:LOCALAPPDATA 'IMPORTSYSTEMGO'
$adminPath = Join-Path $storeDirectory 'mysql-credentials.dpapi.json'
$demoPath = Join-Path $storeDirectory 'mysql-demo-credentials.dpapi.json'

if (-not (Test-Path -LiteralPath $adminPath)) {
    throw 'No se encontro el almacen DPAPI administrativo.'
}
if (Test-Path -LiteralPath $demoPath) {
    throw 'Ya existe un almacen DPAPI DEMO; no se realizo ningun cambio.'
}

$admin = Get-Content -Raw -LiteralPath $adminPath | ConvertFrom-Json
$adminSecure = ConvertTo-SecureString $admin.root_password_dpapi
$adminCredential = [System.Management.Automation.PSCredential]::new($admin.root_user, $adminSecure)

$upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ'.ToCharArray()
$lower = 'abcdefghijkmnopqrstuvwxyz'.ToCharArray()
$digits = '23456789'.ToCharArray()
$symbols = '!@#$%&*+-=?'.ToCharArray()
$all = $upper + $lower + $digits + $symbols
$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
function Get-CryptoIndex([int] $Maximum) {
    $bytes = New-Object byte[] 4
    $rng.GetBytes($bytes)
    return [int]([BitConverter]::ToUInt32($bytes, 0) % [uint32] $Maximum)
}
$characters = [System.Collections.Generic.List[char]]::new()
$characters.Add($upper[(Get-CryptoIndex $upper.Length)])
$characters.Add($lower[(Get-CryptoIndex $lower.Length)])
$characters.Add($digits[(Get-CryptoIndex $digits.Length)])
$characters.Add($symbols[(Get-CryptoIndex $symbols.Length)])
while ($characters.Count -lt 32) {
    $characters.Add($all[(Get-CryptoIndex $all.Length)])
}
for ($index = $characters.Count - 1; $index -gt 0; $index--) {
    $swap = Get-CryptoIndex ($index + 1)
    $temporary = $characters[$index]
    $characters[$index] = $characters[$swap]
    $characters[$swap] = $temporary
}
$demoPassword = -join $characters
$demoSecure = ConvertTo-SecureString $demoPassword -AsPlainText -Force
$protectedPassword = ConvertFrom-SecureString $demoSecure

$document = [ordered]@{
    server = '127.0.0.1'
    port = 3306
    database = 'importsystemgo_demo'
    demo_user = 'importsystemgo_demo'
    demo_password_dpapi = $protectedPassword
    protection = 'DPAPI CurrentUser'
    created_at = (Get-Date).ToString('o')
}
$document | ConvertTo-Json | Set-Content -LiteralPath $demoPath -Encoding UTF8

try {
    $env:MYSQL_ADMIN_HOST = '127.0.0.1'
    $env:MYSQL_ADMIN_PORT = '3306'
    $env:MYSQL_ADMIN_USER = $admin.root_user
    $env:MYSQL_ADMIN_PASSWORD = $adminCredential.GetNetworkCredential().Password
    $env:MYSQL_DEMO_PASSWORD = $demoPassword
    php scripts/provision_mysql_demo.php
    if ($LASTEXITCODE -ne 0) {
        throw 'El aprovisionamiento MySQL DEMO no termino correctamente.'
    }
} catch {
    Remove-Item -LiteralPath $demoPath -Force -ErrorAction SilentlyContinue
    throw
} finally {
    Remove-Item Env:MYSQL_ADMIN_PASSWORD -ErrorAction SilentlyContinue
    Remove-Item Env:MYSQL_DEMO_PASSWORD -ErrorAction SilentlyContinue
    $demoPassword = $null
    $demoSecure = $null
    $adminCredential = $null
    $rng.Dispose()
}

$acl = New-Object System.Security.AccessControl.FileSecurity
$acl.SetAccessRuleProtection($true, $false)
$identity = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
$rule = New-Object System.Security.AccessControl.FileSystemAccessRule($identity, 'FullControl', 'Allow')
$acl.AddAccessRule($rule)
Set-Acl -LiteralPath $demoPath -AclObject $acl

Write-Output 'dpapi_store_created=yes'
