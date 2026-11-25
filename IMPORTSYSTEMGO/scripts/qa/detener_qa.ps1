Get-CimInstance Win32_Process | Where-Object {
    $_.Name -eq 'php.exe' -and $_.CommandLine -match 'IMPORTSYSTEMGO.*artisan serve'
} | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }
