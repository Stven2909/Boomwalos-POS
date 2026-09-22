# ============================================================================
# backup.ps1 — Backup nocturno de la terminal POS (Windows)
# ----------------------------------------------------------------------------
# Crea una copia consistente de la base SQLite usando la API nativa SQLite3
# (SQLite3::backup vía PHP, segura con la aplicación corriendo), y copia
# storage/logs + .env. Conserva $RetentionDays copias.
# Programación sugerida: todos los días, 04:30 (cuando el negocio no opera).
# ============================================================================

param(
    [string]$PosRoot = 'C:\pos',
    [string]$AppRoot = '',
    [string]$PhpPath = 'C:\php\php.exe',
    [int]$RetentionDays = 7
)

$ErrorActionPreference = 'Stop'

if (-not $AppRoot) { $AppRoot = Join-Path $PosRoot 'boomwalos-pos' }

$DbFile = Join-Path $AppRoot 'database\database.sqlite'
$LogsSource = Join-Path $AppRoot 'storage\logs'
$EnvFile = Join-Path $AppRoot '.env'
$BackupRoot = Join-Path $PosRoot 'backups'
$Stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$Dest = Join-Path $BackupRoot $Stamp
$DestDb = Join-Path $Dest 'database.sqlite'
$DestLogs = Join-Path $Dest 'logs'
$DestEnv = Join-Path $Dest '.env'

New-Item -ItemType Directory -Force -Path $Dest | Out-Null
New-Item -ItemType Directory -Force -Path $DestLogs | Out-Null

# --- Backup consistente de SQLite (sin copia cruda a la carrera) ---
if (-not (Test-Path $DbFile)) {
    Write-Error "No se encontró la base SQLite: $DbFile"
}

$phpCode = '
$src = new SQLite3($argv[1], SQLITE3_OPEN_READONLY);
$dst = new SQLite3($argv[2]);
if (! $src->backup($dst)) { fwrite(STDERR, "backup() fallo\n"); exit(1); }
$src->close();
$dst->close();
echo "ok\n";
'

& $PhpPath -r $phpCode $DbFile $DestDb
if ($LASTEXITCODE -ne 0) {
    Write-Error "SQLite backup devolvió código $LASTEXITCODE."
}

# --- Logs y .env ---
Copy-Item -Path "$LogsSource\*.log" -Destination $DestLogs -Force -ErrorAction SilentlyContinue
if (Test-Path $EnvFile) {
    Copy-Item -Path $EnvFile -Destination $DestEnv -Force
}

# --- Retención ---
$Cutoff = (Get-Date).AddDays(-$RetentionDays)
Get-ChildItem $BackupRoot -Directory |
    Where-Object { $_.Name -match '^\d{8}-\d{6}$' -and $_.LastWriteTime -lt $Cutoff } |
    Remove-Item -Recurse -Force -ErrorAction SilentlyContinue

Write-Output "Backup completado en $Dest"