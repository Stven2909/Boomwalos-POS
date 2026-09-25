# ============================================================================
# watchdog.ps1 — Monitor y auto-recuperación de la terminal POS (Windows)
# ----------------------------------------------------------------------------
# Ejecutar como tarea programada con intervalo de 60 segundos.
# Comprueba, en orden:
#   1. Que el POS web responda en $WebUrl. Si el puerto no escucha relanza
#      pos-web (+pos-queue); si el puerto escucha pero la app no responde,
#      mata el proceso PHP que escucha y relanza ambos.
#   2. Que la cola de trabajos no tenga trabajos "procesando" atascados más de
#      $QueueStuckMinutes. Si los hay, reinicia el stack PHP (serve + queue).
#   3. Que el navegador kiosk siga abierto. Si no hay chrome con el perfil
#      kiosk, relanza kiosk.cmd (que hace polling a localhost:8000).
# Log con rotación diaria (conserva $LogKeepDays archivos).
# ----------------------------------------------------------------------------
# NOTA OPERATIVA 1: al detectar cola atascada, el watchdog mata TODOS los
#   procesos php.exe de la máquina (no solo los de pos-web/pos-queue). No
#   correr tinker/debug manual en la terminal mientras el watchdog está activo.
# NOTA OPERATIVA 2 (deuda técnica): la detección de cola consulta las columnas
#   reserved_at/available_at de la tabla `jobs` directamente por SQL. Si una
#   futura migración de Laravel renombra esas columnas, el script deja de
#   detectar atascos sin avisar: revisarlo si se toca esa tabla.
# ============================================================================

param(
    [string]$PosRoot = 'C:\pos',
    [string]$AppRoot = '',
    [string]$WebUrl = 'http://localhost:8000',
    [string]$PhpPath = 'C:\php\php.exe',
    [string]$KioskCmd = 'C:\pos\kiosk.cmd',
    [string]$TaskWeb = 'pos-web',
    [string]$TaskQueue = 'pos-queue',
    [int]$QueueStuckMinutes = 5,
    [int]$LogKeepDays = 7
)

$ErrorActionPreference = 'SilentlyContinue'

if (-not $AppRoot) { $AppRoot = Join-Path $PosRoot 'boomwalos-pos' }
$DbFile = Join-Path $AppRoot 'database\database.sqlite'
$LogDir = Join-Path $PosRoot 'logs'
$LogFile = Join-Path $LogDir 'watchdog.log'

New-Item -ItemType Directory -Force -Path $LogDir | Out-Null

function Write-Log {
    param([string]$Message)
    $line = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $Message"
    Add-Content -Path $LogFile -Value $line -Encoding UTF8
    if ((Get-Item $LogFile).Length -gt 1MB) {
        $stamp = Get-Date -Format 'yyyyMMdd'
        Rename-Item $LogFile -NewName "watchdog-$stamp.log" -Force | Out-Null
        foreach ($old in Get-ChildItem $LogDir -Filter 'watchdog-*.log' |
                Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$LogKeepDays) }) {
            Remove-Item $old.FullName -Force
        }
    }
}

function Test-PosWeb {
    param([int]$Attempts = 2)
    for ($i = 0; $i -lt $Attempts; $i++) {
        try {
            $r = Invoke-WebRequest -UseBasicParsing -Uri $WebUrl -TimeoutSec 3
            if ($r.StatusCode -lt 500) { return $true }
        } catch { }
        Start-Sleep -Seconds 5
    }
    return $false
}

function Get-PortListener {
    param([int]$Port)
    return Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue |
        Select-Object -First 1
}

function Get-QueueStuckCount {
    if (-not (Test-Path $DbFile)) { return 0 }
    $code = "`$pdo = new PDO('sqlite:" + $DbFile.Replace('\', "/") + "'); `$cutoff = time() - " + ($QueueStuckMinutes * 60) + "; echo (int) `$pdo->query('SELECT COUNT(*) FROM jobs WHERE reserved_at IS NOT NULL AND available_at < ' . `$cutoff)->fetchColumn();"
    $out = & $PhpPath -r $code 2>&1
    return ([int]($out -join '').Trim())
}

# --- 1. WEB ---
$webOk = Test-PosWeb
if (-not $webOk) {
    $listener = Get-PortListener 8000
    if (-not $listener) {
        Write-Log 'WEB: no responde y puerto 8000 sin listener. Relanzando pos-web y pos-queue.'
        schtasks /run /tn $TaskWeb | Out-Null
        schtasks /run /tn $TaskQueue | Out-Null
    } else {
        Write-Log "WEB: puerto 8000 escucha pero la app no responde. Reiniciando PHP (PID $($listener.OwningProcess))."
        Stop-Process -Id $listener.OwningProcess -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
        schtasks /run /tn $TaskWeb | Out-Null
        schtasks /run /tn $TaskQueue | Out-Null
    }
}

# --- 2. COLA ---
$stuck = Get-QueueStuckCount
if ($stuck -gt 0) {
    Write-Log "COLA: $stuck trabajo(s) atascado(s) hace > $QueueStuckMinutes min. Reiniciando stack PHP."
    Get-Process -Name php -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 2
    schtasks /run /tn $TaskWeb | Out-Null
    schtasks /run /tn $TaskQueue | Out-Null
}

# --- 3. KIOSK ---
$kioskPid = Get-CimInstance Win32_Process -Filter "Name='chrome.exe'" |
    Where-Object { $_.CommandLine -like '*pos-kiosk-profile*' } |
    Select-Object -First 1 -ExpandProperty ProcessId

if (-not $kioskPid) {
    Write-Log 'KIOSK: sin instancia de chrome kiosk. Relanzando kiosk.cmd.'
    Start-Process -FilePath $KioskCmd -WorkingDirectory (Split-Path $KioskCmd -Parent)
}