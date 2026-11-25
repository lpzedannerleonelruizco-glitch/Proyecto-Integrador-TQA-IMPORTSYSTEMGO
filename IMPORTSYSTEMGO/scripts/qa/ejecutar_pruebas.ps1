$ErrorActionPreference = 'Stop'
Set-Location (Resolve-Path "$PSScriptRoot\..\..")
$php = (Get-Command php).Source
$ext = Join-Path (Split-Path $php) 'ext'
& $php -d "extension_dir=$ext" -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll vendor/bin/phpunit --configuration phpunit.xml --filter QaCenterWorkflowTest
php vendor/bin/pint --test
npm.cmd run build
