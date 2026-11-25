$ErrorActionPreference = 'Stop'
Set-Location (Resolve-Path "$PSScriptRoot\..\..")
py -3.13 -m venv .venv-qa
& .\.venv-qa\Scripts\python.exe -m pip install --upgrade pip
& .\.venv-qa\Scripts\python.exe -m pip install -r qa_engine\requirements.txt
composer install
npm.cmd install
npm.cmd run build
