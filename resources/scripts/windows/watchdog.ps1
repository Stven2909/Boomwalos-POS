# ============================================================================
# watchdog.ps1 — Monitor y auto-recuperación de la terminal POS (Windows)
# ----------------------------------------------------------------------------
# Ejecutar como tarea programada con intervalo de 60 segundos.
# Comprueba, en orden:
#   1. WEB: que el POS responda en $WebUrl. Se usa 127.0.0.1 (NO "localhost")
#      para evitar el fallback a IPv6 ::1, que da falsos negativos cuando
#      `artisan serve` escucha solo en IPv4. Reinicia el stack solo tras
#      $WebFailThreshold fallos CONSECUTIVOS (evita reinicios por parpadeos).
#   2. COLA: trabajos "procesando" atascados > $QueueStuckMinutes → reinicia.
#   3. KIOSK: si no hay chrome del perfil kiosk, relanza kiosk.cmd OCULTO
#      (via run-hidden.vbs, para no mostrar ninguna ventana de consola).
# Log con rotación diaria (conserva $LogKeepDays archivos).
# ----------------------------------------------------------------------------
# NOTA OPERATIVA 1: al reiniciar el stack mata TODOS los php.exe de la máquina
#   y el Chrome del perfil kiosk. No correr tinker/debug manual en la terminal
#   mientras el watchdog está activo.
# NOTA OPERATIVA 2 (deuda técnica): la detección de cola consulta las columnas
#   reserved_at/available_at de la tabla `jobs` directamente por SQL. Si una
#   futura migración de Laravel renombra esas columnas, el script deja de
#   detectar atascos sin avisar: revisarlo si se toca esa tabla.
# ============================================================================

param(
    [string]$PosRoot = 'C:\pos',
    [string]$AppRoot = '',
    [string]$WebUrl = 'http://127.0.0.1:8000',
    [string]$PhpPath = 'C:\php\php.exe',
    [string]$KioskCmd = 'C:\pos\boomwalos-pos\resources\scripts\windows\kiosk.cmd',
    [string]$RunHidden = 'C:\pos\run-hidden.vbs',
    [string]$TaskStartup = 'pos-startup',
    [int]$QueueStuckMinutes = 5,
    [int]$WebFailThreshold = 3,
    [int]$LogKeepDays = 7
)

$ErrorActionPreference = 'SilentlyContinue'

if (-not $AppRoot) { $AppRoot = Join-Path $PosRoot 'boomwalos-pos' }
$DbFile = Join-Path $AppRoot 'database\database.sqlite'
$LogDir = Join-Path $PosRoot 'logs'
$LogFile = Join-Path $LogDir 'watchdog.log'
$WebFailFile = Join-Path $LogDir 'web-fail.count'

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
        Start-Sleep -Seconds 3
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

function Start-Hidden {
    # Lanza un proceso sin ventana visible, usando el shim run-hidden.vbs.
    param([string]$Exe, [string]$Arguments)
    if (Test-Path $RunHidden) {
        Start-Process -FilePath 'wscript.exe' -ArgumentList @($RunHidden, $Exe, $Arguments) -WindowStyle Hidden
    } else {
        Start-Process -FilePath $Exe -ArgumentList $Arguments -WindowStyle Hidden
    }
}

function Restart-PosStack {
    param([string]$Reason)
    Write-Log "STACK: reiniciando stack completo ($Reason)."
    Get-Process -Name php -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
    Get-CimInstance Win32_Process -Filter "Name='chrome.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -like '*pos-kiosk-profile*' } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
    Start-Sleep -Seconds 2
    schtasks /run /tn $TaskStartup | Out-Null
}

# --- 1. WEB ---
$webOk = Test-PosWeb
if ($webOk) {
    if (Test-Path $WebFailFile) { Remove-Item $WebFailFile -Force -ErrorAction SilentlyContinue }
} else {
    $fails = 0
    if (Test-Path $WebFailFile) {
        $fails = [int](((Get-Content $WebFailFile -ErrorAction SilentlyContinue) -join '').Trim())
    }
    $fails++
    Set-Content -Path $WebFailFile -Value $fails -Encoding ASCII

    if ($fails -ge $WebFailThreshold) {
        $listener = Get-PortListener 8000
        if ($listener) {
            Restart-PosStack "puerto 8000 escucha pero la app no responde (PID $($listener.OwningProcess))"
        } else {
            Restart-PosStack 'no responde, puerto 8000 sin listener'
        }
        Remove-Item $WebFailFile -Force -ErrorAction SilentlyContinue
    } else {
        Write-Log "WEB: sin respuesta (fallo $fails/$WebFailThreshold consecutivo); aun no reinicio."
    }
}

# --- 2. COLA ---
$stuck = Get-QueueStuckCount
if ($stuck -gt 0) {
    Restart-PosStack "$stuck trabajo(s) atascado(s) hace > $QueueStuckMinutes min"
}

# --- 3. KIOSK ---
$kioskPid = Get-CimInstance Win32_Process -Filter "Name='chrome.exe'" |
    Where-Object { $_.CommandLine -like '*pos-kiosk-profile*' } |
    Select-Object -First 1 -ExpandProperty ProcessId

if (-not $kioskPid) {
    Write-Log 'KIOSK: sin instancia de chrome kiosk. Relanzando kiosk.cmd (oculto).'
    Start-Hidden -Exe 'cmd.exe' -Arguments "/c $KioskCmd"
}
