$ErrorActionPreference = 'Stop'
Set-Location (Resolve-Path "$PSScriptRoot\..\..")
php -v
& .\.venv-qa\Scripts\python.exe --version
& .\.venv-qa\Scripts\python.exe -c "import selenium,pytest,robot,locust,reportlab,jinja2; print('QA Python dependencies: OK')"
php artisan route:list --path=qa
