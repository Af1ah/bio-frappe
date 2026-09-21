[CmdletBinding()]
param(
    [string]$CaddyPath = 'C:\caddy\caddy.exe',
    [string]$PhpCgiPath = 'C:\PHP\8.3\php-cgi.exe',
    [int]$FastCgiPort = 9000
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$caddyfile = Join-Path $projectRoot 'Caddyfile'
$artisan = Join-Path $projectRoot 'artisan'

foreach ($requiredPath in @($CaddyPath, $PhpCgiPath, $caddyfile, $artisan)) {
    if (-not (Test-Path -LiteralPath $requiredPath)) {
        throw "Required file was not found: $requiredPath"
    }
}

if (-not (Get-NetTCPConnection -LocalPort $FastCgiPort -State Listen -ErrorAction SilentlyContinue)) {
    Start-Process -FilePath $PhpCgiPath -ArgumentList "-b 127.0.0.1:$FastCgiPort" -WorkingDirectory $projectRoot -WindowStyle Hidden
}

function Start-ManagedProcess {
    param(
        [Parameter(Mandatory)] [string]$Arguments,
        [Parameter(Mandatory)] [string]$PidFile
    )

    $existingId = if (Test-Path -LiteralPath $PidFile) { Get-Content -LiteralPath $PidFile -Raw } else { $null }
    if ($existingId -and (Get-Process -Id $existingId.Trim() -ErrorAction SilentlyContinue)) {
        return
    }

    $process = Start-Process -FilePath 'php.exe' -ArgumentList $Arguments -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru
    Set-Content -LiteralPath $PidFile -Value $process.Id -NoNewline
}

$runtimeDirectory = Join-Path $projectRoot 'storage\framework'
New-Item -ItemType Directory -Path $runtimeDirectory -Force | Out-Null
Start-ManagedProcess -Arguments 'artisan queue:work --sleep=2 --tries=3 --timeout=120' -PidFile (Join-Path $runtimeDirectory 'bio-frappe-worker.pid')
Start-ManagedProcess -Arguments 'artisan schedule:work' -PidFile (Join-Path $runtimeDirectory 'bio-frappe-scheduler.pid')

& $CaddyPath reload --config $caddyfile --adapter caddyfile
if ($LASTEXITCODE -ne 0) {
    & $CaddyPath start --config $caddyfile --adapter caddyfile
    if ($LASTEXITCODE -ne 0) {
        throw 'Caddy could not start with the Bio-Frappe configuration.'
    }

    Start-Sleep -Seconds 2
    & $CaddyPath reload --config $caddyfile --adapter caddyfile
    if ($LASTEXITCODE -ne 0) {
        throw 'Caddy started but could not load the Bio-Frappe configuration.'
    }
}

Write-Host 'Bio-Frappe is hosted at https://localhost.'
