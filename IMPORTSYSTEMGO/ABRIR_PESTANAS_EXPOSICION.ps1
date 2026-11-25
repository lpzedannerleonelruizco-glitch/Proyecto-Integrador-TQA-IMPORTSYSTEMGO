param(
    [ValidateSet('edge', 'chrome', 'brave')]
    [string]$Browser = 'edge'
)

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$baseUrl = 'http://127.0.0.1:8001'
$functionalPdf = (Resolve-Path (Join-Path $root 'EXPOSICION_TQA\Reportes\Funcional\reports\QA_Report_FUNCIONAL-20261124.pdf')).Path
$functionalHtml = (Resolve-Path (Join-Path $root 'EXPOSICION_TQA\Reportes\Funcional\reports\QA_Summary_FUNCIONAL-20261124.html')).Path
$failHtml = (Resolve-Path (Join-Path $root 'EXPOSICION_TQA\Reportes\Fail-Controlado\reports\QA_Summary_FAIL-CONTROLADO-20261124.html')).Path
$performanceHtml = (Resolve-Path (Join-Path $root 'EXPOSICION_TQA\Reportes\Rendimiento\reports\QA_Summary_RENDIMIENTO-20261124.html')).Path
$urls = @(
    "$baseUrl/login",
    "$baseUrl/qa",
    "$baseUrl/qa/nueva-ejecucion",
    $functionalHtml,
    $failHtml,
    $functionalPdf,
    $functionalHtml,
    'http://127.0.0.1:8025',
    $performanceHtml,
    "$baseUrl/qa/ia",
    'https://github.com/lpzedannerleonelruizco-glitch/Proyecto-Integrador-TQA-IMPORTSYSTEMGO'
)

$candidates = switch ($Browser) {
    'edge' { @((Join-Path ${env:ProgramFiles(x86)} 'Microsoft\Edge\Application\msedge.exe'), (Join-Path $env:ProgramFiles 'Microsoft\Edge\Application\msedge.exe')) }
    'chrome' { @((Join-Path $env:ProgramFiles 'Google\Chrome\Application\chrome.exe'), (Join-Path ${env:ProgramFiles(x86)} 'Google\Chrome\Application\chrome.exe')) }
    'brave' { @((Join-Path $env:ProgramFiles 'BraveSoftware\Brave-Browser\Application\brave.exe'), (Join-Path ${env:ProgramFiles(x86)} 'BraveSoftware\Brave-Browser\Application\brave.exe')) }
}
$executable = $candidates | Where-Object { $_ -and (Test-Path -LiteralPath $_ -PathType Leaf) } | Select-Object -First 1
if (-not $executable) {
    throw "No se encontró el navegador solicitado: $Browser."
}

Start-Process -FilePath $executable -ArgumentList (@('--new-window') + $urls)
Write-Host 'Pestañas abiertas con rutas reales. Los reportes y resultados se abren desde la ejecución verificada más reciente.'
