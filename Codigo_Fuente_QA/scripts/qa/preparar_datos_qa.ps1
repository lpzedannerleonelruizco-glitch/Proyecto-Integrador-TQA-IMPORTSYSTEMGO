$ErrorActionPreference = 'Stop'
Set-Location (Resolve-Path "$PSScriptRoot\..\..")
$php = (Get-Command php).Source
$ext = Join-Path (Split-Path $php) 'ext'
$qaDb = (Resolve-Path .).Path + '\database\qa.sqlite'
if (-not (Test-Path -LiteralPath $qaDb)) { New-Item -ItemType File -Path $qaDb -Force | Out-Null }
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $qaDb
$env:DB_URL = ''
$env:MAIL_MAILER = 'log'
& $php -d "extension_dir=$ext" -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll artisan migrate --database=sqlite --force
& $php -d "extension_dir=$ext" -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll artisan db:seed --class=AuthorizationSeeder --force
& $php -d "extension_dir=$ext" -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll artisan db:seed --class=QaCenterSeeder --force
